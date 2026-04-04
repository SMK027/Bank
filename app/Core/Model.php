<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Modèle abstrait de base avec opérations CRUD sur fichiers JSON.
 * Chaque modèle concret doit définir la propriété $file (nom du fichier JSON).
 */
abstract class Model
{
    protected string $file = '';
    protected string $dataDir;

    public function __construct(?string $dataDir = null)
    {
        $this->dataDir = $dataDir ?? dirname(__DIR__, 2) . '/data';
        $this->ensureDataDir();
    }

    private function ensureDataDir(): void
    {
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }

    protected function getFilePath(): string
    {
        return $this->dataDir . '/' . $this->file;
    }

    protected function readAll(): array
    {
        $path = $this->getFilePath();
        if (!file_exists($path)) {
            return [];
        }
        $content = file_get_contents($path);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    protected function writeAll(array $data): void
    {
        $path = $this->getFilePath();
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private function getNextId(array $records): int
    {
        if (empty($records)) {
            return 1;
        }
        return max(array_column($records, 'id')) + 1;
    }

    public function find(int $id): ?array
    {
        $records = $this->readAll();
        foreach ($records as $record) {
            if ((int) $record['id'] === $id) {
                return $record;
            }
        }
        return null;
    }

    public function findAll(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $records = $this->readAll();
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        usort($records, function ($a, $b) use ($orderBy, $direction) {
            $valA = $a[$orderBy] ?? '';
            $valB = $b[$orderBy] ?? '';
            $cmp = $valA <=> $valB;
            return $direction === 'DESC' ? -$cmp : $cmp;
        });

        return $records;
    }

    public function findBy(array $criteria, string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $records = $this->readAll();
        $filtered = array_filter($records, function ($record) use ($criteria) {
            foreach ($criteria as $key => $value) {
                if (!isset($record[$key]) || (string) $record[$key] !== (string) $value) {
                    return false;
                }
            }
            return true;
        });

        $result = array_values($filtered);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        usort($result, function ($a, $b) use ($orderBy, $direction) {
            $valA = $a[$orderBy] ?? '';
            $valB = $b[$orderBy] ?? '';
            $cmp = $valA <=> $valB;
            return $direction === 'DESC' ? -$cmp : $cmp;
        });

        return $result;
    }

    public function findOneBy(array $criteria): ?array
    {
        $records = $this->readAll();
        foreach ($records as $record) {
            $match = true;
            foreach ($criteria as $key => $value) {
                if (!isset($record[$key]) || (string) $record[$key] !== (string) $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $record;
            }
        }
        return null;
    }

    public function create(array $data): int
    {
        $records = $this->readAll();
        $id = $this->getNextId($records);
        $data['id'] = $id;
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        $records[] = $data;
        $this->writeAll($records);
        return $id;
    }

    public function update(int $id, array $data): bool
    {
        $records = $this->readAll();
        foreach ($records as &$record) {
            if ((int) $record['id'] === $id) {
                foreach ($data as $key => $value) {
                    $record[$key] = $value;
                }
                $record['updated_at'] = date('Y-m-d H:i:s');
                $this->writeAll($records);
                return true;
            }
        }
        return false;
    }

    public function delete(int $id): bool
    {
        $records = $this->readAll();
        $filtered = array_filter($records, fn($r) => (int) $r['id'] !== $id);
        if (count($filtered) === count($records)) {
            return false;
        }
        $this->writeAll(array_values($filtered));
        return true;
    }

    public function count(array $criteria = []): int
    {
        if (empty($criteria)) {
            return count($this->readAll());
        }
        return count($this->findBy($criteria));
    }
}
