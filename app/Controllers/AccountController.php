<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\AccountAccess;
use App\Models\User;

class AccountController extends Controller
{
    private Account $accountModel;
    private Transaction $transactionModel;
    private AccountAccess $accessModel;
    private User $userModel;

    public function __construct()
    {
        $this->accountModel = new Account();
        $this->transactionModel = new Transaction();
        $this->accessModel = new AccountAccess();
        $this->userModel = new User();
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $this->render('accounts/create', [
            'title' => 'Créer un compte bancaire',
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $data = $this->getPostData(['name', 'currency', 'overdraft']);

        if (empty($data['name']) || empty($data['currency'])) {
            $this->setFlash('danger', 'Le nom et la devise sont requis.');
            $this->redirect('/accounts/create');
            return;
        }

        $overdraft = abs((float) ($data['overdraft'] ?: 0));

        $this->accountModel->createAccount(
            $this->getCurrentUserId(),
            $data['name'],
            $data['currency'],
            $overdraft
        );

        $this->setFlash('success', 'Compte bancaire créé avec succès !');
        $this->redirect('/dashboard');
    }

    public function show(string $id): void
    {
        $this->requireAuth();
        $accountId = (int) $id;
        $userId = $this->getCurrentUserId();

        $account = $this->accountModel->find($accountId);
        if (!$account || !$this->accountModel->hasAccess($accountId, $userId)) {
            $this->setFlash('danger', 'Compte introuvable ou accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $transactions = $this->transactionModel->getByAccount($accountId);
        // Enrichir chaque transaction avec le nom de l'auteur
        foreach ($transactions as &$t) {
            $author = isset($t['user_id']) && $t['user_id'] ? $this->userModel->find((int) $t['user_id']) : null;
            $t['author_name'] = $author ? $author['username'] : 'Inconnu';
        }
        unset($t);
        $balance = $this->accountModel->getBalance($accountId);
        $totalIncome = $this->transactionModel->getTotalIncome($accountId);
        $totalExpense = $this->transactionModel->getTotalExpense($accountId);
        $isOwner = $this->accountModel->isOwner($accountId, $userId);

        // Récupérer les accès partagés
        $accesses = [];
        if ($isOwner) {
            $rawAccesses = $this->accessModel->getAccessesForAccount($accountId);
            foreach ($rawAccesses as &$access) {
                $user = $this->userModel->find((int) $access['user_id']);
                $access['username'] = $user ? $user['username'] : 'Inconnu';
            }
            unset($access);
            $accesses = $rawAccesses;
        }

        // Récupérer le propriétaire
        $owner = $this->userModel->find((int) $account['user_id']);

        $this->render('accounts/show', [
            'title'        => $account['name'],
            'account'      => $account,
            'transactions' => $transactions,
            'balance'      => $balance,
            'totalIncome'  => $totalIncome,
            'totalExpense' => $totalExpense,
            'isOwner'      => $isOwner,
            'accesses'     => $accesses,
            'owner'        => $owner,
            'categories'   => Transaction::CATEGORIES,
        ]);
    }

    public function editForm(string $id): void
    {
        $this->requireAuth();
        $accountId = (int) $id;
        $userId = $this->getCurrentUserId();

        $account = $this->accountModel->find($accountId);
        if (!$account || !$this->accountModel->isOwner($accountId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $this->render('accounts/edit', [
            'title'   => 'Modifier le compte',
            'account' => $account,
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $accountId = (int) $id;
        $userId = $this->getCurrentUserId();

        if (!$this->accountModel->isOwner($accountId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $data = $this->getPostData(['name', 'currency', 'overdraft']);

        if (empty($data['name']) || empty($data['currency'])) {
            $this->setFlash('danger', 'Le nom et la devise sont requis.');
            $this->redirect('/accounts/' . $id . '/edit');
            return;
        }

        $this->accountModel->update($accountId, [
            'name'      => $data['name'],
            'currency'  => $data['currency'],
            'overdraft' => abs((float) ($data['overdraft'] ?: 0)),
        ]);

        $this->setFlash('success', 'Compte modifié avec succès.');
        $this->redirect('/accounts/' . $id);
    }

    public function deleteAccount(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $accountId = (int) $id;
        $userId = $this->getCurrentUserId();

        if (!$this->accountModel->isOwner($accountId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        // Supprimer les transactions associées
        $transactions = $this->transactionModel->getByAccount($accountId);
        foreach ($transactions as $t) {
            $this->transactionModel->delete((int) $t['id']);
        }

        // Supprimer les accès partagés
        $accesses = $this->accessModel->getAccessesForAccount($accountId);
        foreach ($accesses as $a) {
            $this->accessModel->delete((int) $a['id']);
        }

        $this->accountModel->delete($accountId);
        $this->setFlash('success', 'Compte supprimé.');
        $this->redirect('/dashboard');
    }
}
