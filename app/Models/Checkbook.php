<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Chéquier associé à un compte bancaire.
 * Un chéquier est immuable une fois créé (account_id non modifiable).
 */
class Checkbook extends Model
{
    protected string $table = 'checkbooks';

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_OPPOSED = 'opposed';

    /**
     * Retourne tous les chéquiers d'un utilisateur (toutes statuts).
     */
    public function getByUser(int $userId): array
    {
        return $this->findBy(['user_id' => (string) $userId], 'created_at', 'DESC');
    }

    /**
     * Retourne les chéquiers d'un compte donné.
     */
    public function getByAccount(int $accountId): array
    {
        return $this->findBy(['account_id' => (string) $accountId], 'created_at', 'DESC');
    }

    /**
     * Retourne uniquement les chéquiers actifs d'un compte.
     */
    public function getActiveByAccount(int $accountId): array
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT * FROM `{$this->table}`
             WHERE account_id = ? AND status = 'active'
             ORDER BY created_at DESC"
        );
        $stmt->execute([$accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Met en opposition un chéquier et tous ses chèques non encore encaissés.
     */
    public function oppose(int $checkbookId): bool
    {
        $pdo = $this->getPdo();

        // Mettre en opposition les chèques 'emitted' du chéquier
        $stmt = $pdo->prepare(
            "UPDATE `checks`
             SET status = 'opposed', updated_at = NOW()
             WHERE checkbook_id = ? AND status = 'emitted'"
        );
        $stmt->execute([$checkbookId]);

        // Mettre en opposition le chéquier
        return $this->update($checkbookId, [
            'status'     => self::STATUS_OPPOSED,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Vérifie que le type de compte autorise l'émission de chèques.
     * On exclut les comptes épargne (livret, PEL…) car ils ne peuvent
     * pas être associés à un chéquier réglementairement.
     */
    public static function typeAllowsCheckbook(string $accountType): bool
    {
        return !in_array($accountType, ['savings', 'vault', 'event'], true)
            && in_array($accountType, ['standard', 'pro', 'joint', 'online'], true);
    }
}
