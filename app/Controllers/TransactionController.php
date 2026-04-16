<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\DeferredDebit;
use App\Models\Guardianship;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;

class TransactionController extends Controller
{
    private Account $accountModel;
    private Transaction $transactionModel;
    private User $userModel;
    private Notification $notifModel;
    private Guardianship $guardianshipModel;
    private DeferredDebit $deferredDebitModel;

    public function __construct()
    {
        $this->accountModel       = new Account();
        $this->transactionModel   = new Transaction();
        $this->userModel          = new User();
        $this->notifModel         = new Notification();
        $this->guardianshipModel  = new Guardianship();
        $this->deferredDebitModel = new DeferredDebit();
    }

    public function create(string $accountId): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $accId = (int) $accountId;
        $userId = $this->getCurrentUserId();

        if (!$this->isModerator() && !$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $data = $this->getPostData(['type', 'amount', 'category', 'comment', 'scheduled_at']);

        if (empty($data['type']) || empty($data['amount']) || empty($data['category'])) {
            $this->setFlash('danger', 'Le type, le montant et la catégorie sont requis.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        if (!in_array($data['type'], ['income', 'expense'], true)) {
            $this->setFlash('danger', 'Type de transaction invalide.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $amount = abs((float) $data['amount']);
        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant doit être positif.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Traiter la date programmée
        $scheduledAt = null;
        if (!empty($data['scheduled_at'])) {
            $dt = parse_datetime_input($data['scheduled_at']);
            if (!$dt) {
                $this->setFlash('danger', 'La date programmée est invalide (format attendu : jj/mm/aaaa hh:mm).');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            if (!$this->isModerator() && $dt->getTimestamp() <= time()) {
                $this->setFlash('danger', 'La date programmée doit être dans le futur.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            $scheduledAt = $dt->format('Y-m-d H:i:s');
        }

        // Bloquer les opérations sortantes si le compte est gelé
        if ($data['type'] === 'expense' && $this->accountModel->isFrozen($accId)) {
            $this->setFlash('danger', 'Ce compte est gelé. Les opérations sortantes sont impossibles.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Bloquer toutes les opérations manuelles si le compte est désactivé (hors modérateurs)
        if ($this->accountModel->isDisabled($accId) && !$this->isModerator()) {
            $this->setFlash('danger', 'Ce compte est en cours de résiliation. Aucune opération manuelle n\'est possible.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Vérifier le découvert pour les dépenses (ignoré pour les modérateurs)
        // On utilise le solde futur (incl. opérations programmées) pour le contrôle
        if ($data['type'] === 'expense' && !$this->isModerator()) {
            $account   = $this->accountModel->find($accId);
            $balance   = $this->accountModel->getFutureBalance($accId);
            $overdraft = (float) ($account['overdraft'] ?? 0);
            $accountType = $account['type'] ?? 'standard';
            $wouldExceed = ($balance - $amount) < -$overdraft;

            if ($wouldExceed) {
                // Bloquer définitivement si le type de compte interdit le découvert
                if (!\App\Models\Account::typeAllowsOverdraft($accountType)) {
                    $this->setFlash('danger', 'Opération impossible : ce type de compte (' . (\App\Models\Account::TYPES[$accountType]['label'] ?? $accountType) . ') ne permet pas le solde négatif.');
                    $this->redirect('/accounts/' . $accountId);
                    return;
                }

                $force = trim($_POST['force_overdraft'] ?? '') === '1';
                if (!$force) {
                    $newBalance = number_format($balance - $amount, 2, ',', ' ');
                    $this->setFlash('danger', sprintf(
                        'Découvert dépassé. Solde prévu : %s %s. Cochez la case « Forcer l\'opération » pour confirmer.',
                        $newBalance,
                        $account['currency'] ?? ''
                    ));
                    $this->redirect('/accounts/' . $accountId);
                    return;
                }
            }
        }

        // Vérifier le plafond d'épargne pour les entrées
        if ($data['type'] === 'income') {
            $account = $account ?? $this->accountModel->find($accId);
            if ($account && Account::typeHasCap($account['type'] ?? '')) {
                $tCap = (float) ($account['cap'] ?? 0);
                if ($tCap > 0) {
                    $currentBalance = $this->accountModel->getFutureBalance($accId);
                    if ($currentBalance + $amount > $tCap) {
                        $this->setFlash('danger', sprintf(
                            'Opération impossible : ce compte épargne a un plafond de %s %s. Solde actuel : %s %s.',
                            number_format($tCap, 2, ',', ' '),
                            $account['currency'],
                            number_format($currentBalance, 2, ',', ' '),
                            $account['currency']
                        ));
                        $this->redirect('/accounts/' . $accountId);
                        return;
                    }
                }
            }
        }

        // Seuil d'alerte : capturer le solde actuel avant l'opération (dépense immédiate uniquement)
        $balanceBefore = ($data['type'] === 'expense' && $scheduledAt === null)
            ? $this->accountModel->getBalance($accId)
            : 0.0;

        $this->transactionModel->addTransaction(
            $accId,
            $data['type'],
            $amount,
            $data['category'],
            $data['comment'],
            $userId,
            $scheduledAt
        );

        // Vérification du franchissement du seuil d'alerte (dépense immédiate uniquement)
        if ($data['type'] === 'expense' && $scheduledAt === null) {
            $alertAccount = $account ?? $this->accountModel->find($accId);
            if (Account::crossedAlertThreshold($alertAccount, $balanceBefore, $balanceBefore - $amount)) {
                $this->notifModel->sendBalanceAlert(
                    (int) $alertAccount['user_id'],
                    $alertAccount,
                    $balanceBefore - $amount,
                    $this->guardianshipModel
                );
            }
        }

        $label = $data['type'] === 'income' ? 'Entrée' : 'Dépense';
        $msg   = $scheduledAt
            ? $label . ' programmée pour le ' . date('d/m/Y H:i', strtotime($scheduledAt)) . '.'
            : $label . ' enregistrée avec succès.';
        $this->setFlash('success', $msg);
        $this->redirect('/accounts/' . $accountId);
    }

    public function deleteTransaction(string $accountId, string $transactionId): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $accId = (int) $accountId;
        $userId = $this->getCurrentUserId();

        if (!$this->isModerator()) {
            $this->setFlash('danger', 'Seul un modérateur peut supprimer une opération.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $transaction = $this->transactionModel->find((int) $transactionId);
        if (!$transaction || (int) $transaction['account_id'] !== $accId) {
            $this->setFlash('danger', 'Transaction introuvable.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        if ($this->transactionModel->getProtectedIds([(int) $transactionId]) !== []) {
            $this->setFlash('danger', 'Cette transaction est liée à un virement ou un prélèvement automatique. Pour l\'annuler, utilisez la gestion dédiée (annulation du virement ou rejet du prélèvement).');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $this->transactionModel->delete((int) $transactionId);
        $this->setFlash('success', 'Transaction supprimée.');
        $this->redirect('/accounts/' . $accountId);
    }

    // ── DÉBITS DIFFÉRÉS ─────────────────────────────────────────────────────

    public function createDeferredDebit(string $accountId): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $accId  = (int) $accountId;
        $userId = $this->getCurrentUserId();

        if (!$this->isModerator() && !$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $account = $this->accountModel->find($accId);
        if (!$account || empty($account['deferred_debit_enabled'])) {
            $this->setFlash('danger', 'Le débit différé n\'est pas activé sur ce compte.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Bloquer si compte gelé
        if ($this->accountModel->isFrozen($accId)) {
            $this->setFlash('danger', 'Ce compte est gelé. Les opérations sortantes sont impossibles.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Bloquer si compte désactivé (hors modérateurs)
        if ($this->accountModel->isDisabled($accId) && !$this->isModerator()) {
            $this->setFlash('danger', 'Ce compte est en cours de résiliation.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $data = $this->getPostData(['amount', 'category', 'comment', 'operation_date', 'period_end_date']);

        if (empty($data['amount']) || empty($data['category'])) {
            $this->setFlash('danger', 'Le montant et la catégorie sont requis.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $amount = abs((float) $data['amount']);
        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant doit être positif.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Date de l'opération
        $operationDate = null;
        if (!empty($data['operation_date'])) {
            $dtOp = parse_datetime_input($data['operation_date']);
            if (!$dtOp) {
                $this->setFlash('danger', 'La date de l\'opération est invalide (format : jj/mm/aaaa hh:mm).');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            $operationDate = $dtOp->format('Y-m-d H:i:s');
        } else {
            $operationDate = date('Y-m-d H:i:s');
        }

        // Date de fin de période (obligatoire, doit être dans le futur)
        if (empty($data['period_end_date'])) {
            $this->setFlash('danger', 'La date de fin de période est requise.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }
        $dtPeriod = parse_datetime_input($data['period_end_date']);
        if (!$dtPeriod) {
            $this->setFlash('danger', 'La date de fin de période est invalide (format : jj/mm/aaaa).');
            $this->redirect('/accounts/' . $accountId);
            return;
        }
        if ($dtPeriod->format('Y-m-d') < date('Y-m-d')) {
            $this->setFlash('danger', 'La date de fin de période doit être aujourd\'hui ou dans le futur.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }
        $periodEndDate = $dtPeriod->format('Y-m-d');

        $this->deferredDebitModel->createDeferredDebit(
            $accId,
            $userId,
            $amount,
            $data['category'],
            $data['comment'] ?? '',
            $operationDate,
            $periodEndDate
        );

        $this->setFlash('success', sprintf(
            'Opération à débit différé enregistrée (%.2f €). Sera débitée le %s.',
            $amount,
            $dtPeriod->format('d/m/Y')
        ));
        $this->redirect('/accounts/' . $accountId);
    }

    public function cancelDeferredDebit(string $accountId, string $debitId): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $accId = (int) $accountId;
        $ddId  = (int) $debitId;
        $userId = $this->getCurrentUserId();

        if (!$this->isModerator() && !$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $dd = $this->deferredDebitModel->find($ddId);
        if (!$dd || (int) $dd['account_id'] !== $accId || $dd['status'] !== DeferredDebit::STATUS_PENDING) {
            $this->setFlash('danger', 'Opération introuvable ou déjà traitée.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $this->deferredDebitModel->cancel($ddId);
        $this->setFlash('success', 'Opération à débit différé annulée.');
        $this->redirect('/accounts/' . $accountId);
    }

    /**
     * Modifier les dates d'une opération à débit différé en attente.
     */
    public function editDeferredDebit(string $accountId, string $debitId): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $accId  = (int) $accountId;
        $ddId   = (int) $debitId;
        $userId = $this->getCurrentUserId();

        if (!$this->isModerator() && !$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $dd = $this->deferredDebitModel->find($ddId);
        if (!$dd || (int) $dd['account_id'] !== $accId) {
            $this->setFlash('danger', 'Opération introuvable.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $isExecuted = $dd['status'] === DeferredDebit::STATUS_EXECUTED;
        $isPending  = $dd['status'] === DeferredDebit::STATUS_PENDING;

        if (!$isPending && !$isExecuted) {
            $this->setFlash('danger', 'Cette opération ne peut plus être modifiée.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Les utilisateurs ne peuvent modifier les DD exécutés que dans les 7 jours
        if ($isExecuted && !$this->isModerator()) {
            $executedAt = strtotime($dd['executed_at'] ?? '');
            if (!$executedAt || (time() - $executedAt) > 7 * 86400) {
                $this->setFlash('danger', 'Cette opération a été exécutée il y a plus de 7 jours et ne peut plus être modifiée.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
        }

        $data = $this->getPostData(['operation_date', 'period_end_date']);
        $updates = [];

        // Date d'opération
        if (!empty($data['operation_date'])) {
            $dtOp = parse_datetime_input($data['operation_date']);
            if (!$dtOp) {
                $this->setFlash('danger', 'Date d\'opération invalide (format : jj/mm/aaaa hh:mm).');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            $updates['operation_date'] = $dtOp->format('Y-m-d H:i:s');
        }

        // Date de fin de période
        if (!empty($data['period_end_date'])) {
            $dtPeriod = parse_datetime_input($data['period_end_date']);
            if (!$dtPeriod) {
                $this->setFlash('danger', 'Date de fin de période invalide (format : jj/mm/aaaa).');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            // Pour les DD en attente, la date doit être dans le futur
            if ($isPending && $dtPeriod->format('Y-m-d') < date('Y-m-d')) {
                $this->setFlash('danger', 'La date de fin de période doit être aujourd\'hui ou dans le futur.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            $updates['period_end_date'] = $dtPeriod->format('Y-m-d');
        }

        if (empty($updates)) {
            $this->setFlash('warning', 'Aucune modification apportée.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $this->deferredDebitModel->update($ddId, $updates);

        // Pour les DD exécutés, mettre à jour la transaction liée
        if ($isExecuted && !empty($dd['transaction_id']) && isset($updates['operation_date'])) {
            $this->transactionModel->update((int) $dd['transaction_id'], [
                'created_at' => $updates['operation_date'],
            ]);
        }

        $this->setFlash('success', 'Opération à débit différé modifiée.');
        $this->redirect('/accounts/' . $accountId);
    }
}
