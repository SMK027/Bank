<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\LoanSimulation;
use App\Models\Notification;
use App\Models\Transaction;

class LoanController extends Controller
{
    private LoanSimulation  $simModel;
    private Loan            $loanModel;
    private LoanInstallment $installmentModel;
    private Account         $accountModel;
    private Transaction     $transactionModel;
    private Notification    $notifModel;

    public function __construct()
    {
        $this->simModel         = new LoanSimulation();
        $this->loanModel        = new Loan();
        $this->installmentModel = new LoanInstallment();
        $this->accountModel     = new Account();
        $this->transactionModel = new Transaction();
        $this->notifModel       = new Notification();
    }

    // ── Simulateur ────────────────────────────────────────────────────────────

    public function simulatorForm(): void
    {
        $this->requireAuth();
        $userId  = $this->getCurrentUserId();
        $history = $this->simModel->getHistoryForUser($userId, 10);

        $this->render('loans/simulator', [
            'title'      => 'Simulateur de crédits',
            'types'      => LoanSimulation::getTypes(),
            'history'    => $history,
            'simulation' => null,
        ]);
    }

    public function simulate(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $data   = $this->getPostData(['loan_type', 'amount', 'months']);
        $types  = LoanSimulation::getTypes();

        if (empty($data['loan_type']) || !isset($types[$data['loan_type']])) {
            $this->setFlash('danger', 'Type de crédit invalide.');
            $this->redirect('/loans/simulator');
            return;
        }

        $type   = $types[$data['loan_type']];
        $amount = (float) str_replace(',', '.', $data['amount']);
        $months = (int) $data['months'];

        if ($amount < $type['min_amount'] || $amount > $type['max_amount']) {
            $this->setFlash('danger', sprintf(
                'Le montant doit être compris entre %s € et %s € pour ce type de crédit.',
                number_format($type['min_amount'], 0, ',', ' '),
                number_format($type['max_amount'], 0, ',', ' ')
            ));
            $this->redirect('/loans/simulator');
            return;
        }

        if ($months < $type['min_months'] || $months > $type['max_months']) {
            $this->setFlash('danger', sprintf(
                'La durée doit être comprise entre %d et %d mois pour ce type de crédit.',
                $type['min_months'],
                $type['max_months']
            ));
            $this->redirect('/loans/simulator');
            return;
        }

        // --- Calcul ---
        $result = LoanSimulation::calculate($amount, $months, $type['rate']);

        // --- Persistance ---
        $this->simModel->saveSimulation(
            $userId,
            $data['loan_type'],
            $amount,
            $months,
            $type['rate'],
            $result['monthly_payment'],
            $result['total_cost'],
            $result['total_interest']
        );

        $history = $this->simModel->getHistoryForUser($userId, 10);

        $this->render('loans/simulator', [
            'title'      => 'Simulateur de crédits',
            'types'      => $types,
            'history'    => $history,
            'simulation' => [
                'loan_type'       => $data['loan_type'],
                'type_label'      => $type['label'],
                'amount'          => $amount,
                'months'          => $months,
                'annual_rate'     => $type['rate'],
                'monthly_payment' => $result['monthly_payment'],
                'total_cost'      => $result['total_cost'],
                'total_interest'  => $result['total_interest'],
                'amortization'    => $result['amortization'],
            ],
        ]);
    }

    // ── Crédits actifs de l'utilisateur ──────────────────────────────────────

    public function myLoans(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();
        $loans  = $this->loanModel->getForUser($userId);
        $types  = LoanSimulation::getTypes();

        $this->render('loans/my_loans', [
            'title'        => 'Mes crédits',
            'loans'        => $loans,
            'types'        => $types,
            'statusLabels' => Loan::STATUS_LABELS,
            'statusBadge'  => Loan::STATUS_BADGE,
        ]);
    }

    // ── Détail d'un crédit ────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();
        $loanId = (int) $id;
        $loan   = $this->loanModel->getEnriched($loanId);

        if (!$loan || (int) $loan['user_id'] !== $userId) {
            $this->setFlash('danger', 'Crédit introuvable.');
            $this->redirect('/loans');
            return;
        }

        $installments   = $this->installmentModel->getByLoan($loanId);
        $totalScheduled = $this->loanModel->getTotalScheduledInstallments($loanId);
        $remaining      = max(0.0, round(max((float) $loan['amount'], $totalScheduled) - (float) $loan['amount_repaid'], 2));

        $remainingInterest = 0.0;
        foreach ($installments as $inst) {
            if (in_array($inst['status'], [LoanInstallment::STATUS_PENDING, LoanInstallment::STATUS_FAILED], true)) {
                $remainingInterest += (float) ($inst['interest'] ?? 0);
            }
        }
        $remainingInterest = round($remainingInterest, 2);

        $this->render('loans/show', [
            'title'        => 'Crédit #' . $loanId . ' — ' . ($loan['account_name'] ?? ''),
            'loan'         => $loan,
            'installments' => $installments,
            'remaining'         => $remaining,
            'remainingInterest' => $remainingInterest,
            'types'        => LoanSimulation::getTypes(),
            'statusLabels' => Loan::STATUS_LABELS,
            'statusBadge'  => Loan::STATUS_BADGE,
            'iLabels'      => LoanInstallment::STATUS_LABELS,
            'iBadge'       => LoanInstallment::STATUS_BADGE,
            'csrfToken'    => csrf_token(),
        ]);
    }

    // ── Accepter un crédit ────────────────────────────────────────────────────

    public function accept(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $loanId = (int) $id;
        $loan   = $this->loanModel->find($loanId);

        if (!$loan || (int) $loan['user_id'] !== $userId || $loan['status'] !== Loan::STATUS_PENDING) {
            $this->setFlash('danger', 'Action impossible sur ce crédit.');
            $this->redirect('/loans');
            return;
        }

        $account   = $this->accountModel->find((int) $loan['account_id']);
        $types     = LoanSimulation::getTypes();
        $typeLabel = $types[$loan['loan_type']]['label'] ?? $loan['loan_type'];

        // Créditer le compte uniquement si disburse_funds = 1
        $txId = null;
        if (!empty($loan['disburse_funds'])) {
            $txId = $this->transactionModel->addTransaction(
                (int) $loan['account_id'],
                'income',
                (float) $loan['amount'],
                'Autre',
                sprintf('Crédit %s #%d — déblocage des fonds', $typeLabel, $loanId),
                $userId
            );
        }

        $this->loanModel->accept($loanId, $txId);

        AuditLog::log($userId, AuditLog::ACTION_LOAN_ACCEPT, [
            'loan_id'         => $loanId,
            'amount'          => $loan['amount'],
            'disburse_funds'  => !empty($loan['disburse_funds']),
        ], targetAccountId: (int) $loan['account_id']);

        if ($txId !== null) {
            $this->setFlash('success', sprintf(
                'Crédit accepté. Un montant de %s € a été crédité sur votre compte « %s ».',
                number_format((float) $loan['amount'], 2, ',', ' '),
                $account['name'] ?? ''
            ));
        } else {
            $this->setFlash('success', 'Crédit accepté. Les fonds étaient déjà sur votre compte.');
        }
        $this->redirect('/loans/' . $loanId);
    }

    // ── Refuser un crédit ─────────────────────────────────────────────────────

    public function reject(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $loanId = (int) $id;
        $loan   = $this->loanModel->find($loanId);

        if (!$loan || (int) $loan['user_id'] !== $userId || $loan['status'] !== Loan::STATUS_PENDING) {
            $this->setFlash('danger', 'Action impossible sur ce crédit.');
            $this->redirect('/loans');
            return;
        }

        $this->loanModel->reject($loanId);

        AuditLog::log($userId, AuditLog::ACTION_LOAN_REJECT, [
            'loan_id'    => $loanId,
            'account_id' => (int) $loan['account_id'],
        ], targetAccountId: (int) $loan['account_id']);

        $this->setFlash('info', 'Crédit refusé et supprimé.');
        $this->redirect('/loans');
    }
}
