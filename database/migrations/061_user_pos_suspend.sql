-- ─── Suspension TPE au niveau utilisateur ────────────────────────────────────
-- Permet de suspendre un commerçant professionnel même s'il ne possède aucun
-- compte bancaire. La suspension est désormais portée par la table `users`
-- plutôt que par `accounts`, ce qui reflète la sémantique correcte : on bloque
-- le marchand, pas un compte particulier.
ALTER TABLE `users`
    ADD COLUMN `pos_suspended_at`    DATETIME     NULL DEFAULT NULL,
    ADD COLUMN `pos_suspended_until` DATETIME     NULL DEFAULT NULL,
    ADD COLUMN `pos_suspended_by`    INT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN `pos_suspend_reason`  VARCHAR(500) NOT NULL DEFAULT '',
    ADD CONSTRAINT `fk_users_pos_suspended_by`
        FOREIGN KEY (`pos_suspended_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Migrer les suspensions actives existantes (accounts → users)
UPDATE `users` u
  JOIN `accounts` a
    ON a.user_id = u.id
   AND a.type = 'pro'
   AND a.pos_suspended_at IS NOT NULL
   AND (a.pos_suspended_until IS NULL OR a.pos_suspended_until > NOW())
   SET u.pos_suspended_at    = a.pos_suspended_at,
       u.pos_suspended_until = a.pos_suspended_until,
       u.pos_suspended_by    = a.pos_suspended_by,
       u.pos_suspend_reason  = a.pos_suspend_reason
 WHERE u.is_professional = 1;
