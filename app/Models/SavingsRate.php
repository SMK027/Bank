<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Taux d'intérêt annuel des comptes épargne.
 * Seul un modérateur peut enregistrer un nouveau taux.
 */
class SavingsRate extends Model
{
    protected string $table      = 'savings_rates';
    protected bool   $hasUpdatedAt = false;

    /** Retourne l'enregistrement du taux le plus récent (ou null si aucun). */
    public function getCurrent(): ?array
    {
        $stmt = $this->getPdo()->query(
            'SELECT * FROM `savings_rates` ORDER BY `created_at` DESC, `id` DESC LIMIT 1'
        );
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** Retourne le taux actuel sous forme de float (null si aucun taux configuré). */
    public function getCurrentRate(): ?float
    {
        $row = $this->getCurrent();
        return $row ? (float) $row['rate'] : null;
    }

    /** Enregistre un nouveau taux (remplace l'ancien de fait, l'historique est conservé). */
    public function setRate(float $rate, int $moderatorId): int
    {
        return $this->create([
            'rate'   => round($rate, 4),
            'set_by' => $moderatorId,
        ]);
    }

    /** Retourne l'historique des taux avec le nom du modérateur. */
    public function getHistory(int $limit = 20): array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT sr.*, u.username AS set_by_username
             FROM `savings_rates` sr
             LEFT JOIN `users` u ON u.id = sr.set_by
             ORDER BY sr.created_at DESC, sr.id DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
