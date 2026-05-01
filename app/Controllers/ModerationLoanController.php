<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Guardianship;
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
 *   GET  /moderation/loans                                      → liste
 *   GET  /moderation/loans/create                               → formulaire d'octroi
 *   POST /moderation/loans                                      → enregistrer l'octroi
 *   GET  /moderation/loans/{id}                                 → détail + échéancier
 *   POST /moderation/loans/{id}/rate                            → modifier le taux
 *   POST /moderation/loans/{id}/installments                    → ajouter une échéance
 *   POST /moderation/loans/{id}/installments/{iid}/cancel       → annuler une échéance
 *   POST /moderation/loans/{id}/installments/{iid}/refund       → rembourser une mensualité
 *   POST /moderation/loans/{id}/cancel                          → annuler le crédit
 *   POST /moderation/loans/process-installments                 → exécution manuelle du cron
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
            'csrfToken'    => csrf_token(),
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
        $data  = $this->getPostData(['account_id', 'loan_type', 'amount', 'annual_rate', 'notes', 'disburse_funds']);

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

        $amount        = (float) str_replace(',', '.', $data['amount']);
        $rate          = (float) str_replace(',', '.', $data['annual_rate']);
        $notes         = trim($data['notes']);
        $disburseFunds = !empty($data['disburse_funds']);

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
            $notes ?: null,
            $disburseFunds
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

        $installments        = $this->installmentModel->getByLoan((int) $id);
        $totalScheduled      = $this->loanModel->getTotalScheduledInstallments((int) $id);
        $schedulable         = $this->loanModel->getSchedulablePrincipal((int) $id);
        $scheduledPrincipal  = round((float) $loan['amount'] - $schedulable, 2);
        $totalInterest       = max(0.0, round($totalScheduled - $scheduledPrincipal, 2));
        $remaining           = max(0.0, round($totalScheduled - (float) $loan['amount_repaid'], 2));
        $rate                = round((float) $loan['annual_rate'] / 100.0, 8);

        $this->render('moderation/loans/show', [
            'title'          => 'Crédit #' . $id . ' — ' . $loan['owner_username'],
            'loan'           => $loan,
            'installments'   => $installments,
            'totalScheduled' => $totalScheduled,
            'totalInterest'  => $totalInterest,
            'remaining'      => $remaining,
            'schedulable'    => $schedulable,
            'rate'           => $rate,
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

        // Recalcul des intérêts (flat rate) sur toutes les mensualités en attente
        $recalcCount  = $this->installmentModel->recalculateInterestForPending($loanId, $rate);

        $modId = $this->getCurrentUserId();
        AuditLog::log($modId, AuditLog::ACTION_LOAN_RATE_UPDATE, [
            'loan_id'          => $loanId,
            'new_rate'         => $rate,
            'installments_recalculated' => $recalcCount,
        ], targetAccountId: (int) $loan['account_id']);

        $this->setFlash('success', sprintf(
            'Taux mis à jour (%s %%)%s.',
            number_format($rate, 2, ',', ' '),
            $recalcCount > 0 ? sprintf(' — %d mensualité(s) recalculée(s)', $recalcCount) : ''
        ));
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

        // Décomposition capital / intérêts (flat rate annuel)
        $schedulablePrincipal = $this->loanModel->getSchedulablePrincipal($loanId);
        if ($schedulablePrincipal <= 0) {
            $this->setFlash('danger', 'Tout le capital du crédit est déjà planifié.');
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        $rate      = (float) $loan['annual_rate'] / 100.0;
        $principal = round($amount / (1 + $rate), 2);
        $interest  = round($amount - $principal, 2);

        if ($principal <= 0) {
            $this->setFlash('danger', 'Montant invalide.');
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        if ($principal > $schedulablePrincipal + 0.005) {
            $this->setFlash('danger', sprintf(
                'La part capital (%s €) dépasse le capital restant à planifier (%s €).',
                number_format($principal, 2, ',', ' '),
                number_format($schedulablePrincipal, 2, ',', ' ')
            ));
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        $this->installmentModel->addInstallment($loanId, $dueDate, $principal, $interest);

        $this->setFlash('success', sprintf(
            'Échéance ajoutée : %s € (%s € capital + %s € intérêts).',
            number_format($amount, 2, ',', ' '),
            number_format($principal, 2, ',', ' '),
            number_format($interest, 2, ',', ' ')
        ));
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

    // ── Annulation d'un crédit par la modération ─────────────────────────────

    public function cancelLoan(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $modId  = $this->getCurrentUserId();
        $loanId = (int) $id;
        $loan   = $this->loanModel->find($loanId);

        if (!$loan || in_array($loan['status'], [Loan::STATUS_CANCELLED, Loan::STATUS_CLOSED, Loan::STATUS_REJECTED], true)) {
            $this->setFlash('danger', 'Ce crédit ne peut pas être annulé.');
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        $accountId = (int) $loan['account_id'];
        $userId    = (int) $loan['user_id'];
        $amount    = (float) $loan['amount'];
        $typeLabel = LoanSimulation::getTypes()[$loan['loan_type']]['label'] ?? $loan['loan_type'];

        // ── 1. Si les fonds ont été versés lors de l'acceptation, les récupérer ──
        $debitTxId = null;
        if (!empty($loan['disburse_funds']) && !empty($loan['credit_tx_id'])) {
            $debitTxId = $this->transactionModel->addTransaction(
                $accountId,
                'expense',
                $amount,
                'Autre',
                sprintf('Annulation crédit %s #%d — récupération des fonds', $typeLabel, $loanId),
                $modId
            );
        }

        // ── 2. Rembourser chaque mensualité déjà payée ───────────────────────
        $paidInstallments = $this->installmentModel->getPaidByLoan($loanId);
        $refundTotal = 0.0;
        foreach ($paidInstallments as $inst) {
            $instAmount = (float) $inst['amount'];
            $refundTxId = $this->transactionModel->addTransaction(
                $accountId,
                'income',
                $instAmount,
                'Autre',
                sprintf('Remboursement mensualité #%d — annulation crédit #%d', (int) $inst['id'], $loanId),
                $modId
            );
            $this->installmentModel->markRefunded((int) $inst['id'], $refundTxId);
            $this->loanModel->decrementRepaid($loanId, $instAmount);
            $refundTotal += $instAmount;
        }

        // ── 3. Annuler les mensualités encore en attente ─────────────────────
        $pendingInstallments = $this->installmentModel->findBy(['loan_id' => $loanId, 'status' => LoanInstallment::STATUS_PENDING]);
        foreach ($pendingInstallments as $inst) {
            $this->installmentModel->cancel((int) $inst['id']);
        }

        // ── 4. Marquer le crédit comme annulé ────────────────────────────────
        $this->loanModel->cancel($loanId, $debitTxId);

        // ── 5. Recalcul de sécurité : amount_repaid depuis les mensualités ──
        //      (filet de sécurité contre tout décalage numérique ou appel partiel)
        $this->loanModel->recalculateAmountRepaid($loanId);

        // ── 5. Audit + notification ──────────────────────────────────────────
        AuditLog::log($modId, AuditLog::ACTION_LOAN_CANCELLED, [
            'loan_id'          => $loanId,
            'amount'           => $amount,
            'debit_recovery'   => $debitTxId !== null,
            'refund_total'     => $refundTotal,
            'installments_refunded' => count($paidInstallments),
        ], targetAccountId: $accountId);

        $notifBody = sprintf(
            'Votre crédit %s #%d a été annulé par la modération.',
            $typeLabel,
            $loanId
        );
        if ($debitTxId !== null) {
            $notifBody .= sprintf(' Un débit de %s € a été effectué pour récupérer les fonds versés.', number_format($amount, 2, ',', ' '));
        }
        if ($refundTotal > 0) {
            $notifBody .= sprintf(' %s € de mensualités ont été remboursés.', number_format($refundTotal, 2, ',', ' '));
        }
        $this->notifModel->notify($userId, 'loan_closed', 'Crédit annulé', $notifBody, '/loans');

        $this->setFlash('warning', sprintf(
            'Crédit #%d annulé.%s%s',
            $loanId,
            $debitTxId !== null ? sprintf(' Débit de %s € effectué.', number_format($amount, 2, ',', ' ')) : '',
            $refundTotal > 0 ? sprintf(' %s € remboursés (%d mensualité(s)).', number_format($refundTotal, 2, ',', ' '), count($paidInstallments)) : ''
        ));
        $this->redirect('/moderation/loans');
    }

    // ── Remboursement d'une mensualité ────────────────────────────────────────

    public function refundInstallment(string $id, string $iid): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $modId         = $this->getCurrentUserId();
        $loanId        = (int) $id;
        $installmentId = (int) $iid;

        $installment = $this->installmentModel->find($installmentId);
        if (!$installment || (int) $installment['loan_id'] !== $loanId) {
            $this->setFlash('danger', 'Échéance introuvable.');
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        if ($installment['status'] !== LoanInstallment::STATUS_PAID) {
            $this->setFlash('danger', 'Seules les mensualités payées peuvent être remboursées.');
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        $loan = $this->loanModel->find($loanId);
        if (!$loan || !in_array($loan['status'], [Loan::STATUS_ACTIVE, Loan::STATUS_CLOSED], true)) {
            $this->setFlash('danger', 'Crédit introuvable ou dans un état qui ne permet pas le remboursement.');
            $this->redirect('/moderation/loans/' . $loanId);
            return;
        }

        $accountId  = (int) $loan['account_id'];
        $userId     = (int) $loan['user_id'];
        $amount     = (float) $installment['amount'];
        $typeLabel  = LoanSimulation::getTypes()[$loan['loan_type']]['label'] ?? $loan['loan_type'];

        $refundTxId = $this->transactionModel->addTransaction(
            $accountId,
            'income',
            $amount,
            'Autre',
            sprintf('Remboursement mensualité #%d — crédit %s #%d', $installmentId, $typeLabel, $loanId),
            $modId
        );

        $this->installmentModel->markRefunded($installmentId, $refundTxId);
        $this->loanModel->decrementRepaid($loanId, $amount);
        $reopened = $this->loanModel->reopenIfNeeded($loanId);

        AuditLog::log($modId, AuditLog::ACTION_LOAN_INSTALLMENT_REFUNDED, [
            'installment_id' => $installmentId,
            'loan_id'        => $loanId,
            'amount'         => $amount,
            'refund_tx_id'   => $refundTxId,
            'loan_reopened'  => $reopened,
        ], targetAccountId: $accountId);

        $this->notifModel->notify(
            $userId,
            'loan_installment_due',
            'Mensualité remboursée',
            sprintf(
                'La mensualité de %s € du %s (crédit %s #%d) vous a été remboursée par la modération.',
                number_format($amount, 2, ',', ' '),
                date('d/m/Y', strtotime($installment['due_date'])),
                $typeLabel,
                $loanId
            ),
            '/loans/' . $loanId
        );

        $this->setFlash('success', sprintf(
            'Mensualité de %s € remboursée.%s',
            number_format($amount, 2, ',', ' '),
            $reopened ? ' Le crédit a été réouvert (mensualités restantes à payer).' : ''
        ));
        $this->redirect('/moderation/loans/' . $loanId);
    }

    // ── Exécution manuelle des mensualités ───────────────────────────────────

    public function processInstallments(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $guardianshipModel = new Guardianship();

        $notifyWithGuardians = function (
            int    $userId,
            string $type,
            string $title,
            string $body
        ) use ($guardianshipModel): void {
            $link = '/loans';
            $this->notifModel->notify($userId, $type, $title, $body, $link);
            foreach ($guardianshipModel->getGuardiansOf($userId) as $g) {
                $this->notifModel->notify((int) $g['guardian_user_id'], $type, $title, $body, $link);
            }
        };

        $dueInstallments = $this->installmentModel->getDue();

        if (empty($dueInstallments)) {
            $this->setFlash('info', 'Aucune mensualité échue à traiter.');
            $this->redirect('/moderation/loans');
            return;
        }

        $executed = 0;
        $failed   = 0;

        foreach ($dueInstallments as $inst) {
            $installmentId = (int) $inst['id'];
            $loanId        = (int) $inst['loan_id'];
            $accountId     = (int) $inst['account_id'];
            $userId        = (int) $inst['user_id'];
            $amount        = (float) $inst['amount'];
            $dueDate       = $inst['due_date'];
            $currency      = $inst['currency'] ?? 'EUR';
            $accountName   = $inst['account_name'] ?? ('Compte #' . $accountId);

            $account = $this->accountModel->find($accountId);
            if (!$account) {
                $this->installmentModel->markFailed($installmentId);
                $failed++;
                continue;
            }

            if (in_array($account['status'] ?? '', ['frozen', 'disabled'], true)) {
                $this->installmentModel->markFailed($installmentId);
                AuditLog::log(null, AuditLog::ACTION_LOAN_INSTALLMENT_FAILED, [
                    'installment_id' => $installmentId,
                    'loan_id'        => $loanId,
                    'reason'         => 'compte ' . $account['status'],
                ], targetAccountId: $accountId);
                $notifyWithGuardians($userId,
                    'loan_installment_failed',
                    'Mensualité de crédit échouée',
                    sprintf(
                        'Le prélèvement de %s %s du %s (crédit #%d) a échoué : votre compte « %s » est %s.',
                        number_format($amount, 2, ',', ' '),
                        $currency,
                        date('d/m/Y', strtotime($dueDate)),
                        $loanId,
                        $accountName,
                        $account['status'] === 'frozen' ? 'gelé' : 'désactivé'
                    )
                );
                $failed++;
                continue;
            }

            $balance = $this->accountModel->getBalance($accountId);
            if ($balance < $amount) {
                $this->installmentModel->markFailed($installmentId);
                AuditLog::log(null, AuditLog::ACTION_LOAN_INSTALLMENT_FAILED, [
                    'installment_id' => $installmentId,
                    'loan_id'        => $loanId,
                    'balance'        => $balance,
                    'required'       => $amount,
                ], targetAccountId: $accountId);
                $notifyWithGuardians($userId,
                    'loan_installment_failed',
                    'Mensualité de crédit échouée — solde insuffisant',
                    sprintf(
                        'Le prélèvement de %s %s du %s (crédit #%d) a échoué faute de provision sur le compte « %s ».',
                        number_format($amount, 2, ',', ' '),
                        $currency,
                        date('d/m/Y', strtotime($dueDate)),
                        $loanId,
                        $accountName
                    )
                );
                $failed++;
                continue;
            }

            try {
                $txId = $this->transactionModel->addTransaction(
                    $accountId,
                    'expense',
                    $amount,
                    'Autre',
                    sprintf('Remboursement crédit #%d — échéance du %s', $loanId, date('d/m/Y', strtotime($dueDate))),
                    $userId
                );

                $this->installmentModel->markPaid($installmentId, $txId);
                $closed = $this->loanModel->recordRepayment($loanId, $amount);

                AuditLog::log(null, AuditLog::ACTION_LOAN_INSTALLMENT_PAID, [
                    'installment_id' => $installmentId,
                    'loan_id'        => $loanId,
                    'amount'         => $amount,
                    'tx_id'          => $txId,
                ], targetAccountId: $accountId);

                $notifyWithGuardians($userId,
                    'loan_installment_due',
                    'Mensualité de crédit prélevée',
                    sprintf(
                        'Un montant de %s %s a été prélevé sur votre compte « %s » au titre du crédit #%d.',
                        number_format($amount, 2, ',', ' '),
                        $currency,
                        $accountName,
                        $loanId
                    )
                );

                if ($closed) {
                    AuditLog::log(null, AuditLog::ACTION_LOAN_CLOSED, [
                        'loan_id' => $loanId,
                    ], targetAccountId: $accountId);
                    $notifyWithGuardians($userId,
                        'loan_closed',
                        'Crédit soldé !',
                        sprintf('Félicitations ! Votre crédit #%d sur le compte « %s » est entièrement remboursé.', $loanId, $accountName)
                    );
                }

                $executed++;

            } catch (\Throwable) {
                try { $this->installmentModel->markFailed($installmentId); } catch (\Throwable) {}
                $failed++;
            }
        }

        $msg = sprintf('%d mensualité(s) traitée(s)', $executed);
        if ($failed > 0) {
            $msg .= sprintf(', %d échec(s)', $failed);
            $this->setFlash('warning', $msg . '.');
        } else {
            $this->setFlash('success', $msg . '.');
        }
        $this->redirect('/moderation/loans');
    }
}
