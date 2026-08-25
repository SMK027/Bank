-- ============================================================
-- Migration 068 — Découvert événementiel
-- ============================================================

ALTER TABLE `accounts`
    ADD COLUMN `event_overdraft_limit` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `event_end_at`;
