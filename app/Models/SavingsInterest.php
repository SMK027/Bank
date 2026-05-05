<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Intérêts annuels calculés pour les comptes épargne.
 *
 * Cycle de vie d'un enregistrement :
 *   1. Créé par le cron du 1er janvier avec status = 'pending'.
 *   2. L'utilisateur confirme via l'interface → status = 'confirmed', transaction créée.
 *   3. (Optionnel) status = 'rejected' si l'admin annule manuellement.
 */
class SavingsInterest extends Model
{
    protected string $table       = 'savings_interests';
    protected bool   $hasUpdatedAt = false;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED  = 'rejected';

    // ── Lecture ──────────────────────────────────────────────────────────────

    /** Retourne les intérêts en attente de confirmation pour un utilisateur donné. */
    public function getPendingForUser(int $userId): array
    {
        $sql = "SELECT si.*, a.name AS account_name, a.currency, a.cap, a.type
                FROM `savings_interests` si
                JOIN `accounts` a ON a.id = si.account_id
                WHERE a.user_id = ? AND si.status = 'pending'
                ORDER BY si.year DESC, si.id DESC";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Vérifie si un intérêt en attente existe déjà pour ce compte/année. */
    public function existsForAccountYear(int $accountId, int $year): bool
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT COUNT(*) FROM `savings_interests`
             WHERE `account_id` = ? AND `year` = ?"
        );
        $stmt->execute([$accountId, $year]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Supprime toute entrée en statut 'pending' pour ce compte et cette année.
     * Utilisé pour recalculer les intérêts à la demande sur les comptes internes.
     */
    public function deletePendingForAccount(int $accountId, int $year): void
    {
        $stmt = $this->getPdo()->prepare(
            "DELETE FROM `savings_interests`
             WHERE `account_id` = ? AND `year` = ? AND `status` = 'pending'"
        );
        $stmt->execute([$accountId, $year]);
    }

    // ── Calculs ───────────────────────────────────────────────────────────────

    /**
     * Calcule le montant maximum théorique autorisé pour un versement d'intérêts.
     *
     * max = balance_avant_versement × taux
     *
     * Le plafond (cap) limite les versements manuels mais pas les intérêts :
     * les intérêts sont toujours calculés sur le solde complet, même si le
     * compte a atteint ou dépassé son plafond.
     */
    public static function computeMaxAmount(float $balanceBefore, float $rate, ?float $cap): float
    {
        if ($balanceBefore <= 0) {
            return 0.0;
        }
        return max(0.0, round($balanceBefore * $rate, 2));
    }

    /**
     * Calcule les intérêts au prorata temporis (TWAB — Time-Weighted Average Balance)
     * pour un compte sur une année civile entière.
     *
     * Algorithme :
     *   - Solde de départ au 1er janvier de l'année.
     *   - Pour chaque transaction de l'année (triées par created_at ASC), on calcule
     *     la portion de l'année où le solde était constant et on l'accumule.
     *   - Intérêts = TWAB × taux
     *
     * @param int         $accountId  Identifiant du compte
     * @param int         $year       Année civile (ex : 2025)
     * @param float       $rate       Taux annuel brut (ex : 0.03 pour 3 %)
     * @param Transaction $txModel    Instance du modèle Transaction
     */
    public static function calculateProrata(
        int         $accountId,
        int         $year,
        float       $rate,
        Transaction $txModel,
        array       $rateSegments = []
    ): float {
        $yearStartDate = sprintf('%04d-01-01', $year);
        $yearEndDate   = sprintf('%04d-12-31', $year);

        $yearStartTs = (int) mktime(0, 0, 0, 1, 1, $year);
        $yearEndTs   = (int) mktime(0, 0, 0, 1, 1, $year + 1);
        $totalDays   = ($yearEndTs - $yearStartTs) / 86400;

        $startBalance = $txModel->getBalanceBeforeDate($accountId, $yearStartDate);
        $transactions = $txModel->getByAccountBetween($accountId, $yearStartDate, $yearEndDate);

        if (empty($rateSegments)) {
            // Taux unique — comportement original
            $curTs  = (float) $yearStartTs;
            $curBal = $startBalance;
            $wsum   = 0.0;
            foreach ($transactions as $t) {
                // Utilise scheduled_at comme date d'effet si disponible (opération planifiée backdatée)
                $tTs = (float) strtotime($t['scheduled_at'] ?? $t['created_at']);
                if ($tTs < $yearStartTs || $tTs >= $yearEndTs) {
                    continue;
                }
                $wsum  += $curBal * max(0.0, ($tTs - $curTs) / 86400);
                $curBal += ($t['type'] === 'income') ? (float) $t['amount'] : -(float) $t['amount'];
                $curTs   = $tTs;
            }
            $wsum += $curBal * max(0.0, ($yearEndTs - $curTs) / 86400);
            return round(max(0.0, ($wsum / $totalDays) * $rate), 2);
        }

        return round(self::computeMultiRate(
            $startBalance, $transactions, $rate,
            $rateSegments, $yearStartTs, $yearEndTs, $totalDays
        ), 2);
    }

    /**
     * Calcule les intérêts accumulés (en cours) depuis le 1er janvier de l'année
     * courante jusqu'à maintenant, au prorata temporis (TWAB).
     *
     * - Le dénominateur est toujours 365 ou 366 jours (année civile complète),
     *   ce qui assure la cohérence avec calculateProrata().
     * - Retourne 0,00 le 1er janvier (nouveau départ de cycle).
     *
     * @param int         $accountId
     * @param float       $rate         Taux annuel brut (ex : 0.03 = 3 %)
     * @param Transaction $txModel
     * @param array       $rateSegments Segments de modération [{from, to, rate}] (optionnel)
     */
    public static function calculateAccrued(
        int         $accountId,
        float       $rate,
        Transaction $txModel,
        array       $rateSegments = []
    ): float {
        $year          = (int) date('Y');
        $yearStartDate = sprintf('%04d-01-01', $year);
        $todayDate     = date('Y-m-d');

        $yearStartTs = (int) mktime(0, 0, 0, 1, 1, $year);
        $yearEndTs   = (int) mktime(0, 0, 0, 1, 1, $year + 1);
        $nowTs       = min(time(), $yearEndTs);
        $totalDays   = ($yearEndTs - $yearStartTs) / 86400;

        if ($nowTs <= $yearStartTs) {
            return 0.0;
        }

        $startBalance = $txModel->getBalanceBeforeDate($accountId, $yearStartDate);
        $transactions = $txModel->getByAccountBetween($accountId, $yearStartDate, $todayDate);

        if (empty($rateSegments)) {
            // Taux unique — comportement original
            $curTs  = (float) $yearStartTs;
            $curBal = $startBalance;
            $wsum   = 0.0;
            foreach ($transactions as $t) {
                // Utilise scheduled_at comme date d'effet si disponible (opération planifiée backdatée)
                $tTs = (float) strtotime($t['scheduled_at'] ?? $t['created_at']);
                if ($tTs < $yearStartTs || $tTs >= $nowTs) {
                    continue;
                }
                $wsum  += $curBal * max(0.0, ($tTs - $curTs) / 86400);
                $curBal += ($t['type'] === 'income') ? (float) $t['amount'] : -(float) $t['amount'];
                $curTs   = $tTs;
            }
            $wsum += $curBal * max(0.0, ($nowTs - $curTs) / 86400);
            return round(($wsum / $totalDays) * $rate, 2);
        }

        // Rogner les segments à [yearStartTs, nowTs)
        $clipped = [];
        foreach ($rateSegments as $seg) {
            $f = max((int) $seg['from'], $yearStartTs);
            $t = min((int) $seg['to'],   $nowTs);
            if ($f < $t) {
                $clipped[] = ['from' => $f, 'to' => $t, 'rate' => $seg['rate']];
            }
        }

        return round(self::computeMultiRate(
            $startBalance, $transactions, $rate,
            $clipped, $yearStartTs, $nowTs, $totalDays
        ), 2);
    }

    /**
     * Moteur TWAB multi-taux.
     *
     * Pour chaque segment de modération, calcule la contribution pondérée des soldes
     * et applique le taux effectif = min(accountRate, moderation_rate).
     * Si 'rate' est null dans un segment, on utilise accountRate sans plafond.
     *
     * @param float  $startBalance   Solde initial de la période
     * @param array  $transactions   Triées ASC par created_at
     * @param float  $accountRate    Taux propre au compte
     * @param array  $rateSegments   [{from:int, to:int, rate:float|null}], triés ASC
     * @param int    $periodStartTs  Début de la période (borné par les segments)
     * @param int    $periodEndTs    Fin exclusive de la période
     * @param float  $totalYearDays  Dénominateur (jours de l'année civile)
     */
    private static function computeMultiRate(
        float $startBalance,
        array $transactions,
        float $accountRate,
        array $rateSegments,
        int   $periodStartTs,
        int   $periodEndTs,
        float $totalYearDays
    ): float {
        $txIndex    = 0;
        $txCount    = count($transactions);
        $curBalance = $startBalance;
        $curTs      = (float) $periodStartTs;
        $totalInt   = 0.0;

        foreach ($rateSegments as $seg) {
            $segFrom = max((float) $seg['from'], (float) $periodStartTs);
            $segTo   = min((float) $seg['to'],   (float) $periodEndTs);
            if ($segFrom >= $segTo) {
                continue;
            }
            $modRate = $seg['rate'];
            $effRate = ($modRate !== null) ? min($accountRate, (float) $modRate) : $accountRate;

            // Avancer curTs jusqu'à segFrom (combler un éventuel écart entre segments)
            if ($curTs < $segFrom) {
                while ($txIndex < $txCount) {
                    $tTs = (float) strtotime($transactions[$txIndex]['scheduled_at'] ?? $transactions[$txIndex]['created_at']);
                    if ($tTs >= $segFrom) {
                        break;
                    }
                    $curBalance += ($transactions[$txIndex]['type'] === 'income')
                        ? (float) $transactions[$txIndex]['amount']
                        : -(float) $transactions[$txIndex]['amount'];
                    $txIndex++;
                }
                $curTs = $segFrom;
            }

            // Calculer la somme pondérée dans ce segment
            $weightedSum = 0.0;
            while ($txIndex < $txCount) {
                $tTs = (float) strtotime($transactions[$txIndex]['scheduled_at'] ?? $transactions[$txIndex]['created_at']);
                if ($tTs >= $segTo) {
                    break;
                }
                $weightedSum += $curBalance * max(0.0, ($tTs - $curTs) / 86400);
                $curBalance  += ($transactions[$txIndex]['type'] === 'income')
                    ? (float) $transactions[$txIndex]['amount']
                    : -(float) $transactions[$txIndex]['amount'];
                $curTs = $tTs;
                $txIndex++;
            }
            $weightedSum += $curBalance * max(0.0, ($segTo - $curTs) / 86400);
            $curTs = $segTo;

            if ($effRate > 0.0) {
                $totalInt += $weightedSum * $effRate / $totalYearDays;
            }
        }

        return max(0.0, $totalInt);
    }
}
