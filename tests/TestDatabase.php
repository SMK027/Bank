<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use PDO;

/**
 * Crée une base de données SQLite en mémoire avec toutes les tables de l'application
 * et l'injecte dans le singleton Database.
 *
 * À appeler dans setUp() de chaque TestCase qui touche les modèles.
 * À compléter par Database::reset() dans tearDown().
 */
class TestDatabase
{
    public static function make(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec(self::schema());
        Database::setInstance($pdo);

        return $pdo;
    }

    private static function schema(): string
    {
        return <<<'SQL'
            CREATE TABLE IF NOT EXISTS items (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT,
                status     TEXT,
                created_at TEXT,
                updated_at TEXT
            );

            CREATE TABLE IF NOT EXISTS users (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                username        TEXT    NOT NULL,
                email           TEXT    NOT NULL UNIQUE,
                password        TEXT    NOT NULL,
                global_role     TEXT    NOT NULL DEFAULT 'user',
                birth_date      TEXT    DEFAULT NULL,
                is_professional INTEGER NOT NULL DEFAULT 0,
                company_name    TEXT    DEFAULT NULL,
                siret           TEXT    DEFAULT NULL,
                created_at      TEXT,
                updated_at      TEXT
            );

            CREATE TABLE IF NOT EXISTS accounts (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL DEFAULT 0,
                name        TEXT    NOT NULL,
                currency    TEXT    NOT NULL DEFAULT 'EUR',
                overdraft   REAL    NOT NULL DEFAULT 0,
                type        TEXT    NOT NULL DEFAULT 'standard',
                frozen      INTEGER NOT NULL DEFAULT 0,
                cap         REAL    DEFAULT NULL,
                created_at  TEXT,
                updated_at  TEXT
            );

            CREATE TABLE IF NOT EXISTS transactions (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id   INTEGER NOT NULL DEFAULT 0,
                user_id      INTEGER NOT NULL DEFAULT 0,
                type         TEXT    NOT NULL,
                amount       REAL    NOT NULL,
                category     TEXT    NOT NULL DEFAULT '',
                comment      TEXT,
                scheduled_at TEXT    DEFAULT NULL,
                created_at   TEXT,
                updated_at   TEXT
            );

            CREATE TABLE IF NOT EXISTS account_accesses (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id  INTEGER NOT NULL DEFAULT 0,
                user_id     INTEGER NOT NULL DEFAULT 0,
                type        TEXT    NOT NULL DEFAULT 'permanent',
                expires_at  TEXT    DEFAULT NULL,
                created_at  TEXT,
                updated_at  TEXT
            );

            CREATE TABLE IF NOT EXISTS transfers (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                from_account_id INTEGER NOT NULL DEFAULT 0,
                to_account_id   INTEGER NOT NULL DEFAULT 0,
                user_id         INTEGER NOT NULL DEFAULT 0,
                amount          REAL    NOT NULL,
                motif           TEXT    NOT NULL DEFAULT '',
                status          TEXT    NOT NULL DEFAULT 'success',
                scheduled_at    TEXT    DEFAULT NULL,
                executed_at     TEXT    DEFAULT NULL,
                debit_tx_id     INTEGER NOT NULL DEFAULT 0,
                credit_tx_id    INTEGER NOT NULL DEFAULT 0,
                created_at      TEXT,
                updated_at      TEXT
            );

            CREATE TABLE IF NOT EXISTS guardianships (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                minor_user_id    INTEGER NOT NULL DEFAULT 0,
                guardian_user_id INTEGER NOT NULL DEFAULT 0,
                created_by       INTEGER NOT NULL DEFAULT 0,
                created_at       TEXT,
                updated_at       TEXT
            );

            CREATE TABLE IF NOT EXISTS tickets (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL DEFAULT 0,
                type        TEXT    NOT NULL,
                subject     TEXT    NOT NULL,
                status      TEXT    NOT NULL DEFAULT 'open',
                account_id  INTEGER DEFAULT NULL,
                priority    TEXT    NOT NULL DEFAULT 'normal',
                created_at  TEXT,
                updated_at  TEXT
            );

            CREATE TABLE IF NOT EXISTS ticket_messages (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_id   INTEGER NOT NULL DEFAULT 0,
                user_id     INTEGER NOT NULL DEFAULT 0,
                is_staff    INTEGER NOT NULL DEFAULT 0,
                body        TEXT    NOT NULL,
                created_at  TEXT
            );

            CREATE TABLE IF NOT EXISTS mandates (
                id                    INTEGER PRIMARY KEY AUTOINCREMENT,
                number                TEXT    NOT NULL UNIQUE,
                emitter_account_id    INTEGER NOT NULL DEFAULT 0,
                recipient_account_id  INTEGER NOT NULL DEFAULT 0,
                description           TEXT    NOT NULL DEFAULT '',
                amount                REAL    NOT NULL,
                type                  TEXT    NOT NULL DEFAULT 'one_time',
                interval_days         INTEGER DEFAULT NULL,
                status                TEXT    NOT NULL DEFAULT 'active',
                created_by            INTEGER NOT NULL DEFAULT 0,
                created_at            TEXT,
                updated_at            TEXT
            );
        SQL;
    }
}
