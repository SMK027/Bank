<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Taux d'intérêt annuel par type de compte épargne.
 * Seul un modérateur peut enregistrer un nouveau taux.
 *
 * Chaque type de compte éligible (savings, online, …) possède son propre
 * historique de taux. Le taux actuel d'un type est le dernier enregistrement
 * pour ce type (ORDER BY created_at DESC).
 */
class SavingsRate extends Model
{
    protected string $table       = 'savings_rates';
    protected bool   $hasUpdatedAt = false;

    // ── Lecture ───────────────────────────────────────────────────────────────

    /**
     * Retourne l'enregistrement du taux le plus récent pour un type donné,
     * parmi ceux dont la date d'effet est déjà passée ou aujourd'hui.
     * Retourne null si aucun taux n'est encore actif pour ce type.
     */
    public function getCurrent(string $accountType = 'savings'): ?array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT sr.*, u.username AS set_by_username
             FROM `savings_rates` sr
             LEFT JOIN `users` u ON u.id = sr.set_by
             WHERE sr.`account_type` = ?
               AND sr.`created_at` <= NOW()
             ORDER BY sr.`created_at` DESC, sr.`id` DESC
             LIMIT 1'
        );
        $stmt->execute([$accountType]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Retourne le taux actuel (float) pour un type, null si non configuré.
     */
    public function getCurrentRate(string $accountType = 'savings'): ?float
    {
        $row = $this->getCurrent($accountType);
        return $row ? (float) $row['rate'] : null;
    }

    /**
     * Retourne le taux actuel pour chaque type éligible aux intérêts.
     * Clé = account_type, valeur = tableau retourné par getCurrent() (ou null).
     *
     * @return array<string, array|null>
     */
    public function getAllCurrentRates(): array
    {
        $types  = Account::getInterestEligibleTypes();
        $result = [];
        foreach ($types as $type) {
            $result[$type] = $this->getCurrent($type);
        }
        return $result;
    }

    // ── Écriture ───────────────────────────────────────────────────────────────

    /**
     * Enregistre un nouveau taux pour un type de compte donné.
     * L'ancien taux est conservé dans l'historique.
     *
     * @param float       $rate         Taux décimal brut (ex : 0.03 pour 3 %)
     * @param string      $accountType  Type de compte (ex : 'savings')
     * @param int         $moderatorId  Identifiant du modérateur
     * @param string|null $effectiveAt  Date d'effet ISO 'YYYY-MM-DD HH:MM:SS' (null = maintenant)
     */
    public function setRate(float $rate, string $accountType, int $moderatorId, ?string $effectiveAt = null): int
    {
        $effectiveAt = $effectiveAt ?? date('Y-m-d H:i:s');
        $stmt = $this->getPdo()->prepare(
            'INSERT INTO `savings_rates` (`account_type`, `rate`, `set_by`, `created_at`)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$accountType, round($rate, 6), $moderatorId, $effectiveAt]);
        return (int) $this->getPdo()->lastInsertId();
    }

    // ── Historique ─────────────────────────────────────────────────────────────

    /**
     * Retourne l'historique des taux, optionnellement filtré par type.
     */
    public function getHistory(int $limit = 20, ?string $accountType = null): array
    {
        $sql    = 'SELECT sr.*, u.username AS set_by_username
                   FROM `savings_rates` sr
                   LEFT JOIN `users` u ON u.id = sr.set_by';
        $params = [];
        if ($accountType !== null) {
            $sql    .= ' WHERE sr.`account_type` = ?';
            $params[] = $accountType;
        }
        $sql .= ' ORDER BY sr.`created_at` DESC, sr.`id` DESC LIMIT ?';
        $params[] = $limit;

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Construit les segments de taux de modération pour une année civile.
     * Chaque segment couvre [from_ts, to_ts) avec le taux actif sur la période.
     * 'rate' => null si aucun taux n'est configuré pour la période.
     *
     * @return array<int, array{from:int, to:int, rate:float|null}>
     */
    public function getRateSegmentsForYear(string $accountType, int $year): array
    {
        $yearStartDate = sprintf('%04d-01-01 00:00:00', $year);
        $yearEndDate   = sprintf('%04d-01-01 00:00:00', $year + 1);
        $yearStartTs   = (int) mktime(0, 0, 0, 1, 1, $year);
        $yearEndTs     = (int) mktime(0, 0, 0, 1, 1, $year + 1);

        // Taux actif au début de l'année (dernier enregistrement avant Jan 1)
        $stmt = $this->getPdo()->prepare(
            'SELECT `rate` FROM `savings_rates`
             WHERE `account_type` = ? AND `created_at` < ?
             ORDER BY `created_at` DESC, `id` DESC LIMIT 1'
        );
        $stmt->execute([$accountType, $yearStartDate]);
        $initialRate = $stmt->fetchColumn();

        // Changements survenus pendant l'année, triés chronologiquement
        $stmt = $this->getPdo()->prepare(
            'SELECT `rate`, `created_at` FROM `savings_rates`
             WHERE `account_type` = ? AND `created_at` >= ? AND `created_at` < ?
             ORDER BY `created_at` ASC, `id` ASC'
        );
        $stmt->execute([$accountType, $yearStartDate, $yearEndDate]);
        $changes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $segments   = [];
        $curRate    = $initialRate !== false ? (float) $initialRate : null;
        $segStartTs = $yearStartTs;

        foreach ($changes as $change) {
            $changeTs = (int) strtotime($change['created_at']);
            if ($changeTs > $segStartTs) {
                $segments[] = ['from' => $segStartTs, 'to' => $changeTs, 'rate' => $curRate];
            }
            $curRate    = (float) $change['rate'];
            $segStartTs = $changeTs;
        }
        if ($segStartTs < $yearEndTs) {
            $segments[] = ['from' => $segStartTs, 'to' => $yearEndTs, 'rate' => $curRate];
        }

        return $segments;
    }
}

