<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Gestion des budgets mensuels par catégorie de dépense.
 * Un budget est un plafond indicatif par catégorie et par utilisateur.
 * Il n'est pas lié à un mois spécifique : le même plafond s'applique
 * chaque mois et est comparé aux dépenses réelles du mois sélectionné.
 */
class Budget extends Model
{
    protected string $table = 'budgets';

    /**
     * Retourne les budgets de l'utilisateur sous la forme [category => monthly_limit].
     */
    public function getForUser(int $userId): array
    {
        $rows   = $this->findBy(['user_id' => (string) $userId]);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['category']] = (float) $row['monthly_limit'];
        }
        return $result;
    }

    /**
     * Crée ou met à jour le budget mensuel d'une catégorie pour un utilisateur.
     */
    public function upsert(int $userId, string $category, float $monthlyLimit): void
    {
        $sql = "INSERT INTO `{$this->table}` (user_id, category, monthly_limit)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    monthly_limit = VALUES(monthly_limit),
                    updated_at    = CURRENT_TIMESTAMP";
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([$userId, $category, $monthlyLimit]);
    }

    /**
     * Supprime le budget d'une catégorie pour un utilisateur.
     */
    public function deleteForCategory(int $userId, string $category): void
    {
        $stmt = $this->getPdo()->prepare(
            "DELETE FROM `{$this->table}` WHERE user_id = ? AND category = ?"
        );
        $stmt->execute([$userId, $category]);
    }
}
