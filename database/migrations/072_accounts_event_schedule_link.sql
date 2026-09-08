-- ============================================================
-- 072 — Lier les comptes événementiels à event_schedules via FK
-- ============================================================
-- Remplace la duplication des champs event_title/event_start_at/event_end_at
-- directement sur `accounts` par un lien optionnel `event_schedule_id` vers
-- `event_schedules`. Permet de récupérer titre/dates de façon uniforme via
-- une jointure, sans dupliquer la donnée.
--
-- Idempotent : le backfill ci-dessous ne s'exécute que si les anciennes
-- colonnes existent encore (elles sont supprimées par la migration 073).

ALTER TABLE `accounts`
    ADD COLUMN IF NOT EXISTS `event_schedule_id` INT UNSIGNED NULL AFTER `type`;

SET @has_event_title := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'accounts' AND COLUMN_NAME = 'event_title'
);

-- Crée les event_schedules manquants pour chaque combinaison distincte
-- (titre, début, fin) présente sur des comptes événementiels non liés.
SET @sql := IF(@has_event_title > 0,
    'INSERT INTO event_schedules (title, start_at, end_at, created_by)
     SELECT DISTINCT a.event_title, a.event_start_at, a.event_end_at, a.user_id
     FROM accounts a
     WHERE a.type = \'event\' AND a.event_schedule_id IS NULL
       AND a.event_title IS NOT NULL AND a.event_start_at IS NOT NULL AND a.event_end_at IS NOT NULL
       AND NOT EXISTS (
           SELECT 1 FROM event_schedules es
           WHERE es.title = a.event_title AND es.start_at = a.event_start_at AND es.end_at = a.event_end_at
       )',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Relie chaque compte événementiel à l'event_schedule correspondant.
SET @sql := IF(@has_event_title > 0,
    'UPDATE accounts a
     JOIN event_schedules es
       ON es.title = a.event_title AND es.start_at = a.event_start_at AND es.end_at = a.event_end_at
     SET a.event_schedule_id = es.id
     WHERE a.type = \'event\' AND a.event_schedule_id IS NULL
       AND a.event_title IS NOT NULL AND a.event_start_at IS NOT NULL AND a.event_end_at IS NOT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `accounts`
    ADD CONSTRAINT `fk_accounts_event_schedule` FOREIGN KEY IF NOT EXISTS (`event_schedule_id`)
        REFERENCES `event_schedules` (`id`) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS `idx_accounts_event_schedule` ON `accounts` (`event_schedule_id`);
