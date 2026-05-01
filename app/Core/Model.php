<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Modèle abstrait de base avec opérations CRUD via PDO (MariaDB / SQLite).
 * Chaque modèle concret doit définir la propriété $table (nom de la table SQL).
 */
abstract class Model
{
    protected string $table = '';

    /** Mettre à false pour les tables sans colonne `updated_at` (ex. audit_logs). */
    protected bool $hasUpdatedAt = true;

    /** Mettre à false pour les tables qui n'ont pas de colonne `created_at`. */
    protected bool $hasCreatedAt = true;

    protected function getPdo(): PDO
    {
        return Database::getInstance();
    }

    /**
     * Valide un identifiant SQL (nom de colonne / table) pour prévenir l'injection.
     */
    private function col(string $name): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException("Identifiant SQL invalide : {$name}");
        }
        return $name;
    }

    // ─── Lecture ────────────────────────────────────────────────────────────

    public function find(int $id): ?array
    {
        $stmt = $this->getPdo()->prepare(
            "SELECT * FROM `{$this->table}` WHERE id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function findAll(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $col = $this->col($orderBy);
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $stmt = $this->getPdo()->query(
            "SELECT * FROM `{$this->table}` ORDER BY `{$col}` {$dir}"
        );
        return $stmt->fetchAll();
    }

    public function findBy(array $criteria, string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $col = $this->col($orderBy);
        $dir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        if (empty($criteria)) {
            return $this->findAll($orderBy, $direction);
        }

        [$where, $params] = $this->buildWhere($criteria);
        $stmt = $this->getPdo()->prepare(
            "SELECT * FROM `{$this->table}` WHERE {$where} ORDER BY `{$col}` {$dir}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findOneBy(array $criteria): ?array
    {
        [$where, $params] = $this->buildWhere($criteria);
        $stmt = $this->getPdo()->prepare(
            "SELECT * FROM `{$this->table}` WHERE {$where} LIMIT 1"
        );
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    // ─── Écriture ───────────────────────────────────────────────────────────

    public function create(array $data): int
    {
        if ($this->hasCreatedAt) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if ($this->hasUpdatedAt) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $cols   = array_map(fn($c) => '`' . $this->col($c) . '`', array_keys($data));
        $pholds = array_fill(0, count($data), '?');

        $stmt = $this->getPdo()->prepare(sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            implode(', ', $cols),
            implode(', ', $pholds)
        ));
        $stmt->execute(array_values($data));
        return (int) $this->getPdo()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        if ($this->hasUpdatedAt) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $sets   = array_map(fn($c) => '`' . $this->col($c) . '` = ?', array_keys($data));
        $values = array_values($data);
        $values[] = $id;

        $stmt = $this->getPdo()->prepare(sprintf(
            'UPDATE `%s` SET %s WHERE id = ?',
            $this->table,
            implode(', ', $sets)
        ));
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->getPdo()->prepare(
            "DELETE FROM `{$this->table}` WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function count(array $criteria = []): int
    {
        if (empty($criteria)) {
            $stmt = $this->getPdo()->query(
                "SELECT COUNT(*) FROM `{$this->table}`"
            );
            return (int) $stmt->fetchColumn();
        }

        [$where, $params] = $this->buildWhere($criteria);
        $stmt = $this->getPdo()->prepare(
            "SELECT COUNT(*) FROM `{$this->table}` WHERE {$where}"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    // ─── Helpers internes ───────────────────────────────────────────────────

    /**
     * Construit une clause WHERE paramétrée depuis un tableau de critères.
     * Gère les valeurs null (IS NULL) pour éviter les injections.
     *
     * @return array{string, array}  [clause WHERE, paramètres]
     */
    private function buildWhere(array $criteria): array
    {
        $conditions = [];
        $params     = [];

        foreach ($criteria as $key => $value) {
            $c = $this->col($key);
            if ($value === null) {
                $conditions[] = "`{$c}` IS NULL";
            } else {
                $conditions[] = "`{$c}` = ?";
                $params[]     = $value;
            }
        }

        return [implode(' AND ', $conditions), $params];
    }
}
