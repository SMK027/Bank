<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle Virement.
 *
 * Champs stockés dans transfers.json :
 *   id              int      Identifiant unique
 *   from_account_id int      Compte émetteur
 *   to_account_id   int      Compte destinataire
 *   user_id         int      Initiateur du virement
 *   amount          float    Montant
 *   motif           string   Libellé optionnel
 *   status          string   scheduled|success|failed|cancelled
 *   scheduled_at    string|null  Date d'exécution planifiée (Y-m-d H:i:s)
 *   executed_at     string|null  Date d'exécution effective
 *   debit_tx_id     int      ID de la transaction débit (compte émetteur)
 *   credit_tx_id    int      ID de la transaction crédit (compte destinataire)
 *   created_at      string   (auto)
 *   updated_at      string   (auto)
 */
class Transfer extends Model
{
    protected string $table = 'transfers';

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SUCCESS   = 'success';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_SCHEDULED => 'Planifié',
        self::STATUS_SUCCESS   => 'Réussi',
        self::STATUS_FAILED    => 'Échoué',
        self::STATUS_CANCELLED => 'Annulé',
    ];

    /**
     * Crée un enregistrement de virement.
     */
    public function createTransfer(
        int     $fromAccountId,
        int     $toAccountId,
        int     $userId,
        float   $amount,
        string  $motif        = '',
        ?string $scheduledAt  = null,
        int     $debitTxId    = 0,
        int     $creditTxId   = 0
    ): int {
        $status = $scheduledAt !== null ? self::STATUS_SCHEDULED : self::STATUS_SUCCESS;

        return $this->create([
            'from_account_id' => $fromAccountId,
            'to_account_id'   => $toAccountId,
            'user_id'         => $userId,
            'amount'          => $amount,
            'motif'           => $motif,
            'status'          => $status,
            'scheduled_at'    => $scheduledAt,
            'executed_at'     => $scheduledAt === null ? date('Y-m-d H:i:s') : null,
            'debit_tx_id'     => $debitTxId,
            'credit_tx_id'    => $creditTxId,
        ]);
    }

    /**
     * Retourne les virements planifiés dont la date d'exécution est échue.
     */
    public function getDueScheduled(): array
    {
        $now     = time();
        $records = $this->findBy(['status' => self::STATUS_SCHEDULED], 'scheduled_at', 'ASC');

        return array_values(array_filter($records, function (array $t) use ($now): bool {
            return !empty($t['scheduled_at']) && strtotime($t['scheduled_at']) <= $now;
        }));
    }

    /**
     * Marque un virement comme réussi.
     */
    public function markSuccess(int $id): bool
    {
        return $this->update($id, [
            'status'      => self::STATUS_SUCCESS,
            'executed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Marque un virement comme échoué.
     */
    public function markFailed(int $id): bool
    {
        return $this->update($id, ['status' => self::STATUS_FAILED]);
    }

    /**
     * Retourne les virements d'un compte (émetteur ou destinataire).
     */
    public function getByAccount(int $accountId): array
    {
        $all = $this->findAll('created_at', 'DESC');
        return array_values(array_filter($all, function (array $t) use ($accountId): bool {
            return (int) $t['from_account_id'] === $accountId
                || (int) $t['to_account_id']   === $accountId;
        }));
    }

    /**
     * Retourne les virements initiés par un utilisateur.
     */
    public function getByUser(int $userId): array
    {
        return $this->findBy(['user_id' => (string) $userId], 'created_at', 'DESC');
    }
}
