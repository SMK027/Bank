<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;

class TransferController extends Controller
{
    private Account $accountModel;
    private Transaction $transactionModel;
    private Transfer $transferModel;
    private User $userModel;

    public function __construct()
    {
        $this->accountModel     = new Account();
        $this->transactionModel = new Transaction();
        $this->transferModel    = new Transfer();
        $this->userModel        = new User();
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        // Onglet Personnel : comptes propres + partagés (identique à un utilisateur normal)
        $accounts = $this->accountModel->getAccessibleAccounts($userId);
        foreach ($accounts['own'] as &$acc) {
            $acc['balance'] = $this->accountModel->getBalance((int) $acc['id']);
        }
        unset($acc);
        foreach ($accounts['shared'] as &$acc) {
            $acc['balance'] = $this->accountModel->getBalance((int) $acc['id']);
        }
        unset($acc);
        $ownAccounts    = $accounts['own'];
        $sharedAccounts = $accounts['shared'];

        // Onglet Modération : tous les comptes enrichis (modérateur uniquement)
        $allAccountsJson = null;
        $allUsers        = [];
        if ($this->isModerator()) {
            $allUsers    = $this->userModel->findAll('username', 'ASC');
            $usersMap    = array_column($allUsers, null, 'id');
            $enriched    = [];
            foreach ($this->accountModel->findAll('id', 'ASC') as $acc) {
                $uid  = (int) $acc['user_id'];
                $user = $usersMap[$uid] ?? null;
                $enriched[] = [
                    'id'           => (int) $acc['id'],
                    'name'         => $acc['name'],
                    'type'         => $acc['type'] ?? 'standard',
                    'currency'     => $acc['currency'],
                    'overdraft'    => (float) ($acc['overdraft'] ?? 0),
                    'balance'      => $this->accountModel->getBalance((int) $acc['id']),
                    'frozen'       => !empty($acc['frozen']),
                    'no_overdraft' => !Account::typeAllowsOverdraft($acc['type'] ?? 'standard'),
                    'user_id'      => $uid,
                    'user_name'    => $user ? ($user['username'] ?? 'Utilisateur #' . $uid) : 'Utilisateur #' . $uid,
                ];
            }
            $allAccountsJson = json_encode($enriched, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        }

        // Pré-sélection du compte émetteur si passé en GET
        $preselect = isset($_GET['from']) ? (int) $_GET['from'] : null;
        // Onglet actif par défaut si passé en GET (?tab=moderation)
        $activeTab = (isset($_GET['tab']) && $_GET['tab'] === 'moderation') ? 'moderation' : 'personal';

        $this->render('transfers/create', [
            'title'          => 'Virement entre comptes',
            'ownAccounts'    => $ownAccounts,
            'sharedAccounts' => $sharedAccounts,
            'preselect'      => $preselect,
            'isModerator'    => $this->isModerator(),
            'allAccountsJson'=> $allAccountsJson,
            'allUsers'       => $allUsers,
            'accountTypes'   => Account::TYPES,
            'activeTab'      => $activeTab,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $data   = $this->getPostData(['from_account_id', 'to_account_id', 'amount', 'motif', 'mode', 'scheduled_at']);

        $fromId  = (int) $data['from_account_id'];
        $toId    = (int) $data['to_account_id'];
        $modMode = $this->isModerator() && ($data['mode'] ?? '') === 'moderation';

        // Comptes identiques
        if ($fromId === $toId) {
            $this->setFlash('danger', 'Le compte émetteur et le compte destinataire doivent être différents.');
            $this->redirect('/transfers/create' . ($modMode ? '?tab=moderation' : ''));
            return;
        }

        // Montant strictement positif
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant doit être strictement positif.');
            $this->redirect('/transfers/create' . ($modMode ? '?tab=moderation' : ''));
            return;
        }

        // Vérifier l'accès au compte émetteur (bypass uniquement en mode modération)
        if (!$modMode && !$this->accountModel->hasAccess($fromId, $userId)) {
            $this->setFlash('danger', 'Accès refusé au compte émetteur.');
            $this->redirect('/transfers/create');
            return;
        }

        // Vérifier que le compte destinataire existe et est accessible
        $toAccount = $this->accountModel->find($toId);
        if (!$toAccount || (!$modMode && !$this->accountModel->hasAccess($toId, $userId))) {
            $this->setFlash('danger', 'Compte destinataire introuvable ou accès refusé.');
            $this->redirect('/transfers/create' . ($modMode ? '?tab=moderation' : ''));
            return;
        }

        $fromAccount = $this->accountModel->find($fromId);
        if (!$fromAccount) {
            $this->setFlash('danger', 'Compte émetteur introuvable.');
            $this->redirect('/transfers/create' . ($modMode ? '?tab=moderation' : ''));
            return;
        }

        // Bloquer le virement si le compte émetteur est gelé
        if ($this->accountModel->isFrozen($fromId)) {
            $this->setFlash('danger', sprintf(
                'Virement impossible : le compte émetteur « %s » est gelé. Les virements sortants sont bloqués.',
                $fromAccount['name'] ?? ''
            ));
            $this->redirect('/transfers/create?tab=' . ($modMode ? 'moderation' : 'personal'));
            return;
        }
        // Traiter la date programmée
        $scheduledAt = null;
        if (!empty($data['scheduled_at'])) {
            $ts = strtotime($data['scheduled_at']);
            if ($ts === false || $ts <= time()) {
                $this->setFlash('danger', 'La date de planification doit être dans le futur.');
                $this->redirect('/transfers/create?tab=' . ($modMode ? 'moderation' : 'personal'));
                return;
            }
            $scheduledAt = date('Y-m-d H:i:s', $ts);
        }

        // Utiliser le solde futur (incl. opérations planifiées) pour le contrôle
        $balance     = $scheduledAt !== null
            ? $this->accountModel->getFutureBalance($fromId)
            : $this->accountModel->getBalance($fromId);
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
            $this->redirect('/transfers/create?tab=' . ($modMode ? 'moderation' : 'personal'));
            return;
        }

        $motif = trim($data['motif'] ?: '');
        $label = 'Virement' . ($motif !== '' ? ' — ' . $motif : '');

        // Débit sur le compte émetteur
        $debitTxId = $this->transactionModel->addTransaction(
            $fromId,
            'expense',
            $amount,
            'Virement',
            $label,
            $userId,
            $scheduledAt
        );

        // Crédit sur le compte destinataire
        $creditTxId = $this->transactionModel->addTransaction(
            $toId,
            'income',
            $amount,
            'Virement',
            $label,
            $userId,
            $scheduledAt
        );

        // Enregistrer le virement avec les IDs des deux transactions
        $this->transferModel->createTransfer(
            $fromId,
            $toId,
            $userId,
            $amount,
            $motif,
            $scheduledAt,
            $debitTxId,
            $creditTxId
        );

        $verb = $scheduledAt !== null ? 'planifié pour le ' . date('d/m/Y à H:i', strtotime($scheduledAt)) : 'effectué';
        $this->setFlash('success', sprintf(
            'Virement de %s %s %s de « %s » vers « %s ».',
            number_format($amount, 2, ',', ' '),
            $fromAccount['currency'],
            $verb,
            $fromAccount['name'],
            $toAccount['name']
        ));
        $this->redirect('/accounts/' . $fromId);
    }
}
