<?php

declare(strict_types=1);

/**
 * Script CLI : exécution des transactions programmées échues.
 *
 * Lancé automatiquement par cron toutes les minutes.
 * Chaque transaction dont scheduled_at est passé est matérialisée :
 * son champ scheduled_at est mis à null pour qu'elle devienne
 * une transaction ordinaire dans tous les calculs de solde.
 *
 * Usage manuel : php database/process_scheduled.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Transaction;

$dataDir          = dirname(__DIR__) . '/data';
$transactionModel = new Transaction($dataDir);

$now      = time();
$executed = 0;
$errors   = 0;

foreach ($transactionModel->findAll('scheduled_at', 'ASC') as $t) {
    // Ignorer les transactions sans date programmée
    if (empty($t['scheduled_at'])) {
        continue;
    }

    // Ignorer les transactions encore dans le futur
    if (strtotime($t['scheduled_at']) > $now) {
        continue;
    }

    $id = (int) $t['id'];

    if ($transactionModel->update($id, ['scheduled_at' => null])) {
        $executed++;
        echo sprintf(
            "[%s] Matérialisée : transaction #%d — compte #%d — %s %.2f — programmée le %s\n",
            date('Y-m-d H:i:s'),
            $id,
            (int) $t['account_id'],
            $t['type'] === 'income' ? 'crédit' : 'débit',
            (float) $t['amount'],
            $t['scheduled_at']
        );
    } else {
        $errors++;
        echo sprintf(
            "[%s] ERREUR : impossible de matérialiser la transaction #%d\n",
            date('Y-m-d H:i:s'),
            $id
        );
    }
}

if ($executed > 0 || $errors > 0) {
    echo sprintf(
        "[%s] Résultat : %d matérialisée(s), %d erreur(s)\n",
        date('Y-m-d H:i:s'),
        $executed,
        $errors
    );
}

exit($errors > 0 ? 1 : 0);
