<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Modèle Virement récurrent.
 *
 * Chaque instance représente un modèle de virement répété automatiquement
 * toutes les `interval_days` jours. Chaque occurrence concrète crée un
 * enregistrement dans la table `transfers`.
 */
class RecurringTransfer extends Model
{
    protected string $table = 'recurring_transfers';

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Crée un virement récurrent.
     */
    public function createRecurringTransfer(
        int    $fromAccountId,
        int    $toAccountId,
        int    $userId,
        float  $amount,
        string $motif,
        int    $intervalDays,
        string $firstExecutionAt
    ): int {
        return $this->create([
            'from_account_id'   => $fromAccountId,
            'to_account_id'     => $toAccountId,
            'user_id'           => $userId,
            'amount'            => $amount,
            'motif'             => $motif,
            'status'            => self::STATUS_ACTIVE,
            'interval_days'     => $intervalDays,
            'next_execution_at' => $firstExecutionAt,
        ]);
    }

    /**
     * Retourne les virements récurrents actifs dont la prochaine exécution est échue.
     */
    public function getDue(): array
    {
        $now     = time();
        $records = $this->findBy(['status' => self::STATUS_ACTIVE], 'next_execution_at', 'ASC');

        return array_values(array_filter($records, function (array $r) use ($now): bool {
            return !empty($r['next_execution_at']) && strtotime($r['next_execution_at']) <= $now;
        }));
    }

    /**
     * Marque une occurrence comme exécutée et planifie la prochaine.
     */
    public function markExecuted(int $id): bool
    {
        $record = $this->find($id);
        if (!$record) {
            return false;
        }

        $intervalDays = (int) ($record['interval_days'] ?? 1);
        $last         = $record['next_execution_at'] ?? date('Y-m-d H:i:s');
        $next         = date('Y-m-d H:i:s', strtotime($last) + $intervalDays * 86400);

        return $this->update($id, [
            'last_executed_at'  => date('Y-m-d H:i:s'),
            'next_execution_at' => $next,
        ]);
    }

    /**
     * Annule un virement récurrent.
     */
    public function cancel(int $id): bool
    {
        return $this->update($id, ['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Retourne les virements récurrents liés à un compte (émetteur ou destinataire).
     */
    public function getByAccount(int $accountId): array
    {
        $all = $this->findAll('created_at', 'DESC');
        return array_values(array_filter($all, function (array $r) use ($accountId): bool {
            return (int) $r['from_account_id'] === $accountId
                || (int) $r['to_account_id']   === $accountId;
        }));
    }

    /**
     * Retourne les virements récurrents initiés par un utilisateur.
     */
    public function getByUser(int $userId): array
    {
        return $this->findBy(['user_id' => (string) $userId], 'created_at', 'DESC');
    }

    /**
     * Indique si un utilisateur peut annuler ce virement récurrent
     * (propriétaire ou modérateur = vérification faite dans le contrôleur).
     */
    public function canCancel(array $recurringTransfer): bool
    {
        return ($recurringTransfer['status'] ?? '') === self::STATUS_ACTIVE;
    }

    public function updateNextExecution(int $id, string $nextExecutionAt): bool
    {
        return $this->update($id, ['next_execution_at' => $nextExecutionAt]);
    }
}
