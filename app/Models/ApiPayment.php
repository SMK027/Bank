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
}
