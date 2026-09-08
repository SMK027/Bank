-- ============================================================
-- 073 — Suppression des champs événementiels dupliqués sur accounts
-- ============================================================
-- Ces champs sont désormais portés par `event_schedules`, accessible via
-- `accounts.event_schedule_id` (migration 072).

ALTER TABLE `accounts`
    DROP INDEX IF EXISTS `idx_accounts_event_end`,
    DROP COLUMN IF EXISTS `event_title`,
    DROP COLUMN IF EXISTS `event_start_at`,
    DROP COLUMN IF EXISTS `event_end_at`;
