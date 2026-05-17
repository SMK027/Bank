<?php

declare(strict_types=1);

/**
 * Script CLI : clôture des comptes désactivés en fin de mois.
 *
 * Un compte est éligible à la suppression définitive si :
 *   disabled_at IS NOT NULL
 *   ET disabled_at < premier jour du mois courant
 *   (c'est-à-dire que le mois de désactivation est révolu.)
 *
 * Pour chaque compte éligible :
 *   1. Annuler les mandats actifs (émetteur OU destinataire).
 *   2. Annuler les virements récurrents actifs (émetteur OU destinataire).
 *   3. Annuler les prélèvements planifiés restants (to_account = compte).
 *   4. Supprimer les transactions du compte.
 *   5. Supprimer les accès partagés du compte.
 *   6. Supprimer le compte.
 *   7. Journaliser dans AuditLog.
 *
 * Usage manuel : php database/process_account_closures.php
 * Forçage     : php database/process_account_closures.php --force
 *               (clôture immédiate de TOUS les comptes désactivés, sans attendre la fin du mois)
 * Cron : 0 2 * * * (quotidien à 02h00 — efficace à partir du 1er du mois suivant)
 *
 * Ce script peut aussi être inclus depuis un contrôleur en définissant
 * la variable $forceClose = true avant l'inclusion pour forcer la clôture.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Europe/Paris');

use App\Models\Account;
use App\Models\AccountAccess;
use App\Models\AuditLog;
use App\Models\DirectDebit;
use App\Models\Guardianship;
use App\Models\Loan;
use App\Models\Mandate;
use App\Models\Notification;
use App\Models\RecurringTransfer;
use App\Models\Transaction;

$accountModel           = new Account();
$transactionModel       = new Transaction();
$accessModel            = new AccountAccess();
$directDebitModel       = new DirectDebit();
$mandateModel           = new Mandate();
$recurringTransferModel = new RecurringTransfer();
$notifModel             = new Notification();
$guardianshipModel      = new Guardianship();
$loanModel              = new Loan();

$closed = 0;
$errors = 0;

// Détection du mode forçage :
//   - via inclusion : $forceClose = true; require '...';
//   - via CLI       : php process_account_closures.php --force
$force = !empty($forceClose)
    || (PHP_SAPI === 'cli' && isset($argv) && in_array('--force', $argv, true));

$accounts = $accountModel->getEligibleForClosure($force);

if ($force) {
    echo sprintf("[%s] Mode FORÇAGE activé : clôture immédiate des comptes désactivés.\n", date('Y-m-d H:i:s'));
}

if (empty($accounts)) {
    echo sprintf("[%s] Aucun compte à clôturer.\n", date('Y-m-d H:i:s'));
    return;
}

foreach ($accounts as $account) {
    $accountId = (int) $account['id'];
    $userId    = (int) $account['user_id'];
    $name      = $account['name'] ?? ('Compte #' . $accountId);

    echo sprintf(
        "[%s] Clôture du compte #%d « %s » (désactivé le %s)…\n",
        date('Y-m-d H:i:s'), $accountId, $name, $account['disabled_at']
    );

    try {
        /* ── 1. Annuler les mandats actifs liés à ce compte ────────────────── */
        $allMandates = $mandateModel->findAll();
        foreach ($allMandates as $m) {
            if (($m['status'] ?? '') !== Mandate::STATUS_ACTIVE) continue;
            if ((int) $m['emitter_account_id'] !== $accountId
                && (int) $m['recipient_account_id'] !== $accountId) {
                continue;
            }
            $mandateModel->revoke((int) $m['id']);
            echo sprintf("  → Mandat #%d (%s) révoqué.\n", (int) $m['id'], $m['number'] ?? '?');
        }

        /* ── 2. Supprimer tous les virements récurrents liés à ce compte ───── */
        foreach ($recurringTransferModel->getByAccount($accountId) as $rt) {
            $recurringTransferModel->delete((int) $rt['id']);
            echo sprintf("  → Virement récurrent #%d supprimé.\n", (int) $rt['id']);
        }

        /* ── 3. Annuler les prélèvements planifiés restants (to_account) ───── */
        $pendingDebits = $directDebitModel->findBy(
            ['to_account_id' => $accountId, 'status' => DirectDebit::STATUS_SCHEDULED]
        );
        foreach ($pendingDebits as $dd) {
            $directDebitModel->markCancelled((int) $dd['id']);
            echo sprintf("  → Prélèvement planifié #%d annulé.\n", (int) $dd['id']);
        }

        /* ── 4. Supprimer les transactions du compte ─────────────────────── */
        $transactions = $transactionModel->getByAccount($accountId);
        foreach ($transactions as $t) {
            $transactionModel->delete((int) $t['id']);
        }
        echo sprintf("  → %d transaction(s) supprimée(s).\n", count($transactions));

        /* ── 5. Supprimer les accès partagés ─────────────────────────────── */
        $accesses = $accessModel->getAccessesForAccount($accountId);
        foreach ($accesses as $a) {
            $accessModel->delete((int) $a['id']);
        }

        /* ── 5bis. Détecter les crédits actifs qui vont devenir orphelins ─
         * Grâce à la contrainte ON DELETE SET NULL sur loans.account_id, le
         * crédit est conservé. On notifie le contractant et les modérateurs
         * pour que ces derniers puissent réaffecter un autre compte de
         * prélèvement avant la prochaine échéance.
         */
        $loansToOrphan = $loanModel->findBy(['account_id' => $accountId]);
        $activeOrphanedIds = [];
        foreach ($loansToOrphan as $l) {
            if (in_array($l['status'] ?? '', [Loan::STATUS_ACTIVE, Loan::STATUS_PENDING], true)) {
                $activeOrphanedIds[] = (int) $l['id'];
            }
        }
        if (!empty($activeOrphanedIds)) {
            echo sprintf("  → %d crédit(s) actif(s) conservé(s) — compte de prélèvement à réaffecter par la modération : #%s\n",
                count($activeOrphanedIds),
                implode(', #', $activeOrphanedIds)
            );
        }

        /* ── 6. Supprimer le compte ─────────────────────────────────────── */
        $accountModel->delete($accountId);

        /* ── 7. Journaliser ─────────────────────────────────────────────── */
        AuditLog::log(
            0,
            AuditLog::ACTION_ACCOUNT_CLOSE,
            [
                'name'        => $name,
                'disabled_at' => $account['disabled_at'],
                'closed_at'   => date('Y-m-d H:i:s'),
            ],
            targetAccountId: $accountId
        );

        // Notifier le propriétaire (si le compte utilisateur existe encore)
        $notifModel->notify(
            $userId,
            'account_closed',
            'Compte « ' . $name . ' » supprimé',
            'Votre compte bancaire a été définitivement supprimé conformément à votre demande de résiliation du ' . date('d/m/Y', strtotime($account['disabled_at'])) . '.'
        );
        // Notifier également les tuteurs légaux éventuels
        foreach ($guardianshipModel->getGuardiansOf($userId) as $g) {
            $notifModel->notify(
                (int) $g['guardian_user_id'],
                'account_closed',
                'Compte « ' . $name . ' » supprimé',
                'Le compte bancaire dont vous étiez responsable légal a été définitivement supprimé.'
            );
        }

        // Notifier l'utilisateur et les modérateurs si des crédits sont devenus orphelins
        if (!empty($activeOrphanedIds)) {
            $loanList = '#' . implode(', #', $activeOrphanedIds);
            $notifModel->notify(
                $userId,
                'loan_account_orphaned',
                'Compte de prélèvement à réaffecter pour vos crédits',
                'La suppression du compte « ' . $name . ' » a laissé '
                . count($activeOrphanedIds) . ' crédit(s) actif(s) sans compte de prélèvement ('
                . $loanList . '). La modération vous contactera pour réaffecter ces prélèvements '
                . 'à un autre de vos comptes.',
                '/loans'
            );
            $notifModel->notifyModerators(
                'loan_account_orphaned',
                'Crédit(s) à réaffecter suite à la clôture d\'un compte',
                'Le compte « ' . $name . ' » (utilisateur #' . $userId . ') a été supprimé. '
                . count($activeOrphanedIds) . ' crédit(s) actif(s) doivent être réaffectés à un autre compte du contractant : '
                . $loanList . '.',
                '/moderation/loans'
            );
            AuditLog::log(
                0,
                AuditLog::ACTION_ACCOUNT_CLOSE,
                [
                    'name'              => $name,
                    'event'             => 'loans_orphaned',
                    'orphaned_loan_ids' => $activeOrphanedIds,
                ],
                targetAccountId: $accountId,
                targetUserId: $userId
            );
        }

        $closed++;
        echo sprintf("[%s] Compte #%d « %s » clôturé avec succès.\n", date('Y-m-d H:i:s'), $accountId, $name);
    } catch (\Throwable $e) {
        $errors++;
        echo sprintf(
            "[%s] ERREUR lors de la clôture du compte #%d : %s\n",
            date('Y-m-d H:i:s'), $accountId, $e->getMessage()
        );
    }
}

echo sprintf(
    "\n[%s] Résumé : %d compte(s) clôturé(s), %d erreur(s).\n",
    date('Y-m-d H:i:s'), $closed, $errors
);

// Variables exposées pour les appelants (inclusion depuis un contrôleur)
$result = ['closed' => $closed, 'errors' => $errors, 'forced' => $force];
