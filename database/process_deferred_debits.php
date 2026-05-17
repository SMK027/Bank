<?php

declare(strict_types=1);

/**
 * Script CLI : exécution des débits différés échus (fin de période).
 *
 * Pour chaque débit différé dont period_end_date <= aujourd'hui :
 *   - Annulation si le compte est introuvable, gelé ou désactivé.
 *   - Rejet pour solde insuffisant si le type de compte n'autorise pas le découvert.
 *   - Sinon, création d'une transaction de dépense et marquage comme exécuté.
 *
 * Une notification de synthèse unique est envoyée par couple (compte, période).
 *
 * Usage manuel : php database/process_deferred_debits.php
 *
 * Ce script peut aussi être inclus depuis un contrôleur ; il expose à la fin
 * un tableau associatif `$result = ['executed' => int, 'errors' => int]`.
 *
 * Remarque : la même logique est exécutée par le CRON principal
 * (database/process_scheduled.php, section 5) toutes les minutes. Ce script
 * dédié permet à la modération de forcer le traitement manuellement, et
 * peut aussi être planifié indépendamment si besoin.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Europe/Paris');

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\DeferredDebit;
use App\Models\Guardianship;
use App\Models\Notification;
use App\Models\Transaction;

$accountModel       = new Account();
$transactionModel   = new Transaction();
$deferredDebitModel = new DeferredDebit();
$notifModel         = new Notification();
$guardianshipModel  = new Guardianship();

/**
 * Notifie le propriétaire d'un compte ET ses éventuels responsables légaux.
 */
$notifyWithGuardians = function (
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

$executed = 0;
$errors   = 0;

$dueDeferredDebits = $deferredDebitModel->getDue();

// Regrouper par compte + date de fin de période pour n'envoyer qu'une notification de synthèse
$ddGroups = [];
foreach ($dueDeferredDebits as $dd) {
    $groupKey              = $dd['account_id'] . '_' . $dd['period_end_date'];
    $ddGroups[$groupKey][] = $dd;
}

foreach ($ddGroups as $groupDebits) {
    $ddAccountId   = (int) $groupDebits[0]['account_id'];
    $periodEndDate = $groupDebits[0]['period_end_date'];
    $ddAccount     = $accountModel->find($ddAccountId);

    $groupSuccess = 0;
    $groupFails   = [];

    // Compte introuvable → annulation de tous les débits du groupe, sans notification
    if (!$ddAccount) {
        foreach ($groupDebits as $dd) {
            $ddId = (int) $dd['id'];
            echo sprintf("[%s] ERREUR débit différé #%d : compte #%d introuvable.\n",
                date('Y-m-d H:i:s'), $ddId, $ddAccountId);
            $deferredDebitModel->cancel($ddId);
            $errors++;
        }
        continue;
    }

    foreach ($groupDebits as $dd) {
        $ddId       = (int) $dd['id'];
        $ddUserId   = (int) $dd['user_id'];
        $ddAmount   = (float) $dd['amount'];
        $ddCategory = $dd['category'];
        $ddComment  = $dd['comment'] ?? '';

        // Compte gelé → annulation
        if ($accountModel->isFrozen($ddAccountId)) {
            echo sprintf("[%s] ERREUR débit différé #%d : compte #%d gelé.\n",
                date('Y-m-d H:i:s'), $ddId, $ddAccountId);
            $deferredDebitModel->cancel($ddId);
            AuditLog::log(null, 'deferred_debit.cancel', ['deferred_debit_id' => $ddId, 'reason' => 'frozen'], targetAccountId: $ddAccountId);
            $groupFails[] = ['reason' => 'frozen', 'amount' => $ddAmount, 'category' => $ddCategory];
            $errors++;
            continue;
        }

        // Compte désactivé → annulation
        if (!empty($ddAccount['disabled_at'])) {
            echo sprintf("[%s] ERREUR débit différé #%d : compte #%d désactivé.\n",
                date('Y-m-d H:i:s'), $ddId, $ddAccountId);
            $deferredDebitModel->cancel($ddId);
            AuditLog::log(null, 'deferred_debit.cancel', ['deferred_debit_id' => $ddId, 'reason' => 'disabled'], targetAccountId: $ddAccountId);
            $groupFails[] = ['reason' => 'disabled', 'amount' => $ddAmount, 'category' => $ddCategory];
            $errors++;
            continue;
        }

        // Rejet si le type de compte n'autorise pas le découvert et solde insuffisant
        $ddType = $ddAccount['type'] ?? 'standard';
        if (!Account::typeAllowsOverdraft($ddType)) {
            $currentBalance = $accountModel->getBalance($ddAccountId);
            if ($currentBalance - $ddAmount < 0) {
                echo sprintf(
                    "[%s] REJET débit différé #%d : compte #%d (type '%s') solde insuffisant (%.2f < %.2f).\n",
                    date('Y-m-d H:i:s'), $ddId, $ddAccountId, $ddType, $currentBalance, $ddAmount
                );
                $deferredDebitModel->cancel($ddId);
                AuditLog::log(null, 'deferred_debit.reject', ['deferred_debit_id' => $ddId, 'amount' => $ddAmount, 'reason' => 'insufficient_balance'], targetAccountId: $ddAccountId);
                $groupFails[] = ['reason' => 'insufficient_balance', 'amount' => $ddAmount, 'category' => $ddCategory];
                $errors++;
                continue;
            }
        }

        // Créer la transaction de dépense
        $commentFull   = 'Débit différé — ' . $ddCategory . ($ddComment !== '' ? ' — ' . $ddComment : '');
        $balanceBefore = $accountModel->getBalance($ddAccountId);
        $txId = $transactionModel->addTransaction(
            $ddAccountId,
            'expense',
            $ddAmount,
            $ddCategory,
            $commentFull,
            $ddUserId
        );

        if ($txId) {
            $deferredDebitModel->markExecuted($ddId, $txId);
            AuditLog::log(null, 'deferred_debit.exec', ['deferred_debit_id' => $ddId, 'amount' => $ddAmount, 'tx_id' => $txId], targetAccountId: $ddAccountId);

            // Seuil d'alerte (notification individuelle maintenue car critique)
            if (\App\Models\Account::crossedAlertThreshold($ddAccount, $balanceBefore, $balanceBefore - $ddAmount)) {
                $notifyWithGuardians(
                    (int) $ddAccount['user_id'], $ddAccountId, 'balance_alert',
                    'Seuil d\'alerte atteint — ' . ($ddAccount['name'] ?? ''),
                    sprintf(
                        'Le solde de votre compte « %s » est passé sous le seuil d\'alerte de %s %s. Solde actuel : %s %s.',
                        $ddAccount['name'] ?? '',
                        number_format((float) $ddAccount['balance_alert_threshold'], 2, ',', ' '),
                        $ddAccount['currency'] ?? '',
                        number_format($balanceBefore - $ddAmount, 2, ',', ' '),
                        $ddAccount['currency'] ?? ''
                    )
                );
            }

            $groupSuccess++;
            $executed++;
            echo sprintf(
                "[%s] Débit différé #%d exécuté : compte #%d — %.2f — %s\n",
                date('Y-m-d H:i:s'), $ddId, $ddAccountId, $ddAmount, $ddCategory
            );
        } else {
            $deferredDebitModel->cancel($ddId);
            AuditLog::log(null, 'deferred_debit.fail', ['deferred_debit_id' => $ddId, 'amount' => $ddAmount, 'reason' => 'tx_creation_failed'], targetAccountId: $ddAccountId);
            $groupFails[] = ['reason' => 'tx_creation_failed', 'amount' => $ddAmount, 'category' => $ddCategory];
            $errors++;
            echo sprintf("[%s] ERREUR débit différé #%d : création transaction échouée.\n",
                date('Y-m-d H:i:s'), $ddId);
        }
    }

    // Notification de synthèse unique par compte + période
    $accountName   = $ddAccount['name'] ?? 'Compte #' . $ddAccountId;
    $periodDisplay = date('d/m/Y', strtotime($periodEndDate));
    $totalCount    = count($groupDebits);
    $failCount     = count($groupFails);

    $bodyLines = [
        sprintf('Fin de période du %s pour le compte « %s » :', $periodDisplay, $accountName),
        sprintf('• %d opération(s) exécutée(s) avec succès sur %d.', $groupSuccess, $totalCount),
    ];

    if ($failCount > 0) {
        $bodyLines[] = sprintf('• %d opération(s) en échec :', $failCount);
        foreach ($groupFails as $f) {
            $reasonLabel = match ($f['reason']) {
                'frozen'               => 'compte gelé',
                'disabled'             => 'compte désactivé',
                'insufficient_balance' => 'solde insuffisant',
                default                => 'erreur technique',
            };
            $bodyLines[] = sprintf('  – %.2f € (%s) : %s.',
                $f['amount'], $f['category'], $reasonLabel);
        }
    }

    $notifyWithGuardians(
        (int) $ddAccount['user_id'], $ddAccountId, 'deferred_debit_period_end',
        sprintf('Clôture de période — débits différés du %s', $periodDisplay),
        implode("\n", $bodyLines)
    );
}

echo sprintf(
    "[%s] Débits différés : %d exécuté(s), %d erreur(s).\n",
    date('Y-m-d H:i:s'), $executed, $errors
);

$result = ['executed' => $executed, 'errors' => $errors];
