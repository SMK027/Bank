<?php

declare(strict_types=1);

/**
 * Script CLI : exécution des échéances de crédits échues.
 *
 * Lancé automatiquement par cron (ex. toutes les heures ou tous les jours).
 *
 * Pour chaque échéance dont la date est <= aujourd'hui et le statut = 'pending' :
 *   - Vérifie que le compte n'est pas gelé/désactivé
 *   - Crée une transaction de débit sur le compte
 *   - Marque l'échéance comme payée (ou échouée si solde insuffisant)
 *   - Met à jour le montant remboursé sur le crédit
 *   - Clôture le crédit automatiquement s'il est soldé
 *   - Notifie l'utilisateur + ses tuteurs éventuels
 *   - Journalise dans l'audit log
 *
 * Usage manuel : docker compose exec app php database/process_loan_installments.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Europe/Paris');

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Guardianship;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Notification;
use App\Models\Transaction;

$loanModel        = new Loan();
$installmentModel = new LoanInstallment();
$accountModel     = new Account();
$transactionModel = new Transaction();
$notifModel       = new Notification();
$guardianshipModel = new Guardianship();

// ── Utilitaire notification + tuteurs ─────────────────────────────────────
$notifyWithGuardians = function(
    int    $userId,
    int    $accountId,
    string $type,
    string $title,
    string $body
) use ($notifModel, $guardianshipModel): void {
    $link = '/loans';
    $notifModel->notify($userId, $type, $title, $body, $link);
    foreach ($guardianshipModel->getGuardiansOf($userId) as $g) {
        $notifModel->notify((int) $g['guardian_user_id'], $type, $title, $body, $link);
    }
};

$executed = 0;
$errors   = 0;

$dueInstallments = $installmentModel->getDue();

if (empty($dueInstallments)) {
    echo sprintf("[%s] Aucune échéance de crédit à traiter.\n", date('Y-m-d H:i:s'));
    exit(0);
}

echo sprintf("[%s] %d échéance(s) à traiter.\n", date('Y-m-d H:i:s'), count($dueInstallments));

foreach ($dueInstallments as $inst) {
    $installmentId = (int) $inst['id'];
    $loanId        = (int) $inst['loan_id'];
    $accountId     = (int) $inst['account_id'];
    $userId        = (int) $inst['user_id'];
    $amount        = (float) $inst['amount'];
    $dueDate       = $inst['due_date'];
    $currency      = $inst['currency'] ?? 'EUR';
    $accountName   = $inst['account_name'] ?? ('Compte #' . $accountId);

    // ── Vérifications préalables ────────────────────────────────────────
    $account = $accountModel->find($accountId);
    if (!$account) {
        echo sprintf("[%s] ERREUR échéance #%d : compte #%d introuvable — ignoré.\n",
            date('Y-m-d H:i:s'), $installmentId, $accountId);
        $errors++;
        continue;
    }

    if ($accountModel->isFrozen($accountId) || $accountModel->isDisabled($accountId)) {
        $isFrozen = $accountModel->isFrozen($accountId);
        $reason   = $isFrozen ? 'gelé' : 'en résiliation';
        echo sprintf("[%s] ERREUR échéance #%d : compte #%d %s — marquée échouée.\n",
            date('Y-m-d H:i:s'), $installmentId, $accountId, $reason);

        $installmentModel->markFailed($installmentId);
        AuditLog::log(null, AuditLog::ACTION_LOAN_INSTALLMENT_FAILED, [
            'installment_id' => $installmentId,
            'loan_id'        => $loanId,
            'reason'         => 'compte_' . ($isFrozen ? 'gelé' : 'résiliation'),
        ], targetAccountId: $accountId);

        $notifyWithGuardians($userId, $accountId,
            'loan_installment_failed',
            'Mensualité de crédit échouée',
            sprintf(
                'Le prélèvement de %s %s du %s (crédit #%d) a échoué : votre compte « %s » est actuellement %s.',
                number_format($amount, 2, ',', ' '),
                $currency,
                date('d/m/Y', strtotime($dueDate)),
                $loanId,
                $accountName,
                $reason
            )
        );
        $errors++;
        continue;
    }

    // ── Vérifier solde suffisant (découvert autorisé inclus) ─────────────
    $balance   = $accountModel->getBalance($accountId);
    $overdraft = (float) ($account['overdraft'] ?? 0.0);
    if ($balance + $overdraft < $amount) {
        echo sprintf("[%s] INSUFFISANT échéance #%d : solde %.2f (découvert %.2f) < mensualité %.2f — marquée échouée.\n",
            date('Y-m-d H:i:s'), $installmentId, $balance, $overdraft, $amount);

        $installmentModel->markFailed($installmentId);
        AuditLog::log(null, AuditLog::ACTION_LOAN_INSTALLMENT_FAILED, [
            'installment_id' => $installmentId,
            'loan_id'        => $loanId,
            'balance'        => $balance,
            'required'       => $amount,
        ], targetAccountId: $accountId);

        $notifyWithGuardians($userId, $accountId,
            'loan_installment_failed',
            'Mensualité de crédit échouée — solde insuffisant',
            sprintf(
                'Le prélèvement de %s %s du %s (crédit #%d) a échoué faute de provision. Veuillez approvisionner votre compte « %s ».',
                number_format($amount, 2, ',', ' '),
                $currency,
                date('d/m/Y', strtotime($dueDate)),
                $loanId,
                $accountName
            )
        );
        $errors++;
        continue;
    }

    // ── Créer la transaction de débit ─────────────────────────────────────
    try {
        $txId = $transactionModel->addTransaction(
            $accountId,
            'expense',
            $amount,
            'Autre',
            sprintf('Remboursement crédit #%d — échéance du %s', $loanId, date('d/m/Y', strtotime($dueDate))),
            $userId
        );

        $installmentModel->markPaid($installmentId, $txId);

        // Met à jour amount_repaid et clôture si soldé
        $closed = $loanModel->recordRepayment($loanId, $amount);

        AuditLog::log(null, AuditLog::ACTION_LOAN_INSTALLMENT_PAID, [
            'installment_id' => $installmentId,
            'loan_id'        => $loanId,
            'amount'         => $amount,
            'tx_id'          => $txId,
        ], targetAccountId: $accountId);

        $notifyWithGuardians($userId, $accountId,
            'loan_installment_due',
            'Mensualité de crédit prélevée',
            sprintf(
                'Un montant de %s %s a été prélevé le %s sur votre compte « %s » au titre du remboursement du crédit #%d.',
                number_format($amount, 2, ',', ' '),
                $currency,
                date('d/m/Y'),
                $accountName,
                $loanId
            )
        );

        if ($closed) {
            AuditLog::log(null, AuditLog::ACTION_LOAN_CLOSED, [
                'loan_id' => $loanId,
            ], targetAccountId: $accountId);

            $notifyWithGuardians($userId, $accountId,
                'loan_closed',
                'Crédit soldé !',
                sprintf(
                    'Félicitations ! Votre crédit #%d sur le compte « %s » est entièrement remboursé.',
                    $loanId,
                    $accountName
                )
            );

            echo sprintf("[%s] Crédit #%d soldé.\n", date('Y-m-d H:i:s'), $loanId);
        }

        echo sprintf("[%s] OK échéance #%d (crédit #%d) — %.2f %s prélevés.\n",
            date('Y-m-d H:i:s'), $installmentId, $loanId, $amount, $currency);
        $executed++;

    } catch (\Throwable $e) {
        echo sprintf("[%s] EXCEPTION échéance #%d : %s\n",
            date('Y-m-d H:i:s'), $installmentId, $e->getMessage());

        try {
            $installmentModel->markFailed($installmentId);
        } catch (\Throwable) {}

        $errors++;
    }
}

echo sprintf(
    "[%s] Terminé. %d exécutée(s), %d erreur(s).\n",
    date('Y-m-d H:i:s'), $executed, $errors
);
exit($errors > 0 ? 1 : 0);
