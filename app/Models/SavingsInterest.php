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

    // ── Calculs ───────────────────────────────────────────────────────────────

    /**
     * Calcule le montant maximum théorique autorisé pour un versement d'intérêts.
     *
     * max = balance_avant_versement × taux
     * Si le compte a un plafond : max = min(max, plafond − balance)
     * Retourne 0 si le compte est déjà au plafond ou si le solde est négatif.
     */
    public static function computeMaxAmount(float $balanceBefore, float $rate, ?float $cap): float
    {
        if ($balanceBefore <= 0) {
            return 0.0;
        }
        $max = $balanceBefore * $rate;
        if ($cap !== null && $cap > 0) {
            $room = $cap - $balanceBefore;
            if ($room <= 0) {
                return 0.0;
            }
            $max = min($max, $room);
        }
        return max(0.0, round($max, 2));
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
        Transaction $txModel
    ): float {
        $yearStartDate = sprintf('%04d-01-01', $year);
        $yearEndDate   = sprintf('%04d-12-31', $year);

        $yearStartTs = mktime(0, 0, 0, 1, 1, $year);
        $yearEndTs   = mktime(0, 0, 0, 1, 1, $year + 1); // borne exclusive
        $totalDays   = ($yearEndTs - $yearStartTs) / 86400; // 365 ou 366

        // Solde au début de l'année (avant la première seconde de $year)
        $currentBalance = $txModel->getBalanceBeforeDate($accountId, $yearStartDate);

        // Toutes les transactions exécutées pendant l'année (triées par created_at ASC)
        $transactions = $txModel->getByAccountBetween($accountId, $yearStartDate, $yearEndDate);

        $currentTs   = (float) $yearStartTs;
        $weightedSum = 0.0;

        foreach ($transactions as $t) {
            $tTs = (float) strtotime($t['created_at']);
            if ($tTs < $yearStartTs || $tTs >= $yearEndTs) {
                continue;
            }
            $daysHeld     = max(0.0, ($tTs - $currentTs) / 86400);
            $weightedSum += $currentBalance * $daysHeld;
            $currentBalance += ($t['type'] === 'income')
                ? (float) $t['amount']
                : -(float) $t['amount'];
            $currentTs = $tTs;
        }

        // Dernier segment : de la dernière transaction jusqu'à la fin de l'année
        $daysHeld     = max(0.0, ($yearEndTs - $currentTs) / 86400);
        $weightedSum += $currentBalance * $daysHeld;

        $twab     = $weightedSum / $totalDays;
        $interest = max(0.0, $twab * $rate);
        return round($interest, 2);
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
     * @param float       $rate       Taux annuel brut (ex : 0.03 = 3 %)
     * @param Transaction $txModel
     */
    public static function calculateAccrued(
        int         $accountId,
        float       $rate,
        Transaction $txModel
    ): float {
        $year          = (int) date('Y');
        $yearStartDate = sprintf('%04d-01-01', $year);
        $todayDate     = date('Y-m-d');

        $yearStartTs = (int) mktime(0, 0, 0, 1, 1, $year);
        $yearEndTs   = (int) mktime(0, 0, 0, 1, 1, $year + 1); // dénominateur
        $nowTs       = min(time(), $yearEndTs);
        $totalDays   = ($yearEndTs - $yearStartTs) / 86400; // 365 ou 366

        if ($nowTs <= $yearStartTs) {
            return 0.0;
        }

        $currentBalance = $txModel->getBalanceBeforeDate($accountId, $yearStartDate);
        $transactions   = $txModel->getByAccountBetween($accountId, $yearStartDate, $todayDate);

        $currentTs   = (float) $yearStartTs;
        $weightedSum = 0.0;

        foreach ($transactions as $t) {
            $tTs = (float) strtotime($t['created_at']);
            if ($tTs < $yearStartTs || $tTs >= $nowTs) {
                continue;
            }
            $daysHeld     = max(0.0, ($tTs - $currentTs) / 86400);
            $weightedSum += $currentBalance * $daysHeld;
            $currentBalance += ($t['type'] === 'income')
                ? (float) $t['amount']
                : -(float) $t['amount'];
            $currentTs = $tTs;
        }

        // Segment final : de la dernière transaction jusqu'à maintenant
        $daysHeld     = max(0.0, ($nowTs - $currentTs) / 86400);
        $weightedSum += $currentBalance * $daysHeld;

        $twab     = $weightedSum / $totalDays;
        $interest = max(0.0, $twab * $rate);
        return round($interest, 2);
    }
}
