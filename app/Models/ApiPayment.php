<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Journal des paiements API/TPE.
 *
 * Une ligne par opération (succès comme échec). Pour les opérations
 * réussies, les colonnes `transaction_id` (débit côté client),
 * `credit_transaction_id` (crédit côté commerçant) et
 * `deferred_debit_id` (si le débit a été enregistré en différé)
 * permettent de retracer l'opération et de l'annuler si nécessaire.
 */
class ApiPayment extends Model
{
    protected string $table = 'api_payments';

    /** La table api_payments n'a ni created_at auto-géré ni updated_at. */
    protected bool $hasUpdatedAt = false;

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED  = 'failed';

    /** Liste les paiements récents (option : uniquement TPE = client api_key 'pos_internal'). */
    public function getRecent(int $limit = 100, ?int $apiClientId = null): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];
        if ($apiClientId !== null) {
            $sql      .= ' WHERE api_client_id = :cid';
            $params['cid'] = $apiClientId;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT :lim';

        $stmt = $this->getPdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Recherche/filtrage des paiements TPE pour la modération.
     *
     * Filtres (tous facultatifs) :
     *  - api_client_id : restreindre à un client API (TPE interne en pratique)
     *  - client        : recherche dans le nom du titulaire OU du compte client
     *                    OU id du compte (préfixé `#`)
     *  - merchant      : idem côté commerçant (via credit_transaction_id)
     *  - amount_min / amount_max : bornes de montant (incluses)
     *  - date_from / date_to     : bornes de `created_at` (au format Y-m-d, incluses)
     *  - status        : 'success' | 'failed' | 'cancelled' | 'deferred'
     *
     * Retourne les colonnes du paiement enrichies de :
     *  client_name, client_account_name, merchant_name, merchant_account_name.
     */
    public function searchForModeration(array $filters = [], int $limit = 200): array
    {
        $sql = "SELECT p.*,
                       cu.username       AS client_name,
                       ca.name           AS client_account_name,
                       mu.username       AS merchant_name,
                       ma.name           AS merchant_account_name,
                       ma.id             AS merchant_account_id
                FROM `{$this->table}` p
                LEFT JOIN `accounts`     ca ON ca.id = p.account_id
                LEFT JOIN `users`        cu ON cu.id = ca.user_id
                LEFT JOIN `transactions` ct ON ct.id = p.credit_transaction_id
                LEFT JOIN `accounts`     ma ON ma.id = ct.account_id
                LEFT JOIN `users`        mu ON mu.id = ma.user_id
                WHERE 1=1";

        $params = [];

        if (!empty($filters['api_client_id'])) {
            $sql .= ' AND p.api_client_id = :cid';
            $params[':cid'] = (int) $filters['api_client_id'];
        }

        if (!empty($filters['client'])) {
            $client = trim((string) $filters['client']);
            // Reconnaît "#42" comme un id de compte client
            if (preg_match('/^#?(\d+)$/', $client, $m)) {
                $sql .= ' AND p.account_id = :clientId';
                $params[':clientId'] = (int) $m[1];
            } else {
                $sql .= ' AND (cu.username LIKE :clientLk1 OR ca.name LIKE :clientLk2)';
                $params[':clientLk1'] = '%' . $client . '%';
                $params[':clientLk2'] = '%' . $client . '%';
            }
        }

        if (!empty($filters['merchant'])) {
            $merchant = trim((string) $filters['merchant']);
            if (preg_match('/^#?(\d+)$/', $merchant, $m)) {
                $sql .= ' AND ma.id = :merchantId';
                $params[':merchantId'] = (int) $m[1];
            } else {
                $sql .= ' AND (mu.username LIKE :merchantLk1 OR ma.name LIKE :merchantLk2)';
                $params[':merchantLk1'] = '%' . $merchant . '%';
                $params[':merchantLk2'] = '%' . $merchant . '%';
            }
        }

        if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
            $sql .= ' AND p.amount >= :amin';
            $params[':amin'] = (float) $filters['amount_min'];
        }
        if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
            $sql .= ' AND p.amount <= :amax';
            $params[':amax'] = (float) $filters['amount_max'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= ' AND p.created_at >= :dfrom';
            $params[':dfrom'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND p.created_at <= :dto';
            $params[':dto'] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'cancelled':
                    $sql .= ' AND p.cancelled_at IS NOT NULL';
                    break;
                case 'deferred':
                    $sql .= " AND p.status = 'success' AND p.cancelled_at IS NULL AND p.deferred_debit_id IS NOT NULL";
                    break;
                case 'success':
                    $sql .= " AND p.status = 'success' AND p.cancelled_at IS NULL";
                    break;
                case 'failed':
                    $sql .= " AND p.status = 'failed'";
                    break;
            }
        }

        $sql .= ' ORDER BY p.created_at DESC, p.id DESC LIMIT :lim';

        $stmt = $this->getPdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $type = is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function isCancelled(array $payment): bool
    {
        return !empty($payment['cancelled_at']);
    }

    public function markCancelled(int $id, ?int $moderatorId, string $reason = ''): bool
    {
        return $this->update($id, [
            'cancelled_at'  => date('Y-m-d H:i:s'),
            'cancelled_by'  => $moderatorId,
            'cancel_reason' => mb_substr($reason, 0, 255),
        ]);
    }

    // ─── Remboursements partiels ──────────────────────────────

    /**
     * Retourne la somme déjà remboursée pour un paiement.
     */
    public function getTotalRefunded(int $paymentId): float
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM api_payment_refunds WHERE payment_id = ?'
        );
        $stmt->execute([$paymentId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Retourne [payment_id => total_refunded] pour une liste de paiement_ids.
     * Évite N+1 requêtes dans moderationIndex().
     */
    public function getRefundsMap(array $paymentIds): array
    {
        if (empty($paymentIds)) {
            return [];
        }
        $ph   = implode(',', array_fill(0, count($paymentIds), '?'));
        $stmt = $this->getPdo()->prepare(
            "SELECT payment_id, COALESCE(SUM(amount), 0) AS total
               FROM api_payment_refunds
              WHERE payment_id IN ($ph)
              GROUP BY payment_id"
        );
        $stmt->execute(array_values($paymentIds));
        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['payment_id']] = (float) $row['total'];
        }
        return $map;
    }

    /**
     * Retourne les remboursements d'un paiement, triés du plus récent au plus ancien.
     */
    public function getRefundsForPayment(int $paymentId): array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT r.*, u.username AS refunded_by_name
               FROM api_payment_refunds r
               LEFT JOIN users u ON u.id = r.refunded_by
              WHERE r.payment_id = ?
              ORDER BY r.refunded_at DESC'
        );
        $stmt->execute([$paymentId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Enregistre un remboursement partiel et retourne son id.
     */
    public function addRefund(
        int     $paymentId,
        float   $amount,
        int     $moderatorId,
        string  $reason,
        ?int    $customerTxId,
        ?int    $merchantTxId
    ): int {
        $pdo  = $this->getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO api_payment_refunds
               (payment_id, amount, reason, refunded_by, refunded_at, customer_tx_id, merchant_tx_id)
             VALUES (?, ?, ?, ?, NOW(), ?, ?)'
        );
        $stmt->execute([
            $paymentId,
            $amount,
            mb_substr($reason, 0, 255),
            $moderatorId,
            $customerTxId,
            $merchantTxId,
        ]);
        return (int) $pdo->lastInsertId();
    }
}
