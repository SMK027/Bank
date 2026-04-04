<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\Transaction;

class TransactionController extends Controller
{
    private Account $accountModel;
    private Transaction $transactionModel;

    public function __construct()
    {
        $this->accountModel = new Account();
        $this->transactionModel = new Transaction();
    }

    public function create(string $accountId): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $accId = (int) $accountId;
        $userId = $this->getCurrentUserId();

        if (!$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $data = $this->getPostData(['type', 'amount', 'category', 'comment']);

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

        // Vérifier le découvert pour les dépenses
        if ($data['type'] === 'expense') {
            $account = $this->accountModel->find($accId);
            $balance = $this->accountModel->getBalance($accId);
            $overdraft = (float) ($account['overdraft'] ?? 0);
            if (($balance - $amount) < -$overdraft) {
                $this->setFlash('danger', 'Opération refusée : le découvert autorisé serait dépassé.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
        }

        $this->transactionModel->addTransaction(
            $accId,
            $data['type'],
            $amount,
            $data['category'],
            $data['comment']
        );

        $label = $data['type'] === 'income' ? 'Entrée' : 'Dépense';
        $this->setFlash('success', $label . ' enregistrée avec succès.');
        $this->redirect('/accounts/' . $accountId);
    }

    public function deleteTransaction(string $accountId, string $transactionId): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $accId = (int) $accountId;
        $userId = $this->getCurrentUserId();

        if (!$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $transaction = $this->transactionModel->find((int) $transactionId);
        if (!$transaction || (int) $transaction['account_id'] !== $accId) {
            $this->setFlash('danger', 'Transaction introuvable.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $this->transactionModel->delete((int) $transactionId);
        $this->setFlash('success', 'Transaction supprimée.');
        $this->redirect('/accounts/' . $accountId);
    }
}
