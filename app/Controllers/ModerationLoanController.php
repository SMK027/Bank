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
use App\Models\User;

/**
 * Gestion des crédits côté modération.
 *
 * Routes :
 *   GET  /moderation/loans                  → liste
 *   GET  /moderation/loans/create           → formulaire d'octroi
 *   POST /moderation/loans                  → enregistrer l'octroi
 *   GET  /moderation/loans/{id}             → détail + échéancier
 *   POST /moderation/loans/{id}/rate        → modifier le taux
 *   POST /moderation/loans/{id}/installments        → ajouter une échéance
 *   POST /moderation/loans/{id}/installments/{iid}/cancel → annuler une échéance
 */
class ModerationLoanController extends Controller
{
    private Loan            $loanModel;
    private LoanInstallment $installmentModel;
    private Account         $accountModel;
    private Transaction     $transactionModel;
    private Notification    $notifModel;
    private User            $userModel;

    public function __construct()
    {
        $this->loanModel        = new Loan();
        $this->installmentModel = new LoanInstallment();
        $this->accountModel     = new Account();
        $this->transactionModel = new Transaction();
        $this->notifModel       = new Notification();
        $this->userModel        = new User();
    }

    // ── Liste ────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireModerator();

        $loans = $this->loanModel->getAllEnriched();

        $this->render('moderation/loans/index', [
            'title'  => 'Crédits — Modération',
            'loans'  => $loans,
            'types'  => LoanSimulation::getTypes(),
            'statusLabels' => Loan::STATUS_LABELS,
            'statusBadge'  => Loan::STATUS_BADGE,
        ]);
    }

    // ── Formulaire d'octroi ───────────────────────────────────────────────────

    public function createForm(): void
    {
        $this->requireModerator();

        $preAccountId = isset($_GET['account_id']) ? (int) $_GET['account_id'] : null;
        $account      = $preAccountId ? $this->accountModel->find($preAccountId) : null;

        $this->render('moderation/loans/create', [
            'title'     => 'Octroyer un crédit',
            'types'     => LoanSimulation::getTypes(),
            'account'   => $account,
            'csrfToken' => csrf_token(),
        ]);
    }

    // ── Enregistrement ────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $modId = $this->getCurrentUserId();
        $data  = $this->getPostData(['account_id', 'loan_type', 'amount', 'annual_rate', 'notes']);

        $accountId  = (int) $data['account_id'];
        $account    = $this->accountModel->find($accountId);

        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/moderation/loans/create');
            return;
        }

        $types     = LoanSimulation::getTypes();
        $loanType  = $data['loan_type'];
        if (!isset($types[$loanType])) {
            $this->setFlash('danger', 'Type de crédit invalide.');
            $this->redirect('/moderation/loans/create');
            return;
        }

        $amount    = (float) str_replace(',', '.', $data['amount']);
        $rate      = (float) str_replace(',', '.', $data['annual_rate']);
        $notes     = trim($data['notes']);

        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant doit être positif.');
            $this->redirect('/moderation/loans/create');
            return;
        }

        if ($rate < 0 || $rate > 100) {
            $this->setFlash('danger', 'Le taux annuel doit être compris entre 0 et 100 %.');
            $this->redirect('/moderation/loans/create');
            return;
        }

        $ownerId = (int) $account['user_id'];
        $loanId  = $this->loanModel->grant(
            $accountId,
            $ownerId,
            $loanType,
            $amount,
            $rate,
            $modId,
            $notes ?: null
        );

        // Notification à l'utilisateur
        $typeLabel = $types[$loanType]['label'];
        $this->notifModel->notify(
            $ownerId,
            'loan_offered',
            'Offre de crédit reçue',
            sprintf(
                'Un crédit %s de %s € à %s %% vous a été proposé sur votre compte « %s ». Veuillez l\'accepter ou le refuser.',
                $typeLabel,
                number_format($amount, 2, ',', ' '),
                number_format($rate, 2, ',', ' '),
                $account['name']
            ),
            '/loans/' . $loanId
        );

        AuditLog::log($modId, AuditLog::ACTION_LOAN_GRANT, [
            'loan_id'   => $loanId,
            'type'      => $loanType,
            'amount'    => $amount,
            'rate'      => $rate,
            'account'   => $account['name'],
        ], targetAccountId: $accountId);

        $this->setFlash('success', 'Crédit octroyé avec succès. L\'utilisateur doit maintenant l\'accepter.');
        $this->redirect('/moderation/loans/' . $loanId);
    }

    // ── Détail ────────────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requireModerator();

        $loan = $this->loanModel->getEnriched((int) $id);
        if (!$loan) {
            $this->setFlash('danger', 'Crédit introuvable.');
            $this->redirect('/moderation/loans');
            return;
        }

        $installments = $this->installmentModel->getByLoan((int) $id);
        $totalScheduled = $this->loanModel->getTotalScheduledInstallments((int) $id);
        $remaining      = round((float) $loan['amount'] - (float) $loan['amount_repaid'], 2);
        $schedulable    = round((float) $loan['amount'] - $totalScheduled, 2);

        $this->render('moderation/loans/show', [
            'title'          => 'Crédit #' . $id . ' — ' . $loan['owner_username'],
            'loan'           => $loan,
            'installments'   => $installments,
            'totalScheduled' => $totalScheduled,
            'remaining'      => $remaining,
            'schedulable'    => $schedulable,
            'types'          => LoanSimulation::getTypes(),
            'statusLabels'   => Loan::STATUS_LABELS,
            'statusBadge'    => Loan::STATUS_BADGE,
            'iLabels'        => LoanInstallment::STATUS_LABELS,
            'iBadge'         => LoanInstallment::STATUS_BADGE,
            'csrfToken'      => csrf_token(),
        ]);
    }

    // ── Modifier le taux ──────────────────────────────────────────────────────

    public function updateRate(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $loanId = (int) $id;
        $loan   = $this->loanModel->find($loanId);

        if (!$loan || $loan['status'] === Loan::STATUS_CLOSED || $loan['status'] === Loan::STATUS_REJECTED) {
            $this->setFlash('danger', 'Impossible de modifier ce crédit.');
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        $rate = (float) str_replace(',', '.', $_POST['annual_rate'] ?? '');
        if ($rate < 0 || $rate > 100) {
            $this->setFlash('danger', 'Taux invalide.');
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        $this->loanModel->updateRate($loanId, $rate);

        $modId = $this->getCurrentUserId();
        AuditLog::log($modId, AuditLog::ACTION_LOAN_RATE_UPDATE, [
            'loan_id'  => $loanId,
            'new_rate' => $rate,
        ], targetAccountId: (int) $loan['account_id']);

        $this->setFlash('success', 'Taux mis à jour avec succès.');
        $this->redirect('/moderation/loans/' . $loanId);
    }

    // ── Ajouter une échéance ──────────────────────────────────────────────────

    public function addInstallment(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $loanId = (int) $id;
        $loan   = $this->loanModel->find($loanId);

        if (!$loan || !in_array($loan['status'], [Loan::STATUS_PENDING, Loan::STATUS_ACTIVE], true)) {
            $this->setFlash('danger', 'Impossible d\'ajouter une échéance à ce crédit.');
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        $data    = $this->getPostData(['due_date', 'amount']);
        $dueDate = trim($data['due_date']);
        $amount  = (float) str_replace(',', '.', $data['amount']);

        if (!$dueDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $this->setFlash('danger', 'Date d\'échéance invalide.');
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant de l\'échéance doit être positif.');
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        // Vérifier que le total des échéances ne dépasse pas le montant total
        $totalScheduled = $this->loanModel->getTotalScheduledInstallments($loanId);
        if (round($totalScheduled + $amount, 2) > (float) $loan['amount']) {
            $this->setFlash('danger', sprintf(
                'Le total des échéances (%s €) dépasserait le montant total du crédit (%s €).',
                number_format($totalScheduled + $amount, 2, ',', ' '),
                number_format((float) $loan['amount'], 2, ',', ' ')
            ));
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        $this->installmentModel->addInstallment($loanId, $dueDate, $amount);

        $this->setFlash('success', 'Échéance ajoutée.');
        $this->redirect('/moderation/loans/' . $loanId);
    }

    // ── Annuler une échéance ─────────────────────────────────────────────────

    public function cancelInstallment(string $id, string $iid): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $loanId        = (int) $id;
        $installmentId = (int) $iid;

        $installment = $this->installmentModel->find($installmentId);
        if (!$installment || (int) $installment['loan_id'] !== $loanId) {
            $this->setFlash('danger', 'Échéance introuvable.');
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        if ($installment['status'] !== LoanInstallment::STATUS_PENDING) {
            $this->setFlash('danger', 'Seules les échéances en attente peuvent être annulées.');
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        $this->installmentModel->cancel($installmentId);
        $this->setFlash('success', 'Échéance annulée.');
        $this->redirect('/moderation/loans/' . $loanId);
    }
}
