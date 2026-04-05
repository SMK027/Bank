<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle Prélèvement (direct debit).
 *
 * Champs :
 *   id              int          Identifiant unique
 *   mandate_number  string       Numéro de mandat
 *   scheduled_at    string       Date/heure d'exécution planifiée (Y-m-d H:i:s)
 *   executed_at     string|null  Date/heure d'exécution effective
 *   amount          float        Montant
 *   motif           string|null  Libellé facultatif
 *   from_account_id int|null     Compte émetteur (NULL = la banque, pas de crédit)
 *   to_account_id   int          Compte destinataire (débité)
 *   debit_tx_id     int|null     ID de la transaction de débit
 *   credit_tx_id    int|null     ID de la transaction de crédit (NULL si from_account_id NULL)
 *   status          string       scheduled|success|failed|cancelled
 *   created_by      int          ID du modérateur ayant créé le prélèvement
 *   created_at      string       (auto)
 *   updated_at      string       (auto)
 */
class DirectDebit extends Model
{
    protected string $table = 'direct_debits';

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SUCCESS   = 'success';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED  = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_SCHEDULED => 'Planifié',
        self::STATUS_SUCCESS   => 'Exécuté',
        self::STATUS_FAILED    => 'Échoué',
        self::STATUS_CANCELLED => 'Annulé',
        self::STATUS_REJECTED  => 'Rejeté',
    ];

    /**
     * Crée un prélèvement en base (statut = scheduled).
     */
    public function createDirectDebit(
        string  $mandateNumber,
        string  $scheduledAt,
        float   $amount,
        int     $toAccountId,
        ?int    $fromAccountId = null,
        ?string $motif         = null,
        int     $createdBy     = 0
    ): int {
        return $this->create([
            'mandate_number'  => $mandateNumber,
            'scheduled_at'    => $scheduledAt,
            'executed_at'     => null,
            'amount'          => $amount,
            'motif'           => $motif,
            'from_account_id' => $fromAccountId,
            'to_account_id'   => $toAccountId,
            'debit_tx_id'     => null,
            'credit_tx_id'    => null,
            'status'          => self::STATUS_SCHEDULED,
            'created_by'      => $createdBy,
        ]);
    }

    /**
     * Retourne les prélèvements planifiés dont la date est échue.
     */
    public function getDue(): array
    {
        $now     = time();
        $records = $this->findBy(['status' => self::STATUS_SCHEDULED], 'scheduled_at', 'ASC');

        return array_values(array_filter($records, function (array $d) use ($now): bool {
            return !empty($d['scheduled_at']) && strtotime($d['scheduled_at']) <= $now;
        }));
    }

    /**
     * Marque le prélèvement comme exécuté avec les IDs de transaction.
     */
    public function markSuccess(int $id, int $debitTxId, ?int $creditTxId): bool
    {
        return $this->update($id, [
            'status'       => self::STATUS_SUCCESS,
            'executed_at'  => date('Y-m-d H:i:s'),
            'debit_tx_id'  => $debitTxId,
            'credit_tx_id' => $creditTxId,
        ]);
    }

    /**
     * Marque le prélèvement comme échoué.
     */
    public function markFailed(int $id): bool
    {
        return $this->update($id, [
            'status'      => self::STATUS_FAILED,
            'executed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Marque le prélèvement comme annulé.
     */
    public function markCancelled(int $id): bool
    {
        return $this->update($id, ['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Vérifie si un prélèvement peut être annulé (statut scheduled uniquement).
     */
    public function canCancel(array $directDebit): bool
    {
        return ($directDebit['status'] ?? '') === self::STATUS_SCHEDULED;
    }

    /**
     * Vérifie si un prélèvement peut être rejeté.
     * Conditions : statut success + exécuté depuis plus de 48 h et moins de 2 semaines.
     */
    public function canReject(array $directDebit): bool
    {
        if (($directDebit['status'] ?? '') !== self::STATUS_SUCCESS) {
            return false;
        }
        if (empty($directDebit['executed_at'])) {
            return false;
        }
        $age = time() - strtotime($directDebit['executed_at']);
        return $age >= 48 * 3600 && $age <= 14 * 24 * 3600;
    }

    /**
     * Marque le prélèvement comme rejeté (après exécution — transactions inversées par le contrôleur).
     */
    public function markRejected(int $id): bool
    {
        return $this->update($id, ['status' => self::STATUS_REJECTED]);
    }

    /**
     * Rejette automatiquement un prélèvement avant toute exécution
     * (compte sans découvert, solde insuffisant).
     * Enregistre executed_at pour traçabilité.
     */
    public function markAutoRejected(int $id): bool
    {
        return $this->update($id, [
            'status'      => self::STATUS_REJECTED,
            'executed_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
