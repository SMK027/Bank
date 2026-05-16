-- ============================================================
-- Migration 053 — Indexes secondaires manquants
-- ------------------------------------------------------------
-- InnoDB indexe automatiquement les colonnes des FOREIGN KEY,
-- mais plusieurs colonnes utilisées dans les filtres WHERE des
-- tâches cron et du calcul de solde "à venir" (Account::getBalancesBatch)
-- n'étaient pas couvertes.
--
-- Le runner database/migrate.php ignore l'erreur MySQL 1061
-- (Duplicate key name) : cette migration est donc idempotente.
-- ============================================================

-- transactions : filtres sur scheduled_at (cron + getBalancesBatch)
ALTER TABLE `transactions`
    ADD INDEX `idx_tx_scheduled_at` (`scheduled_at`),
    ADD INDEX `idx_tx_account_scheduled` (`account_id`, `scheduled_at`);

-- transfers : cron des virements programmés
ALTER TABLE `transfers`
    ADD INDEX `idx_transfers_status_scheduled` (`status`, `scheduled_at`);

-- recurring_transfers : cron des virements récurrents
ALTER TABLE `recurring_transfers`
    ADD INDEX `idx_rec_tr_status_next` (`status`, `next_execution_at`);
