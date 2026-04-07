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
     * ou null si aucun taux n'est configuré pour ce type.
     */
    public function getCurrent(string $accountType = 'savings'): ?array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT sr.*, u.username AS set_by_username
             FROM `savings_rates` sr
             LEFT JOIN `users` u ON u.id = sr.set_by
             WHERE sr.`account_type` = ?
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
     * @param float  $rate        Taux décimal brut (ex : 0.03 pour 3 %)
     * @param string $accountType Type de compte (ex : 'savings')
     * @param int    $moderatorId Identifiant du modérateur
     */
    public function setRate(float $rate, string $accountType, int $moderatorId): int
    {
        return $this->create([
            'account_type' => $accountType,
            'rate'         => round($rate, 6),
            'set_by'       => $moderatorId,
        ]);
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
}

