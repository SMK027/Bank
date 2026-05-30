<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

/**
 * Chèque émis depuis un chéquier.
 *
 * Cycle de vie :
 *   emitted  → cashed   (confirmé par l'utilisateur)
 *   emitted  → opposed  (mis en opposition avant encaissement)
 *
 * Lorsqu'un chèque est émis, une transaction est créée avec
 * scheduled_at = '2099-01-01 00:00:00' (sentinelle "à venir").
 * La confirmation (cashed) matérialise la transaction en nullifiant scheduled_at.
 */
class Check extends Model
{
    protected string $table = 'checks';

    public const STATUS_EMITTED  = 'emitted';
    public const STATUS_CASHED   = 'cashed';
    public const STATUS_OPPOSED  = 'opposed';

    /**
     * Valeur sentinelle indiquant une transaction en attente de chèque.
     * Suffisamment lointaine pour ne jamais être traitée par le cron.
     */
    public const PENDING_SCHEDULED_AT = '2099-01-01 00:00:00';

    /**
     * Retourne tous les chèques d'un chéquier, triés par numéro décroissant.
     */
    public function getByCheckbook(int $checkbookId): array
    {
        return $this->findBy(['checkbook_id' => (string) $checkbookId], 'check_number', 'DESC');
    }

    /**
     * Retourne les chèques émis (en attente) d'un compte donné,
     * en joignant les informations du chéquier.
     */
    public function getEmittedByAccount(int $accountId): array
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT c.*, cb.label AS checkbook_label, cb.account_id
             FROM `checks` c
             JOIN `checkbooks` cb ON cb.id = c.checkbook_id
             WHERE cb.account_id = ? AND c.status = 'emitted'
             ORDER BY c.created_at DESC"
        );
        $stmt->execute([$accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Prochain numéro de chèque pour un chéquier donné (auto-incrémenté).
     */
    public function getNextCheckNumber(int $checkbookId): int
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT COALESCE(MAX(check_number), 0) + 1 FROM `checks` WHERE checkbook_id = ?"
        );
        $stmt->execute([$checkbookId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Émet un chèque et retourne son id.
     */
    public function emit(int $checkbookId, float $amount, string $payee, int $transactionId): int
    {
        $nextNum = $this->getNextCheckNumber($checkbookId);
        return $this->create([
            'checkbook_id'   => $checkbookId,
            'check_number'   => $nextNum,
            'transaction_id' => $transactionId,
            'amount'         => $amount,
            'payee'          => $payee,
            'status'         => self::STATUS_EMITTED,
        ]);
    }

    /**
     * Confirme l'encaissement d'un chèque et matérialise la transaction associée.
     * Retourne false si le chèque est introuvable, déjà encaissé ou en opposition.
     */
    public function confirmCash(int $checkId): bool
    {
        $check = $this->find($checkId);
        if (!$check || $check['status'] !== self::STATUS_EMITTED) {
            return false;
        }

        $pdo = $this->getPdo();
        $pdo->beginTransaction();

        try {
            // Matérialiser la transaction (scheduled_at → NULL = exécutée)
            if (!empty($check['transaction_id'])) {
                $stmt = $pdo->prepare(
                    "UPDATE `transactions`
                     SET scheduled_at = NULL, updated_at = NOW()
                     WHERE id = ? AND scheduled_at = ?"
                );
                $stmt->execute([(int) $check['transaction_id'], self::PENDING_SCHEDULED_AT]);
            }

            // Passer le chèque en 'cashed'
            $stmt = $pdo->prepare(
                "UPDATE `checks` SET status = 'cashed', updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$checkId]);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }

    /**
     * Met en opposition un chèque individuel.
     * Si une transaction en attente est liée, elle est annulée (supprimée).
     */
    public function oppose(int $checkId): bool
    {
        $check = $this->find($checkId);
        if (!$check || $check['status'] !== self::STATUS_EMITTED) {
            return false;
        }

        $pdo = $this->getPdo();
        $pdo->beginTransaction();

        try {
            // Supprimer la transaction en attente si elle existe
            if (!empty($check['transaction_id'])) {
                $stmt = $pdo->prepare(
                    "DELETE FROM `transactions`
                     WHERE id = ? AND scheduled_at = ?"
                );
                $stmt->execute([(int) $check['transaction_id'], self::PENDING_SCHEDULED_AT]);
            }

            // Passer le chèque en 'opposed'
            $stmt = $pdo->prepare(
                "UPDATE `checks` SET status = 'opposed', updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$checkId]);

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }
}
