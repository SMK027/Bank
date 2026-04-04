<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Singleton de connexion à la base de données.
 * Une seule instance PDO est partagée dans toute l'application.
 */
class Database
{
    private static ?Database $instance = null;
    private \PDO $connection;

    /**
     * Constructeur privé — empêche l'instanciation directe.
     */
    private function __construct()
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'app_db';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        $this->connection = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Synchroniser le fuseau horaire MySQL avec PHP
        $phpTz = date('P');
        $this->connection->exec("SET time_zone = '{$phpTz}'");
    }

    /**
     * Empêcher le clonage.
     */
    private function __clone()
    {
    }

    /**
     * Empêcher la désérialisation.
     */
    public function __wakeup()
    {
        throw new \RuntimeException('Désérialisation du singleton interdite.');
    }

    /**
     * Retourne l'instance unique de Database.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retourne la connexion PDO.
     */
    public function getConnection(): \PDO
    {
        return $this->connection;
    }

    /**
     * Réinitialise l'instance (utile pour les tests).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
