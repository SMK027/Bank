<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class ExpenseSplit extends Model
{
    protected string $table = 'expense_splits';

    // ── Création ─────────────────────────────────────────────────────────────

    /**
     * Crée une répartition de dépense et ses participants.
     *
     * @param int   $transactionId   ID de la transaction à répartir
     * @param int   $accountId       Compte sur lequel la transaction a été passée
     * @param int   $createdBy       Utilisateur qui initie la répartition
     * @param float $totalAmount     Montant de la transaction
     * @param array $participants    Tableau de [['user_id' => int, 'amount' => float, 'payment_request_id' => ?int], ...]
     * @return int  ID de la répartition créée
     */
    public function createSplit(
        int   $transactionId,
        int   $accountId,
        int   $createdBy,
        float $totalAmount,
        array $participants
    ): int {
        $pdo = $this->getPdo();
        $pdo->beginTransaction();

        try {
            $splitId = $this->create([
                'transaction_id' => $transactionId,
                'account_id'     => $accountId,
                'created_by'     => $createdBy,
                'total_amount'   => $totalAmount,
            ]);

            $stmt = $pdo->prepare(
                "INSERT INTO `expense_split_participants`
                    (`split_id`, `user_id`, `amount`, `payment_request_id`)
                 VALUES (?, ?, ?, ?)"
            );

            foreach ($participants as $p) {
                $stmt->execute([
                    $splitId,
                    (int) $p['user_id'],
                    (float) $p['amount'],
                    isset($p['payment_request_id']) ? (int) $p['payment_request_id'] : null,
                ]);
            }

            $pdo->commit();
            return $splitId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ── Lecture ──────────────────────────────────────────────────────────────

    /**
     * Retourne la répartition active pour une transaction donnée, avec ses participants.
     * Retourne null si aucune répartition n'existe.
     */
    public function getForTransaction(int $transactionId): ?array
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT es.*
             FROM `expense_splits` es
             WHERE es.transaction_id = ?
             ORDER BY es.created_at DESC
             LIMIT 1"
        );
        $stmt->execute([$transactionId]);
        $split = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$split) {
            return null;
        }

        // Récupérer les participants
        $stmtP = $this->getPdo()->prepare(
            "SELECT esp.*, u.username,
                    pr.status AS pr_status,
                    pr.amount AS pr_amount
             FROM `expense_split_participants` esp
             JOIN `users` u ON u.id = esp.user_id
             LEFT JOIN `payment_requests` pr ON pr.id = esp.payment_request_id
             WHERE esp.split_id = ?
             ORDER BY esp.amount DESC"
        );
        $stmtP->execute([(int) $split['id']]);
        $split['participants'] = $stmtP->fetchAll(\PDO::FETCH_ASSOC);

        return $split;
    }

    /**
     * Retourne toutes les répartitions créées par ou impliquant un utilisateur.
     */
    public function getForUser(int $userId): array
    {
        $sql = "SELECT es.*,
                       t.comment   AS tx_comment,
                       t.category  AS tx_category,
                       t.amount    AS tx_amount,
                       a.name      AS account_name,
                       a.currency  AS currency
                FROM `expense_splits` es
                JOIN `transactions` t ON t.id = es.transaction_id
                JOIN `accounts`     a ON a.id = es.account_id
                WHERE es.created_by = :uid
                   OR EXISTS (
                       SELECT 1 FROM `expense_split_participants` esp
                       WHERE esp.split_id = es.id AND esp.user_id = :uid2
                   )
                ORDER BY es.created_at DESC";

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([':uid' => $userId, ':uid2' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Vérifie qu'une transaction n'a pas encore été répartie.
     */
    public function existsForTransaction(int $transactionId): bool
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT 1 FROM `expense_splits` WHERE transaction_id = ? LIMIT 1"
        );
        $stmt->execute([$transactionId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Retourne la liste des transaction_id déjà répartis parmi un ensemble d'IDs.
     * Utilisé pour afficher l'indicateur dans la liste des opérations.
     *
     * @param int[] $transactionIds
     * @return int[]
     */
    public function getSplitTransactionIds(array $transactionIds): array
    {
        if (empty($transactionIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($transactionIds), '?'));
        $stmt = $this->getPdo()->prepare(
            "SELECT DISTINCT transaction_id FROM `expense_splits` WHERE transaction_id IN ({$placeholders})"
        );
        $stmt->execute(array_values($transactionIds));
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }
}
