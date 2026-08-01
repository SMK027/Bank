<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class Mandate extends Model
{
    protected string $table = 'mandates';

    public const TYPE_ONE_TIME  = 'one_time';
    public const TYPE_RECURRING = 'recurring';

    public const TYPES = [
        self::TYPE_ONE_TIME  => 'Ponctuel',
        self::TYPE_RECURRING => 'Récurrent',
    ];

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_REVOKED  = 'revoked';

    public const STATUSES = [
        self::STATUS_ACTIVE   => 'Actif',
        self::STATUS_EXECUTED => 'Exécuté',
        self::STATUS_REVOKED  => 'Révoqué',
    ];

    /**
     * Crée un nouveau mandat.
     * Quand $emitterAccountId est null, le mandat est émis par la banque :
     * seul le compte destinataire sera débité, aucun compte n'est crédité.
     *
     * Pour les mandats récurrents, deux modes sont disponibles :
     * - $intervalDays  : prélèvement tous les N jours.
     * - $executionDay  : prélèvement chaque mois à un jour fixe (1-28).
     * Les deux options sont mutuellement exclusives ; $executionDay est prioritaire.
     */
    public function createMandate(
        string  $number,
        ?int    $emitterAccountId,
        int     $recipientAccountId,
        string  $description,
        float   $amount,
        string  $type,
        ?int    $intervalDays,
        int     $createdBy,
        ?string $firstExecutionAt = null,
        ?int    $executionDay = null
    ): int {
        $isRecurring  = ($type === self::TYPE_RECURRING);
        $useFixedDay  = $isRecurring && $executionDay !== null && $executionDay >= 1 && $executionDay <= 28;

        // Calcul de la prochaine exécution si aucune date explicite n'est fournie
        if ($firstExecutionAt === null && $useFixedDay) {
            $firstExecutionAt = $this->nextExecutionDateForDay($executionDay);
        } else {
            $firstExecutionAt = $firstExecutionAt ?? date('Y-m-d H:i:s');
        }

        return $this->create([
            'number'               => $number,
            'emitter_account_id'   => $emitterAccountId,
            'recipient_account_id' => $recipientAccountId,
            'description'          => $description,
            'amount'               => $amount,
            'type'                 => $type,
            'interval_days'        => $isRecurring && !$useFixedDay ? $intervalDays : null,
            'execution_day'        => $useFixedDay ? $executionDay : null,
            'status'               => self::STATUS_ACTIVE,
            'created_by'           => $createdBy,
            'next_execution_at'    => $firstExecutionAt,
        ]);
    }

    /**
     * Calcule la prochaine date d'exécution pour un jour fixe du mois.
     * Si le jour est encore à venir ce mois-ci, retourne cette date.
     * Sinon, retourne le même jour du mois suivant.
     * Limité au 28 pour garantir la validité en février.
     */
    private function nextExecutionDateForDay(int $day): string
    {
        $today = new \DateTime('today');
        $year  = (int) $today->format('Y');
        $month = (int) $today->format('n');

        $candidate = (clone $today)->setDate($year, $month, $day);
        if ($candidate < $today) {
            // Aller au mois suivant
            $candidate->modify('first day of next month');
            $candidate->setDate((int) $candidate->format('Y'), (int) $candidate->format('n'), $day);
        }

        return $candidate->format('Y-m-d') . ' 00:00:00';
    }

    /**
     * Retourne tous les mandats avec les noms de comptes, triés du plus récent au plus ancien.
     */
    public function getAllWithAccounts(): array
    {
        $sql = 'SELECT m.*,
                       ea.name AS emitter_name,
                       eu.username AS emitter_owner,
                       ra.name AS recipient_name,
                       ru.username AS recipient_owner
                  FROM mandates m
             LEFT JOIN accounts ea ON ea.id = m.emitter_account_id
             LEFT JOIN users    eu ON eu.id = ea.user_id
                  JOIN accounts ra ON ra.id = m.recipient_account_id
                  JOIN users    ru ON ru.id = ra.user_id
                 ORDER BY m.created_at DESC';
        $stmt = $this->getPdo()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retourne les mandats rattachés à un compte (émetteur OU destinataire).
     */
    public function getByAccount(int $accountId): array
    {
        $sql = 'SELECT m.*,
                       ea.name AS emitter_name,
                       eu.username AS emitter_owner,
                       ra.name AS recipient_name,
                       ru.username AS recipient_owner
                  FROM mandates m
             LEFT JOIN accounts ea ON ea.id = m.emitter_account_id
             LEFT JOIN users    eu ON eu.id = ea.user_id
                  JOIN accounts ra ON ra.id = m.recipient_account_id
                  JOIN users    ru ON ru.id = ra.user_id
                 WHERE m.emitter_account_id = :id OR m.recipient_account_id = :id2
                 ORDER BY m.created_at DESC';
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([':id' => $accountId, ':id2' => $accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Révoque un mandat.
     */
    public function revoke(int $id): bool
    {
        return $this->update($id, ['status' => self::STATUS_REVOKED]);
    }

    /**
     * Retourne les mandats actifs dont la prochaine exécution est échue.
     */
    public function getDue(): array
    {
        $now     = time();
        $records = $this->findBy(['status' => self::STATUS_ACTIVE], 'next_execution_at', 'ASC');

        return array_values(array_filter($records, function (array $m) use ($now): bool {
            return !empty($m['next_execution_at']) && strtotime($m['next_execution_at']) <= $now;
        }));
    }

    /**
     * Marque un mandat comme exécuté et planifie la prochaine exécution si récurrent.
     */
    public function markExecuted(int $id): bool
    {
        $mandate = $this->find($id);
        if (!$mandate) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        if ($mandate['type'] === self::TYPE_ONE_TIME) {
            return $this->update($id, [
                'last_executed_at'  => $now,
                'next_execution_at' => null,
                'status'            => self::STATUS_EXECUTED,
            ]);
        }

        // Récurrent : planifier la prochaine exécution
        $executionDay = isset($mandate['execution_day']) && $mandate['execution_day'] !== null
            ? (int) $mandate['execution_day']
            : null;

        if ($executionDay !== null) {
            // Mode jour fixe : toujours le même jour du mois suivant
            $next = new \DateTime('first day of next month');
            $next->setDate((int) $next->format('Y'), (int) $next->format('n'), $executionDay);
            $nextExecution = $next->format('Y-m-d') . ' 00:00:00';
        } else {
            $intervalDays  = (int) ($mandate['interval_days'] ?? 30);
            $nextExecution = date('Y-m-d H:i:s', strtotime("+{$intervalDays} days"));
        }

        return $this->update($id, [
            'last_executed_at'  => $now,
            'next_execution_at' => $nextExecution,
        ]);
    }

    /**
     * Retourne les mandats actifs avec prochaine exécution, rattachés à un compte.
     */
    public function getUpcomingByAccount(int $accountId): array
    {
        $sql = 'SELECT m.*,
                       ea.name AS emitter_name,
                       eu.username AS emitter_owner,
                       ra.name AS recipient_name,
                       ru.username AS recipient_owner
                  FROM mandates m
             LEFT JOIN accounts ea ON ea.id = m.emitter_account_id
             LEFT JOIN users    eu ON eu.id = ea.user_id
                  JOIN accounts ra ON ra.id = m.recipient_account_id
                  JOIN users    ru ON ru.id = ra.user_id
                 WHERE m.status = :status
                   AND m.next_execution_at IS NOT NULL
                   AND (m.emitter_account_id = :id1 OR m.recipient_account_id = :id2)
                 ORDER BY m.next_execution_at ASC';
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute([
            ':status' => self::STATUS_ACTIVE,
            ':id1'    => $accountId,
            ':id2'    => $accountId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour la date de prochaine exécution d'un mandat actif.
     */
    public function updateNextExecution(int $id, string $nextExecutionAt): bool
    {
        return $this->update($id, ['next_execution_at' => $nextExecutionAt]);
    }

    /**
     * Vérifie si un numéro de mandat existe déjà.
     */
    public function numberExists(string $number): bool
    {
        return $this->findOneBy(['number' => $number]) !== null;
    }
}
