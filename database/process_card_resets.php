<?php

declare(strict_types=1);

/**
 * Script cron : remise à zéro du plafond mensuel des cartes à débit immédiat.
 * Planification recommandée : 0 0 1 * * (tous les 1er du mois à minuit)
 *
 * Pour chaque carte bancaire :
 *   - Liée à un compte sans débit différé (deferred_debit_enabled = 0 ou NULL)
 *   - Ayant un plafond mensuel configuré (monthly_limit IS NOT NULL)
 * → Réinitialise monthly_reset_at à l'instant courant.
 *
 * Les cartes à débit différé sont gérées manuellement par l'utilisateur
 * depuis la page de gestion des cartes (une fois par mois, à la fin de période).
 *
 * Usage manuel : docker compose exec app php database/process_card_resets.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Europe/Paris');

use App\Core\Database;

// Limiter l'exécution automatique au 1er du mois (protection si cron mal configuré)
$forced = in_array('--force', $argv ?? [], true);
if (!$forced && date('d') !== '01') {
    echo '[' . date('Y-m-d H:i:s') . '] Pas le 1er du mois — rien à faire.' . PHP_EOL;
    exit(0);
}

$pdo = Database::getInstance();
$now = date('Y-m-d H:i:s');

$stmt = $pdo->prepare(
    "UPDATE payment_cards pc
       JOIN accounts a ON a.id = pc.account_id
        SET pc.monthly_reset_at             = :now,
            pc.monthly_spent_override       = NULL,
            pc.monthly_spent_override_month = NULL
      WHERE (a.deferred_debit_enabled = 0 OR a.deferred_debit_enabled IS NULL)
        AND pc.monthly_limit IS NOT NULL"
);
$stmt->execute([':now' => $now]);
$count = $stmt->rowCount();

echo '[' . date('Y-m-d H:i:s') . "] Remise à zéro du plafond : {$count} carte(s) à débit immédiat réinitialisée(s)." . PHP_EOL;
