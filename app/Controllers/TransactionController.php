<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
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

    public function __construct()
    {
        $this->accountModel = new Account();
        $this->transactionModel = new Transaction();
        $this->userModel = new User();
        $this->notifModel = new Notification();
        $this->guardianshipModel = new Guardianship();
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

        // Traiter la date programmée (dépenses à venir uniquement)
        $scheduledAt = null;
        if (!empty($data['scheduled_at'])) {
            $ts = strtotime($data['scheduled_at']);
            if ($ts === false || $ts <= time()) {
                $this->setFlash('danger', 'La date programmée doit être dans le futur.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            $scheduledAt = date('Y-m-d H:i:s', $ts);
        }

        // Bloquer les opérations sortantes si le compte est gelé
        if ($data['type'] === 'expense' && $this->accountModel->isFrozen($accId)) {
            $this->setFlash('danger', 'Ce compte est gelé. Les opérations sortantes sont impossibles.');
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
}
