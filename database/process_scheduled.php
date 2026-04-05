<?php

declare(strict_types=1);

/**
 * Script CLI : exécution des opérations planifiées échues.
 *
 * Lancé automatiquement par cron toutes les minutes.
 *
 * 1. Virements planifiés (status = 'scheduled', scheduled_at <= now)
 *    → matérialise les transactions pré-créées, passe en 'success' ou 'failed'.
 *
 * 2. Prélèvements automatiques (direct_debits, status = 'scheduled', scheduled_at <= now)
 *    → crée les transactions débit (et crédit si compte émetteur défini),
 *      passe en 'success' ou 'failed'.
 *
 * 3. Transactions planifiées orphelines (sans virement associé)
 *    → matérialisées pour rétro-compatibilité.
 *
 * Usage manuel : php database/process_scheduled.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Forcer le même fuseau horaire que l'application web
date_default_timezone_set('Europe/Paris');

use App\Models\Account;
use App\Models\DirectDebit;
use App\Models\Transaction;
use App\Models\Transfer;

$transferModel    = new Transfer();
$transactionModel = new Transaction();
$directDebitModel = new DirectDebit();
$accountModel     = new Account();

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
   2. Traitement des prélèvements automatiques échus
   ───────────────────────────────────────────────────────────────── */
foreach ($directDebitModel->getDue() as $debit) {
    $debitId      = (int) $debit['id'];
    $toAccountId  = (int) $debit['to_account_id'];
    $fromAccountId = $debit['from_account_id'] !== null ? (int) $debit['from_account_id'] : null;
    $amount       = (float) $debit['amount'];
    $mandate      = $debit['mandate_number'];
    $motif        = $debit['motif'] ?? '';
    $comment      = 'Prélèvement' . ($motif !== '' ? ' — ' . $motif : '') . ' (mandat ' . $mandate . ')';

    $ok = true;

    // Vérifier que le compte destinataire (débité) n'est pas gelé
    if ($accountModel->isFrozen($toAccountId)) {
        echo sprintf(
            "[%s] ERREUR prélèvement #%d : compte #%d gelé, exécution impossible.\n",
            date('Y-m-d H:i:s'), $debitId, $toAccountId
        );
        $directDebitModel->markFailed($debitId);
        $errors++;
        continue;
    }

    // Rejeter automatiquement si le type de compte n'autorise pas le découvert
    // et que le prélèvement ferait basculer le solde en négatif.
    // Types concernés : minor, savings, online (overdraft: false).
    $toAccount = $accountModel->find($toAccountId);
    $toType    = $toAccount['type'] ?? 'standard';
    if (!Account::typeAllowsOverdraft($toType)) {
        $currentBalance = $accountModel->getBalance($toAccountId);
        if ($currentBalance - $amount < 0) {
            echo sprintf(
                "[%s] REJET AUTO prélèvement #%d : compte #%d (type '%s') solde insuffisant (%.2f < %.2f).\n",
                date('Y-m-d H:i:s'), $debitId, $toAccountId, $toType, $currentBalance, $amount
            );
            $directDebitModel->markAutoRejected($debitId);
            $errors++;
            continue;
        }
    }

    // Créer la transaction de débit sur le compte destinataire
    $debitTxId = $transactionModel->addTransaction(
        $toAccountId,
        'expense',
        $amount,
        'Prélèvement',
        $comment,
        0  // user_id = 0 → affiché comme "Modération"
    );

    // Créer la transaction de crédit sur le compte émetteur (si défini)
    $creditTxId = null;
    if ($fromAccountId !== null) {
        try {
            $creditTxId = $transactionModel->addTransaction(
                $fromAccountId,
                'income',
                $amount,
                'Prélèvement',
                $comment,
                0
            );
        } catch (\Throwable $e) {
            echo sprintf(
                "[%s] AVERTISSEMENT prélèvement #%d : crédit compte #%d échoué (%s)\n",
                date('Y-m-d H:i:s'), $debitId, $fromAccountId, $e->getMessage()
            );
            $ok = false;
        }
    }

    if ($ok) {
        $directDebitModel->markSuccess($debitId, $debitTxId, $creditTxId);
        $executed++;
        echo sprintf(
            "[%s] Prélèvement #%d exécuté : débit compte #%d%s — %.2f — mandat %s\n",
            date('Y-m-d H:i:s'),
            $debitId,
            $toAccountId,
            $fromAccountId !== null ? ' / crédit compte #' . $fromAccountId : ' (banque)',
            $amount,
            $mandate
        );
    } else {
        $directDebitModel->markFailed($debitId);
        $errors++;
    }
}

/* ─────────────────────────────────────────────────────────────────
   3. Rétro-compatibilité : transactions planifiées sans virement
   ───────────────────────────────────────────────────────────────── */
// Collecter les IDs de transactions déjà traitées via les virements et prélèvements
$processedTxIds = [];
foreach ($transferModel->findAll() as $tr) {
    if (!empty($tr['debit_tx_id']))  $processedTxIds[(int) $tr['debit_tx_id']]  = true;
    if (!empty($tr['credit_tx_id'])) $processedTxIds[(int) $tr['credit_tx_id']] = true;
}
foreach ($directDebitModel->findAll() as $dd) {
    if (!empty($dd['debit_tx_id']))  $processedTxIds[(int) $dd['debit_tx_id']]  = true;
    if (!empty($dd['credit_tx_id'])) $processedTxIds[(int) $dd['credit_tx_id']] = true;
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
