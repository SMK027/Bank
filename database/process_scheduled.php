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
use App\Models\AuditLog;
use App\Models\DirectDebit;
use App\Models\Guardianship;
use App\Models\Mandate;
use App\Models\Notification;
use App\Models\RecurringTransfer;
use App\Models\Transaction;
use App\Models\Transfer;

$transferModel          = new Transfer();
$transactionModel       = new Transaction();
$directDebitModel       = new DirectDebit();
$accountModel           = new Account();
$mandateModel           = new Mandate();
$recurringTransferModel = new RecurringTransfer();
$notifModel             = new Notification();
$guardianshipModel      = new Guardianship();

/**
 * Notifie le propriétaire d'un compte ET ses éventuels responsables légaux.
 * Garantit que les comptes mineurs transmettent bien l'alerte au tuteur.
 *
 * @param int    $userId    user_id du propriétaire du compte
 * @param int    $accountId id du compte concerné (utilisé pour le lien)
 */
$notifyWithGuardians = function(
    int $userId,
    int $accountId,
    string $type,
    string $title,
    string $body
) use ($notifModel, $guardianshipModel): void {
    $link = '/accounts/' . $accountId;
    $notifModel->notify($userId, $type, $title, $body, $link);
    foreach ($guardianshipModel->getGuardiansOf($userId) as $g) {
        $notifModel->notify((int) $g['guardian_user_id'], $type, $title, $body, $link);
    }
};

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
   2. Exécution des virements récurrents échus
   ───────────────────────────────────────────────────────────────── */
foreach ($recurringTransferModel->getDue() as $recurring) {
    $recId         = (int) $recurring['id'];
    $fromAccountId = (int) $recurring['from_account_id'];
    $toAccountId   = (int) $recurring['to_account_id'];
    $amount        = (float) $recurring['amount'];
    $motif         = $recurring['motif'] ?? '';
    $userId        = (int) ($recurring['user_id'] ?? 0);
    $ok            = true;

    // Vérifier que le compte émetteur n'est pas gelé
    if ($accountModel->isFrozen($fromAccountId)) {
        echo sprintf(
            "[%s] ERREUR virement récurrent #%d : compte émetteur #%d gelé — report à la prochaine occurrence.\n",
            date('Y-m-d H:i:s'), $recId, $fromAccountId
        );
        // On reprogramme quand même pour ne pas bloquer les futures occurrences
        $recurringTransferModel->markExecuted($recId);
        AuditLog::log(null, AuditLog::ACTION_TRANSFER_RECURRING_FAIL, ['amount' => $amount, 'reason' => 'frozen'], targetAccountId: $fromAccountId);
        $frozenAccount = $accountModel->find($fromAccountId);
        if ($frozenAccount) {
            $notifyWithGuardians(
                (int) $frozenAccount['user_id'],
                $fromAccountId,
                'recurring_transfer_failed',
                'Virement récurrent #' . $recId . ' non exécuté',
                'Le virement récurrent de ' . number_format($amount, 2, ',', ' ') . ' € n\'a pas pu être exécuté : votre compte « ' . ($frozenAccount['name'] ?? 'Compte #' . $fromAccountId) . ' » est actuellement gelé.'
            );
        }
        $errors++;
        continue;
    }

    $fromAccount = $accountModel->find($fromAccountId);
    $fromType    = $fromAccount['type'] ?? 'standard';
    $balance     = $accountModel->getBalance($fromAccountId);
    $overdraft   = (float) ($fromAccount['overdraft'] ?? 0);

    // Vérifier le plafond du compte destinataire (les intérêts annuels passent par process_interests.php)
    $toAccount = $accountModel->find($toAccountId);
    $toCap     = (float) ($toAccount['cap'] ?? 0);
    if (Account::typeHasCap($toAccount['type'] ?? '') && $toCap > 0) {
        $toBalance = $accountModel->getBalance($toAccountId);
        if ($toBalance + $amount > $toCap) {
            echo sprintf(
                "[%s] ERREUR virement récurrent #%d : plafond atteint sur compte destinataire #%d (%.2f + %.2f > %.2f).\n",
                date('Y-m-d H:i:s'), $recId, $toAccountId, $toBalance, $amount, $toCap
            );
            $recurringTransferModel->markExecuted($recId);
            AuditLog::log(null, AuditLog::ACTION_TRANSFER_RECURRING_FAIL, ['amount' => $amount, 'reason' => 'cap_reached', 'to_account' => $toAccountId], targetAccountId: $toAccountId);
            $notifyWithGuardians(
                (int) $fromAccount['user_id'],
                $fromAccountId,
                'recurring_transfer_failed',
                'Virement récurrent #' . $recId . ' non exécuté',
                sprintf(
                    'Le virement récurrent de %s %s vers « %s » n\'a pas pu être exécuté : le plafond de %s %s est atteint (solde actuel : %s %s).',
                    number_format($amount, 2, ',', ' '),
                    $toAccount['currency'] ?? '€',
                    $toAccount['name'] ?? ('Compte #' . $toAccountId),
                    number_format($toCap, 2, ',', ' '),
                    $toAccount['currency'] ?? '€',
                    number_format($toBalance, 2, ',', ' '),
                    $toAccount['currency'] ?? '€'
                )
            );
            $errors++;
            continue;
        }
    }

    if ($balance - $amount < -$overdraft) {
        echo sprintf(
            "[%s] ERREUR virement récurrent #%d : solde insuffisant sur compte #%d (%.2f < %.2f).\n",
            date('Y-m-d H:i:s'), $recId, $fromAccountId, $balance, $amount
        );
        $recurringTransferModel->markExecuted($recId);
        AuditLog::log(null, AuditLog::ACTION_TRANSFER_RECURRING_FAIL, ['amount' => $amount, 'reason' => 'insufficient_balance'], targetAccountId: $fromAccountId);
        $notifyWithGuardians(
            (int) $fromAccount['user_id'],
            $fromAccountId,
            'recurring_transfer_failed',
            'Virement récurrent #' . $recId . ' non exécuté',
            'Le virement récurrent de ' . number_format($amount, 2, ',', ' ') . ' € n\'a pas pu être exécuté : solde insuffisant sur votre compte « ' . ($fromAccount['name'] ?? 'Compte #' . $fromAccountId) . ' ».'
        );
        $errors++;
        continue;
    }

    $label    = 'Virement récurrent' . ($motif !== '' ? ' — ' . $motif : '');
    $nowStr   = date('Y-m-d H:i:s');

    try {
        $debitTxId = $transactionModel->addTransaction(
            $fromAccountId, 'expense', $amount, 'Virement', $label, $userId
        );
        $creditTxId = $transactionModel->addTransaction(
            $toAccountId, 'income', $amount, 'Virement', $label, $userId
        );
        $transferModel->createTransfer(
            $fromAccountId, $toAccountId, $userId, $amount, $motif, null, $debitTxId, $creditTxId
        );
        $recurringTransferModel->markExecuted($recId);
        AuditLog::log(null, AuditLog::ACTION_TRANSFER_RECURRING_EXEC, ['amount' => $amount, 'from_account' => $fromAccountId, 'to_account' => $toAccountId], targetAccountId: $fromAccountId);
        // Seuil d'alerte
        if (\App\Models\Account::crossedAlertThreshold($fromAccount, $balance, $balance - $amount)) {
            $notifyWithGuardians(
                (int) $fromAccount['user_id'], $fromAccountId, 'balance_alert',
                'Seuil d\'alerte atteint \u2014 ' . ($fromAccount['name'] ?? ''),
                sprintf(
                    'Le solde de votre compte \u00ab %s \u00bb est pass\u00e9 sous le seuil d\'alerte de %s %s. Solde actuel : %s %s.',
                    $fromAccount['name'] ?? '',
                    number_format((float) $fromAccount['balance_alert_threshold'], 2, ',', ' '),
                    $fromAccount['currency'] ?? '',
                    number_format($balance - $amount, 2, ',', ' '),
                    $fromAccount['currency'] ?? ''
                )
            );
        }
        $executed++;
        echo sprintf(
            "[%s] Virement récurrent #%d exécuté : compte #%d → #%d — %.2f\n",
            date('Y-m-d H:i:s'), $recId, $fromAccountId, $toAccountId, $amount
        );
    } catch (\Throwable $e) {
        echo sprintf(
            "[%s] ERREUR virement récurrent #%d : %s\n",
            date('Y-m-d H:i:s'), $recId, $e->getMessage()
        );
        $errors++;
    }
}

/* ─────────────────────────────────────────────────────────────────
   3. Génération des prélèvements issus des mandats échus
   ───────────────────────────────────────────────────────────────── */
foreach ($mandateModel->getDue() as $mandate) {
    $mandateId          = (int) $mandate['id'];
    $emitterAccountId   = (int) $mandate['emitter_account_id'];
    $recipientAccountId = (int) $mandate['recipient_account_id'];
    $amount             = (float) $mandate['amount'];
    $number             = $mandate['number'];
    $description        = $mandate['description'] ?? '';

    $motif = $description !== '' ? $description : null;

    // Créer un prélèvement planifié immédiatement
    $ddId = $directDebitModel->createDirectDebit(
        $number,
        date('Y-m-d H:i:s'),
        $amount,
        $recipientAccountId,
        $emitterAccountId,
        $motif,
        (int) ($mandate['created_by'] ?? 0)
    );

    // Marquer le mandat comme exécuté (reprogrammer si récurrent)
    $mandateModel->markExecuted($mandateId);
    echo sprintf(
        "[%s] Mandat #%d (%s) → prélèvement #%d créé : compte #%d → #%d — %.2f€ — type %s\n",
        date('Y-m-d H:i:s'),
        $mandateId,
        $number,
        $ddId,
        $emitterAccountId,
        $recipientAccountId,
        $amount,
        $mandate['type']
    );
}

/* ─────────────────────────────────────────────────────────────────
   4. Traitement des prélèvements automatiques échus
   ───────────────────────────────────────────────────────────────── */
foreach ($directDebitModel->getDue() as $debit) {
    $debitId       = (int) $debit['id'];
    $toAccountId   = (int) $debit['to_account_id'];
    $fromAccountId = $debit['from_account_id'] !== null ? (int) $debit['from_account_id'] : null;
    $amount        = (float) $debit['amount'];
    $mandate       = $debit['mandate_number'];
    $motif         = $debit['motif'] ?? '';

    $ok = true;

    // Vérifier que le compte destinataire (débité) n'est pas gelé
    if ($accountModel->isFrozen($toAccountId)) {
        echo sprintf(
            "[%s] ERREUR prélèvement #%d : compte #%d gelé, exécution impossible.\n",
            date('Y-m-d H:i:s'), $debitId, $toAccountId
        );
        $directDebitModel->markFailed($debitId);
        AuditLog::log(null, AuditLog::ACTION_DIRECT_DEBIT_FAIL, ['mandate' => $mandate, 'amount' => $amount, 'reason' => 'frozen'], targetAccountId: $toAccountId);
        $frozenAccount = $accountModel->find($toAccountId);
        if ($frozenAccount) {
            $notifyWithGuardians(
                (int) $frozenAccount['user_id'],
                $toAccountId,
                'direct_debit_failed',
                'Prélèvement #' . $debitId . ' échoué',
                'Le prélèvement (mandat ' . $mandate . ') de ' . number_format($amount, 2, ',', ' ') . ' € n\'a pas pu être exécuté : votre compte « ' . ($frozenAccount['name'] ?? 'Compte #' . $toAccountId) . ' » est actuellement gelé.'
            );
        }
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
            AuditLog::log(null, AuditLog::ACTION_DIRECT_DEBIT_REJECT, ['mandate' => $mandate, 'amount' => $amount, 'reason' => 'insufficient_balance'], targetAccountId: $toAccountId);
            $notifyWithGuardians(
                (int) $toAccount['user_id'],
                $toAccountId,
                'direct_debit_rejected',
                'Prélèvement #' . $debitId . ' rejeté',
                'Le prélèvement (mandat ' . $mandate . ') de ' . number_format($amount, 2, ',', ' ') . ' € a été automatiquement rejeté : solde insuffisant sur votre compte « ' . ($toAccount['name'] ?? 'Compte #' . $toAccountId) . ' » (compte sans autorisation de découvert).'
            );
            $errors++;
            continue;
        }
    }

    // Construire les commentaires détaillés
    $toName   = $toAccount['name'] ?? ('Compte #' . $toAccountId);
    $motifStr = $motif !== '' ? ' — ' . $motif : '';

    if ($fromAccountId !== null) {
        $fromAccount  = $accountModel->find($fromAccountId);
        $fromName     = $fromAccount['name'] ?? ('Compte #' . $fromAccountId);

        // Côté débité : "Prélèvement de <intitulé émetteur> (#<id>) — <motif> (mandat …)"
        $debitComment  = 'Prélèvement de ' . $fromName . ' (#' . $fromAccountId . ')'
                       . $motifStr . ' (mandat ' . $mandate . ')';

        // Côté crédité : "Prélèvement vers <intitulé destinataire> (#<id>) — <motif> (mandat …)"
        $creditComment = 'Prélèvement vers ' . $toName . ' (#' . $toAccountId . ')'
                       . $motifStr . ' (mandat ' . $mandate . ')';
    } else {
        // Pas de compte émetteur (prélèvement banque)
        $fromName      = null;
        $debitComment  = 'Prélèvement bancaire — ' . $toName . ' (#' . $toAccountId . ')'
                       . $motifStr . ' (mandat ' . $mandate . ')';
        $creditComment = null;
    }

    // Créer la transaction de débit sur le compte destinataire
    $toBalanceBefore = $accountModel->getBalance($toAccountId);
    $debitTxId = $transactionModel->addTransaction(
        $toAccountId,
        'expense',
        $amount,
        'Prélèvement',
        $debitComment,
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
                $creditComment,
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
        AuditLog::log(null, AuditLog::ACTION_DIRECT_DEBIT_EXEC, ['mandate' => $mandate, 'amount' => $amount], targetAccountId: $toAccountId);
        // Seuil d'alerte
        if (\App\Models\Account::crossedAlertThreshold($toAccount, $toBalanceBefore, $toBalanceBefore - $amount)) {
            $notifyWithGuardians(
                (int) $toAccount['user_id'], $toAccountId, 'balance_alert',
                'Seuil d\'alerte atteint \u2014 ' . ($toAccount['name'] ?? ''),
                sprintf(
                    'Le solde de votre compte \u00ab %s \u00bb est pass\u00e9 sous le seuil d\'alerte de %s %s. Solde actuel : %s %s.',
                    $toAccount['name'] ?? '',
                    number_format((float) $toAccount['balance_alert_threshold'], 2, ',', ' '),
                    $toAccount['currency'] ?? '',
                    number_format($toBalanceBefore - $amount, 2, ',', ' '),
                    $toAccount['currency'] ?? ''
                )
            );
        }
        $notifyWithGuardians(
            (int) $toAccount['user_id'],
            $toAccountId,
            'direct_debit_success',
            'Prélèvement #' . $debitId . ' exécuté',
            'Le prélèvement (mandat ' . $mandate . ') de ' . number_format($amount, 2, ',', ' ') . ' € a été exécuté sur votre compte « ' . ($toAccount['name'] ?? 'Compte #' . $toAccountId) . ' ».'
        );
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
        AuditLog::log(null, AuditLog::ACTION_DIRECT_DEBIT_FAIL, ['mandate' => $mandate, 'amount' => $amount, 'reason' => 'credit_error'], targetAccountId: $toAccountId);
        $notifyWithGuardians(
            (int) $toAccount['user_id'],
            $toAccountId,
            'direct_debit_failed',
            'Prélèvement #' . $debitId . ' échoué',
            'Le prélèvement (mandat ' . $mandate . ') de ' . number_format($amount, 2, ',', ' ') . ' € n\'a pas pu être exécuté en raison d\'une erreur technique sur votre compte « ' . ($toAccount['name'] ?? 'Compte #' . $toAccountId) . ' ».'
        );
        $errors++;
    }
}

/* ─────────────────────────────────────────────────────────────────
   5. Rétro-compatibilité : transactions planifiées sans virement
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
