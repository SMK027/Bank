-- Ajoute un drapeau indiquant que l'utilisateur doit changer son code PIN
-- (utilisé après une réinitialisation par un modérateur).
ALTER TABLE `users`
    ADD COLUMN `pin_must_change` TINYINT(1) NOT NULL DEFAULT 0 AFTER `pin_hash`;
