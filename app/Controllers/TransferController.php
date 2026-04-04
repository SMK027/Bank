<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\Transaction;

class TransferController extends Controller
{
    private Account $accountModel;
    private Transaction $transactionModel;

    public function __construct()
    {
        $this->accountModel     = new Account();
        $this->transactionModel = new Transaction();
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $userId   = $this->getCurrentUserId();
        $accounts = $this->accountModel->getAccessibleAccounts($userId);

        // Enrichir chaque compte avec son solde calculé
        foreach ($accounts['own'] as &$acc) {
            $acc['balance'] = $this->accountModel->getBalance((int) $acc['id']);
        }
        unset($acc);
        foreach ($accounts['shared'] as &$acc) {
            $acc['balance'] = $this->accountModel->getBalance((int) $acc['id']);
        }
        unset($acc);

        // Pré-sélection du compte émetteur si passé en GET
        $preselect = isset($_GET['from']) ? (int) $_GET['from'] : null;

        $this->render('transfers/create', [
            'title'      => 'Virement entre comptes',
            'ownAccounts'    => $accounts['own'],
            'sharedAccounts' => $accounts['shared'],
            'preselect'  => $preselect,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $data   = $this->getPostData(['from_account_id', 'to_account_id', 'amount', 'motif']);

        $fromId = (int) $data['from_account_id'];
        $toId   = (int) $data['to_account_id'];

        // Comptes identiques
        if ($fromId === $toId) {
            $this->setFlash('danger', 'Le compte émetteur et le compte destinataire doivent être différents.');
            $this->redirect('/transfers/create');
            return;
        }

        // Montant strictement positif
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant doit être strictement positif.');
            $this->redirect('/transfers/create');
            return;
        }

        // Vérifier l'accès au compte émetteur (doit avoir accès)
        if (!$this->accountModel->hasAccess($fromId, $userId)) {
            $this->setFlash('danger', 'Accès refusé au compte émetteur.');
            $this->redirect('/transfers/create');
            return;
        }

        // Vérifier que le compte destinataire existe et est accessible
        $toAccount = $this->accountModel->find($toId);
        if (!$toAccount || !$this->accountModel->hasAccess($toId, $userId)) {
            $this->setFlash('danger', 'Compte destinataire introuvable ou accès refusé.');
            $this->redirect('/transfers/create');
            return;
        }

        $fromAccount = $this->accountModel->find($fromId);
        $balance     = $this->accountModel->getBalance($fromId);
        $overdraft   = (float) ($fromAccount['overdraft'] ?? 0);
        $accountType = $fromAccount['type'] ?? 'standard';

        // Vérifier si le solde sera insuffisant
        $newBalance = $balance - $amount;
        if ($newBalance < -$overdraft) {
            if (!Account::typeAllowsOverdraft($accountType)) {
                $this->setFlash('danger', sprintf(
                    'Virement impossible : le compte émetteur (%s) ne permet pas le solde négatif. Solde disponible : %s %s.',
                    $fromAccount['name'],
                    number_format($balance, 2, ',', ' '),
                    $fromAccount['currency']
                ));
            } else {
                $this->setFlash('danger', sprintf(
                    'Fonds insuffisants : le virement dépasserait le découvert autorisé. Solde disponible : %s %s (découvert : %s %s).',
                    number_format($balance, 2, ',', ' '),
                    $fromAccount['currency'],
                    number_format($overdraft, 2, ',', ' '),
                    $fromAccount['currency']
                ));
            }
            $this->redirect('/transfers/create?from=' . $fromId);
            return;
        }

        $motif = trim($data['motif'] ?: '');
        $label = 'Virement' . ($motif !== '' ? ' — ' . $motif : '');

        // Débit sur le compte émetteur
        $this->transactionModel->addTransaction(
            $fromId,
            'expense',
            $amount,
            'Virement',
            $label,
            $userId
        );

        // Crédit sur le compte destinataire
        $this->transactionModel->addTransaction(
            $toId,
            'income',
            $amount,
            'Virement',
            $label,
            $userId
        );

        $this->setFlash('success', sprintf(
            'Virement de %s %s effectué de « %s » vers « %s ».',
            number_format($amount, 2, ',', ' '),
            $fromAccount['currency'],
            $fromAccount['name'],
            $toAccount['name']
        ));
        $this->redirect('/accounts/' . $fromId);
    }
}
