<?php

declare(strict_types=1);

/**
 * Script CLI : exécution des virements planifiés échus.
 *
 * Lancé automatiquement par cron toutes les minutes.
 *
 * Pour chaque virement avec status = 'scheduled' et scheduled_at <= maintenant :
 *   1. Matérialise les deux transactions liées (scheduled_at → null)
 *   2. Passe le virement en status 'success' (ou 'failed' en cas d'erreur)
 *
 * Les transactions planifiées orphelines (sans virement associé) sont également
 * matérialisées à la fin pour rétro-compatibilité.
 *
 * Usage manuel : php database/process_scheduled.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Transaction;
use App\Models\Transfer;

$transferModel    = new Transfer();
$transactionModel = new Transaction();

$now      = time();
$executed = 0;
$errors   = 0;

/* ─────────────────────────────────────────────────────────────────
   1. Traitement des virements planifiés échus
   ───────────────────────────────────────────────────────────────── */
foreach ($transferModel->getDueScheduled() as $transfer) {
    $transferId = (int) $transfer['id'];
    $debitId    = (int) ($transfer['debit_tx_id']  ?? 0);
    $creditId   = (int) ($transfer['credit_tx_id'] ?? 0);
    $ok         = true;

    // Matérialiser la transaction de débit
    if ($debitId > 0) {
        if (!$transactionModel->update($debitId, ['scheduled_at' => null])) {
            echo sprintf("[%s] ERREUR : impossible de matérialiser tx débit #%d (virement #%d)\n",
                date('Y-m-d H:i:s'), $debitId, $transferId);
            $ok = false;
        }
    }

    // Matérialiser la transaction de crédit
    if ($creditId > 0) {
        if (!$transactionModel->update($creditId, ['scheduled_at' => null])) {
            echo sprintf("[%s] ERREUR : impossible de matérialiser tx crédit #%d (virement #%d)\n",
                date('Y-m-d H:i:s'), $creditId, $transferId);
            $ok = false;
        }
    }

    // Mettre à jour le statut du virement
    if ($ok) {
        $transferModel->markSuccess($transferId);
        $executed++;
        echo sprintf(
            "[%s] Virement #%d exécuté : compte #%d → #%d — %.2f — planifié le %s\n",
            date('Y-m-d H:i:s'),
            $transferId,
            (int) $transfer['from_account_id'],
            (int) $transfer['to_account_id'],
            (float) $transfer['amount'],
            $transfer['scheduled_at']
        );
    } else {
        $transferModel->markFailed($transferId);
        $errors++;
    }
}

/* ─────────────────────────────────────────────────────────────────
   2. Rétro-compatibilité : transactions planifiées sans virement
   ───────────────────────────────────────────────────────────────── */
// Collecter les IDs de transactions déjà traitées via les virements
$processedTxIds = [];
foreach ($transferModel->findAll() as $tr) {
    if (!empty($tr['debit_tx_id']))  $processedTxIds[(int) $tr['debit_tx_id']]  = true;
    if (!empty($tr['credit_tx_id'])) $processedTxIds[(int) $tr['credit_tx_id']] = true;
}

foreach ($transactionModel->findAll('scheduled_at', 'ASC') as $t) {
    if (empty($t['scheduled_at'])) continue;
    if (strtotime($t['scheduled_at']) > $now) continue;

    $txId = (int) $t['id'];
    if (isset($processedTxIds[$txId])) continue; // déjà géré via le virement

    if ($transactionModel->update($txId, ['scheduled_at' => null])) {
        $executed++;
        echo sprintf(
            "[%s] Tx orpheline #%d matérialisée : compte #%d — %s %.2f\n",
            date('Y-m-d H:i:s'),
            $txId,
            (int) $t['account_id'],
            $t['type'] === 'income' ? 'crédit' : 'débit',
            (float) $t['amount']
        );
    } else {
        $errors++;
        echo sprintf("[%s] ERREUR : tx orpheline #%d non matérialisée\n",
            date('Y-m-d H:i:s'), $txId);
    }
}

/* ─────────────────────────────────────────────────────────────────
   Résumé
   ───────────────────────────────────────────────────────────────── */
if ($executed > 0 || $errors > 0) {
    echo sprintf("[%s] Résultat : %d exécuté(s), %d erreur(s)\n",
        date('Y-m-d H:i:s'), $executed, $errors);
}

exit($errors > 0 ? 1 : 0);
