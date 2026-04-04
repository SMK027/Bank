<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Singleton de connexion PDO vers MariaDB.
 * Utilise les variables d'environnement DB_*.
 */
class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host     = getenv('DB_HOST')     ?: 'db';
            $port     = getenv('DB_PORT')     ?: '3306';
            $dbname   = getenv('DB_DATABASE') ?: 'bankapp';
            $username = getenv('DB_USERNAME') ?: 'bankapp';
            $password = getenv('DB_PASSWORD') ?: '';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

            self::$instance = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$instance;
    }

    /**
     * Injecte une instance PDO (utile pour les tests avec SQLite en mémoire).
     */
    public static function setInstance(PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    /**
     * Réinitialise le singleton (tearDown des tests).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
