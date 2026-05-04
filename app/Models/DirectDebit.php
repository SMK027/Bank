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
        int     $createdBy     = 0,
        int     $retryCount    = 0
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
            'retry_count'     => $retryCount,
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
     * Retourne tous les prélèvements planifiés (status = scheduled) liés au compte
     * qu'il soit débité (to_account_id) ou crédité (from_account_id).
     */
    public function getUpcomingByAccount(int $accountId): array
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT * FROM `{$this->table}`
             WHERE status = :status
               AND (to_account_id = :acc OR from_account_id = :acc)
             ORDER BY scheduled_at ASC"
        );
        $stmt->execute(['status' => self::STATUS_SCHEDULED, 'acc' => $accountId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
     * Conditions : statut success + executed_at renseigné.
     * Le délai de 48 h n'est plus une contrainte de rejet, mais conditionne
     * les exigences (motif + mot de passe) imposées au modérateur.
     */
    public function canReject(array $directDebit): bool
    {
        if (($directDebit['status'] ?? '') !== self::STATUS_SUCCESS) {
            return false;
        }
        return !empty($directDebit['executed_at']);
    }

    /**
     * Indique si le rejet est dans la fenêtre des 48 h après exécution
     * (motif et mot de passe requis dans ce cas).
     */
    public function isWithin48hOfExecution(array $directDebit): bool
    {
        if (empty($directDebit['executed_at'])) {
            return false;
        }
        $age = time() - strtotime($directDebit['executed_at']);
        return $age >= 0 && $age < 48 * 3600;
    }

    /**
     * Marque le prélèvement comme rejeté (après exécution — transactions inversées par le contrôleur).
     */
    public function markRejected(int $id, ?string $reason = null): bool
    {
        $data = ['status' => self::STATUS_REJECTED];
        if ($reason !== null) {
            $data['reject_reason'] = $reason;
        }
        return $this->update($id, $data);
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

    /**
     * Vérifie si un prélèvement rejeté ou échoué peut être réexécuté.
     * Condition : statut failed/rejected ET retry_count = 0 (une seule tentative autorisée).
     */
    public function canRetry(array $directDebit): bool
    {
        if ((int) ($directDebit['retry_count'] ?? 0) !== 0) {
            return false;
        }
        return in_array($directDebit['status'] ?? '', [self::STATUS_REJECTED, self::STATUS_FAILED], true);
    }

    /**
     * Crée un nouveau prélèvement planifié à partir d'un prélèvement rejeté/échoué.
     * Marque l'original comme déjà réexécuté (retry_count = 1) et crée le
     * nouveau prélèvement avec retry_count = 1 (lui-même non réexécutable).
     */
    public function retry(int $id, ?string $scheduledAt = null): ?int
    {
        $original = $this->find($id);
        if (!$original || !$this->canRetry($original)) {
            return null;
        }

        // Verrouiller l'original : ne peut plus être réexécuté
        $this->update($id, ['retry_count' => 1]);

        return $this->createDirectDebit(
            $original['mandate_number'],
            $scheduledAt ?? date('Y-m-d H:i:s'),
            (float) $original['amount'],
            (int) $original['to_account_id'],
            $original['from_account_id'] !== null ? (int) $original['from_account_id'] : null,
            $original['motif'],
            (int) $original['created_by'],
            1 // retry_count = 1 : ce nouveau prélèvement n'est lui-même pas réexécutable
        );
    }

    /**
     * Modifie la date d'exécution planifiée d'un prélèvement en statut 'scheduled'.
     */
    public function reschedule(int $id, string $scheduledAt): bool
    {
        return $this->update($id, ['scheduled_at' => $scheduledAt]);
    }
}
