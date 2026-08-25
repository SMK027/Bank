<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class EventSchedule extends Model
{
    protected string $table = 'event_schedules';

    public function createEvent(string $title, string $startAt, string $endAt, int $createdBy): int
    {
        return $this->create([
            'title'      => mb_substr(trim($title), 0, 180),
            'start_at'   => $startAt,
            'end_at'     => $endAt,
            'created_by' => $createdBy,
        ]);
    }

    public function findActiveAt(?string $at = null): ?array
    {
        $at = $at ?? date('Y-m-d H:i:s');

        $stmt = $this->getPdo()->prepare(
            "SELECT *
             FROM {$this->table}
             WHERE start_at <= ? AND end_at >= ?
             ORDER BY end_at ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$at, $at]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findUpcoming(int $limit = 50): array
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT *
             FROM {$this->table}
             ORDER BY start_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function hasOverlap(string $startAt, string $endAt, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*)
             FROM {$this->table}
             WHERE start_at <= ? AND end_at >= ?";
        $params = [$endAt, $startAt];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function updateEvent(int $id, string $title, string $startAt, string $endAt): bool
    {
        $existing = $this->find($id);
        if (!$existing) {
            return false;
        }

        return $this->update($id, [
            'title'    => mb_substr(trim($title), 0, 180),
            'start_at' => $startAt,
            'end_at'   => $endAt,
        ]);
    }
}
