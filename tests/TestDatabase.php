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
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                username            TEXT    NOT NULL,
                email               TEXT    NOT NULL UNIQUE,
                password            TEXT    NOT NULL,
                global_role         TEXT    NOT NULL DEFAULT 'user',
                status              TEXT    NOT NULL DEFAULT 'active',
                suspended_until     TEXT    DEFAULT NULL,
                birth_date          TEXT    DEFAULT NULL,
                is_professional     INTEGER NOT NULL DEFAULT 0,
                company_name        TEXT    DEFAULT NULL,
                siret               TEXT    DEFAULT NULL,
                account_number      TEXT    DEFAULT NULL,
                pin_hash            TEXT    DEFAULT NULL,
                pin_must_change     INTEGER NOT NULL DEFAULT 0,
                pos_suspended_at    TEXT    DEFAULT NULL,
                pos_suspended_until TEXT    DEFAULT NULL,
                pos_suspended_by    INTEGER DEFAULT NULL,
                created_at          TEXT,
                updated_at          TEXT
            );

            CREATE TABLE IF NOT EXISTS accounts (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL DEFAULT 0,
                name        TEXT    NOT NULL,
                currency    TEXT    NOT NULL DEFAULT 'EUR',
                overdraft   REAL    NOT NULL DEFAULT 0,
                type        TEXT    NOT NULL DEFAULT 'standard',
                frozen      INTEGER NOT NULL DEFAULT 0,
                frozen_reason TEXT DEFAULT NULL,
                frozen_until TEXT DEFAULT NULL,
                frozen_by INTEGER DEFAULT NULL,
                cap         REAL    DEFAULT NULL,
                internal    INTEGER NOT NULL DEFAULT 0,
                event_title TEXT    DEFAULT NULL,
                event_start_at TEXT DEFAULT NULL,
                event_end_at TEXT DEFAULT NULL,
                event_overdraft_limit REAL NOT NULL DEFAULT 0,
                passive_income_paused_at TEXT DEFAULT NULL,
                passive_income_last_reactivated_at TEXT DEFAULT NULL,
                disabled_at TEXT    DEFAULT NULL,
                hidden_from_owner INTEGER NOT NULL DEFAULT 0,
                balance_alert_threshold REAL DEFAULT NULL,
                interest_rate REAL DEFAULT NULL,
                pos_suspended_at TEXT DEFAULT NULL,
                pos_suspended_until TEXT DEFAULT NULL,
                pos_suspended_by INTEGER DEFAULT NULL,
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
                from_currency    TEXT    DEFAULT NULL,
                to_currency      TEXT    DEFAULT NULL,
                exchange_rate    REAL    DEFAULT NULL,
                converted_amount REAL    DEFAULT NULL,
                created_at      TEXT,
                updated_at      TEXT
            );

            CREATE TABLE IF NOT EXISTS exchange_rates (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                base_currency   TEXT    NOT NULL,
                target_currency TEXT    NOT NULL,
                rate            REAL    NOT NULL,
                fetched_at      TEXT    NOT NULL,
                created_at      TEXT,
                updated_at      TEXT,
                UNIQUE (base_currency, target_currency)
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
                execution_day         INTEGER DEFAULT NULL,
                status                TEXT    NOT NULL DEFAULT 'active',
                created_by            INTEGER NOT NULL DEFAULT 0,
                last_executed_at      TEXT    DEFAULT NULL,
                next_execution_at     TEXT    DEFAULT NULL,
                created_at            TEXT,
                updated_at            TEXT
            );

            CREATE TABLE IF NOT EXISTS direct_debits (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                mandate_number  TEXT    NOT NULL,
                scheduled_at    TEXT    NOT NULL,
                executed_at     TEXT    DEFAULT NULL,
                amount          REAL    NOT NULL,
                motif           TEXT    DEFAULT NULL,
                from_account_id INTEGER DEFAULT NULL,
                to_account_id   INTEGER NOT NULL DEFAULT 0,
                debit_tx_id     INTEGER DEFAULT NULL,
                credit_tx_id    INTEGER DEFAULT NULL,
                status          TEXT    NOT NULL DEFAULT 'scheduled',
                retry_count     INTEGER NOT NULL DEFAULT 0,
                created_by      INTEGER NOT NULL DEFAULT 0,
                created_at      TEXT,
                updated_at      TEXT
            );

            CREATE TABLE IF NOT EXISTS notifications (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER NOT NULL DEFAULT 0,
                type       TEXT    NOT NULL,
                title      TEXT    NOT NULL,
                body       TEXT    DEFAULT NULL,
                link       TEXT    DEFAULT NULL,
                is_read    INTEGER NOT NULL DEFAULT 0,
                created_at TEXT,
                updated_at TEXT
            );

            CREATE TABLE IF NOT EXISTS recurring_transfers (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                from_account_id   INTEGER NOT NULL DEFAULT 0,
                to_account_id     INTEGER NOT NULL DEFAULT 0,
                user_id           INTEGER NOT NULL DEFAULT 0,
                amount            REAL    NOT NULL,
                motif             TEXT    NOT NULL DEFAULT '',
                status            TEXT    NOT NULL DEFAULT 'active',
                interval_days     INTEGER NOT NULL,
                next_execution_at TEXT    NOT NULL,
                last_executed_at  TEXT    DEFAULT NULL,
                created_at        TEXT,
                updated_at        TEXT
            );

            CREATE TABLE IF NOT EXISTS event_schedules (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                title      TEXT     NOT NULL,
                start_at   TEXT     NOT NULL,
                end_at     TEXT     NOT NULL,
                created_by INTEGER  NOT NULL DEFAULT 0,
                created_at TEXT,
                updated_at TEXT
            );

            CREATE TABLE IF NOT EXISTS event_account_upgrades (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id  INTEGER NOT NULL DEFAULT 0,
                upgrade_key TEXT    NOT NULL,
                quantity    INTEGER NOT NULL DEFAULT 0,
                level       INTEGER NOT NULL DEFAULT 1,
                created_at  TEXT,
                updated_at  TEXT
            );

            CREATE TABLE IF NOT EXISTS payment_cards (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL DEFAULT 0,
                account_id  INTEGER NOT NULL DEFAULT 0,
                card_number TEXT    NOT NULL UNIQUE,
                last4       TEXT    NOT NULL,
                label       TEXT    NOT NULL DEFAULT '',
                status      TEXT    NOT NULL DEFAULT 'active',
                created_at  TEXT,
                updated_at  TEXT
            );

            CREATE TABLE IF NOT EXISTS checkbooks (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL DEFAULT 0,
                account_id  INTEGER NOT NULL DEFAULT 0,
                status      TEXT    NOT NULL DEFAULT 'active',
                created_at  TEXT,
                updated_at  TEXT
            );

            CREATE TABLE IF NOT EXISTS checks (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                checkbook_id INTEGER NOT NULL DEFAULT 0,
                number       TEXT    NOT NULL,
                status       TEXT    NOT NULL DEFAULT 'available',
                amount       REAL    DEFAULT NULL,
                payee        TEXT    DEFAULT NULL,
                created_at   TEXT,
                updated_at   TEXT
            );

            CREATE TABLE IF NOT EXISTS api_clients (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                name            TEXT    NOT NULL,
                api_key         TEXT    NOT NULL UNIQUE,
                api_secret_hash TEXT    NOT NULL,
                status          TEXT    NOT NULL DEFAULT 'active',
                created_by      INTEGER DEFAULT NULL,
                created_at      TEXT,
                updated_at      TEXT
            );

            CREATE TABLE IF NOT EXISTS api_payments (
                id                    INTEGER PRIMARY KEY AUTOINCREMENT,
                api_client_id         INTEGER NOT NULL,
                card_id               INTEGER DEFAULT NULL,
                account_id            INTEGER DEFAULT NULL,
                transaction_id        INTEGER DEFAULT NULL,
                credit_transaction_id INTEGER DEFAULT NULL,
                deferred_debit_id     INTEGER DEFAULT NULL,
                operation             TEXT    NOT NULL,
                amount                REAL    NOT NULL,
                currency              TEXT    NOT NULL DEFAULT 'EUR',
                status                TEXT    NOT NULL,
                reason                TEXT    NOT NULL DEFAULT '',
                comment               TEXT    NOT NULL DEFAULT '',
                cancelled_at          TEXT    DEFAULT NULL,
                cancelled_by          INTEGER DEFAULT NULL,
                cancel_reason         TEXT    NOT NULL DEFAULT '',
                created_at            TEXT
            );

            CREATE TABLE IF NOT EXISTS pos_status (
                id             INTEGER PRIMARY KEY,
                disabled_at    TEXT    DEFAULT NULL,
                disabled_until TEXT    DEFAULT NULL,
                disabled_by    INTEGER DEFAULT NULL,
                reason         TEXT    NOT NULL DEFAULT '',
                updated_at     TEXT
            );

            CREATE TABLE IF NOT EXISTS loans (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id         INTEGER NOT NULL DEFAULT 0,
                account_id      INTEGER DEFAULT NULL,
                loan_type       TEXT    NOT NULL,
                amount          REAL    NOT NULL,
                months          INTEGER NOT NULL,
                annual_rate     REAL    NOT NULL,
                monthly_payment REAL    NOT NULL,
                total_cost      REAL    NOT NULL,
                total_interest  REAL    NOT NULL,
                status          TEXT    NOT NULL DEFAULT 'pending',
                credit_tx_id    INTEGER DEFAULT NULL,
                refund_tx_id    INTEGER DEFAULT NULL,
                cancel_tx_id    INTEGER DEFAULT NULL,
                disburse_funds  INTEGER NOT NULL DEFAULT 1,
                created_at      TEXT,
                updated_at      TEXT
            );

            CREATE TABLE IF NOT EXISTS loan_installments (
                id                 INTEGER PRIMARY KEY AUTOINCREMENT,
                loan_id            INTEGER NOT NULL DEFAULT 0,
                installment_number INTEGER NOT NULL DEFAULT 1,
                due_date           TEXT    NOT NULL,
                amount             REAL    NOT NULL,
                principal          REAL    NOT NULL DEFAULT 0,
                interest           REAL    NOT NULL DEFAULT 0,
                penalty            REAL    NOT NULL DEFAULT 0,
                status             TEXT    NOT NULL DEFAULT 'pending',
                transaction_id     INTEGER DEFAULT NULL,
                refund_tx_id       INTEGER DEFAULT NULL,
                created_at         TEXT,
                updated_at         TEXT
            );
            CREATE TABLE IF NOT EXISTS feature_flags (
                flag_key    TEXT PRIMARY KEY,
                enabled     INTEGER NOT NULL DEFAULT 1,
                label       TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                category    TEXT NOT NULL DEFAULT 'general',
                updated_by  INTEGER DEFAULT NULL,
                updated_at  TEXT
            );

            CREATE TABLE IF NOT EXISTS supervisors (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                first_name    TEXT NOT NULL,
                last_name     TEXT NOT NULL,
                supervisor_id TEXT NOT NULL UNIQUE,
                pin_hash      TEXT NOT NULL,
                status        TEXT NOT NULL DEFAULT 'active',
                created_by    INTEGER DEFAULT NULL,
                created_at    TEXT,
                updated_at    TEXT
            );

            CREATE TABLE IF NOT EXISTS supervisor_habilitations (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                supervisor_id INTEGER NOT NULL,
                feature_key   TEXT NOT NULL,
                created_at    TEXT,
                updated_at    TEXT,
                UNIQUE (supervisor_id, feature_key)
            );

            INSERT OR IGNORE INTO pos_status (id) VALUES (1);
        SQL;
    }
}
