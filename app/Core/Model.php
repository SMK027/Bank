<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Database;

/**
 * Modèle abstrait de base avec opérations CRUD.
 * Chaque modèle concret doit définir la propriété $table.
 */
abstract class Model
{
    protected \PDO $db;
    protected string $table = '';

    public function __construct(?\PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /**
     * Trouve un enregistrement par son ID.
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Retourne tous les enregistrements.
     */
    public function findAll(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY {$orderBy} {$direction}");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Recherche par critères (AND).
     */
    public function findBy(array $criteria, string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $conditions = [];
        $params = [];

        foreach ($criteria as $key => $value) {
            $conditions[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }

        $where = implode(' AND ', $conditions);
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderBy} {$direction}");
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Recherche un seul enregistrement par critères.
     */
    public function findOneBy(array $criteria): ?array
    {
        $conditions = [];
        $params = [];

        foreach ($criteria as $key => $value) {
            $conditions[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }

        $where = implode(' AND ', $conditions);
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$where} LIMIT 1");
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Crée un enregistrement et retourne son ID.
     */
    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $stmt = $this->db->prepare("INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour un enregistrement par son ID.
     */
    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            $sets[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }

        $setClause = implode(', ', $sets);
        $stmt = $this->db->prepare("UPDATE {$this->table} SET {$setClause} WHERE id = :id");
        return $stmt->execute($params);
    }

    /**
     * Supprime un enregistrement par son ID.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Compte les enregistrements correspondant aux critères.
     */
    public function count(array $criteria = []): int
    {
        if (empty($criteria)) {
            $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->table}");
            return (int) $stmt->fetchColumn();
        }

        $conditions = [];
        $params = [];

        foreach ($criteria as $key => $value) {
            $conditions[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }

        $where = implode(' AND ', $conditions);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Exécute une requête SQL préparée.
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
