<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\AccountAccess;
use App\Models\DirectDebit;
use App\Models\Guardianship;
use App\Models\Mandate;
use App\Models\User;

class AccountController extends Controller
{
    private Account $accountModel;
    private Transaction $transactionModel;
    private AccountAccess $accessModel;
    private DirectDebit $directDebitModel;
    private User $userModel;

    public function __construct()
    {
        $this->accountModel    = new Account();
        $this->transactionModel = new Transaction();
        $this->accessModel      = new AccountAccess();
        $this->directDebitModel = new DirectDebit();
        $this->userModel        = new User();
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $user    = $this->userModel->find($this->getCurrentUserId());
        $isMinor = User::isMinorFromDate($user['birth_date'] ?? null);
        if ($isMinor) {
            $this->setFlash('danger', 'Les mineurs ne peuvent pas créer de compte. Les comptes sont ouverts par la modération.');
            $this->redirect('/dashboard');
            return;
        }
        $isPro = User::isProfessional($user);
        $this->render('accounts/create', [
            'title'        => 'Créer un compte bancaire',
            'accountTypes' => Account::getAllowedTypes($isMinor, $isPro),
            'isMinor'      => $isMinor,
            'isPro'        => $isPro,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $data = $this->getPostData(['name', 'currency', 'overdraft', 'account_type', 'cap']);

        if (empty($data['name']) || empty($data['currency'])) {
            $this->setFlash('danger', 'Le nom et la devise sont requis.');
            $this->redirect('/accounts/create');
            return;
        }

        $user    = $this->userModel->find($this->getCurrentUserId());
        $isMinor = User::isMinorFromDate($user['birth_date'] ?? null);

        // Les mineurs ne peuvent pas créer de compte eux-mêmes
        if ($isMinor) {
            $this->setFlash('danger', 'Les mineurs ne peuvent pas créer de compte. Les comptes sont ouverts par la modération.');
            $this->redirect('/dashboard');
            return;
        }

        // Le type 'minor' est réservé à la modération, jamais au formulaire utilisateur
        if (($data['account_type'] ?? '') === 'minor') {
            $this->setFlash('danger', 'Les comptes mineurs sont créés uniquement par la modération.');
            $this->redirect('/dashboard');
            return;
        }

        // Le type 'pro' est réservé aux utilisateurs professionnels
        if (($data['account_type'] ?? '') === 'pro' && !User::isProfessional($user)) {
            $this->setFlash('danger', 'Les comptes professionnels sont réservés aux utilisateurs ayant un statut professionnel vérifié.');
            $this->redirect('/accounts/create');
            return;
        }

        $allowedTypes = Account::getAllowedTypes($isMinor, User::isProfessional($user));

        if (!array_key_exists($data['account_type'], $allowedTypes)) {
            $this->setFlash('danger', 'Type de compte non autorisé pour votre profil.');
            $this->redirect('/accounts/create');
            return;
        }

        $type      = $data['account_type'];
        $overdraft = Account::typeAllowsOverdraft($type) ? abs((float) ($data['overdraft'] ?: 0)) : 0.0;
        $cap       = Account::typeHasCap($type) && $data['cap'] !== '' ? abs((float) $data['cap']) : null;

        $this->accountModel->createAccount(
            $this->getCurrentUserId(),
            $data['name'],
            $data['currency'],
            $overdraft,
            $type,
            $cap
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
        if (!$account || (!$this->isModerator() && !$this->accountModel->hasAccess($accountId, $userId))) {
            $this->setFlash('danger', 'Compte introuvable ou accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $transactions = $this->transactionModel->getByAccount($accountId);
        // Enrichir chaque transaction avec le nom de l'auteur et le statut programmé
        foreach ($transactions as &$t) {
            $authorId = isset($t['user_id']) ? (int) $t['user_id'] : 0;
            if ($authorId === 0) {
                // user_id = 0 : action explicitement anonymisée (ex. annulation par modération)
                $t['author_name'] = 'Modération';
            } else {
                $author = $this->userModel->find($authorId);
                if ($author && ($author['global_role'] ?? 'user') === 'moderator'
                    && !$this->accountModel->hasAccess($accountId, $authorId)) {
                    // Modérateur sans accès légitime → action de modération anonymisée
                    $t['author_name'] = 'Modération';
                } else {
                    $t['author_name'] = $author ? $author['username'] : 'Inconnu';
                }
            }
            $t['is_pending'] = Transaction::isPending($t);
        }
        unset($t);
        $balance       = $this->accountModel->getBalance($accountId);
        $futureBalance = $this->accountModel->getFutureBalance($accountId);
        $totalIncome   = $this->transactionModel->getTotalIncome($accountId, true);
        $totalExpense  = $this->transactionModel->getTotalExpense($accountId, true);
        $totalIncomeFuture  = $this->transactionModel->getTotalIncome($accountId);
        $totalExpenseFuture = $this->transactionModel->getTotalExpense($accountId);
        $hasPending    = abs($futureBalance - $balance) > 0.001;
        $isOwner = $this->accountModel->isOwner($accountId, $userId);
        $isModerator = $this->isModerator();
        $isFrozen    = $this->accountModel->isFrozen($accountId);

        // Récupérer les accès partagés
        $accesses = [];
        if ($isOwner || $isModerator) {
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

        // Responsables légaux si le propriétaire est mineur
        $guardians       = [];
        $isMinorAccount  = User::isMinorFromDate($owner['birth_date'] ?? null);
        $isGuardian      = false;
        if ($isMinorAccount) {
            $guardianshipModel = new Guardianship();
            $isGuardian = !$isOwner
                && $guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id']);
            foreach ($guardianshipModel->getGuardiansOf((int) $account['user_id']) as $g) {
                $guardianUser = $this->userModel->find((int) $g['guardian_user_id']);
                if ($guardianUser) {
                    $guardians[] = $guardianUser;
                }
            }
        }

        // Séparer les transactions à venir des exécutées
        $pendingTransactions  = array_values(array_filter($transactions, fn($t) => $t['is_pending']));
        $executedTransactions = array_values(array_filter($transactions, fn($t) => !$t['is_pending']));

        // IDs de transactions liées à un virement ou prélèvement (non supprimables individuellement)
        $allTxIds    = array_column($transactions, 'id');
        $linkedTxIds = $allTxIds ? $this->transactionModel->getProtectedIds($allTxIds) : [];

        // Prélèvements planifiés sur ce compte (to_account) non encore exécutés
        $upcomingDebits = $this->directDebitModel->findBy(
            ['to_account_id' => $accountId, 'status' => DirectDebit::STATUS_SCHEDULED],
            'scheduled_at',
            'ASC'
        );

        // Mandats rattachés au compte (émetteur ou destinataire) — comptes pro uniquement
        $mandateModel = new Mandate();
        $mandates = [];
        if ($account['type'] === 'pro') {
            $mandates = $mandateModel->getByAccount($accountId);
        }

        // Mandats à venir (prochaine exécution planifiée) — tous types de comptes
        $upcomingMandates = $mandateModel->getUpcomingByAccount($accountId);

        $this->render('accounts/show', [
            'title'                => $account['name'],
            'account'             => $account,
            'transactions'        => $transactions,
            'pendingTransactions'  => $pendingTransactions,
            'executedTransactions' => $executedTransactions,
            'upcomingDebits'       => $upcomingDebits,
            'balance'             => $balance,
            'futureBalance'      => $futureBalance,
            'hasPending'         => $hasPending,
            'totalIncome'        => $totalIncome,
            'totalExpense'       => $totalExpense,
            'totalIncomeFuture'  => $totalIncomeFuture,
            'totalExpenseFuture' => $totalExpenseFuture,
            'isOwner'            => $isOwner,
            'isModerator'        => $isModerator,
            'isFrozen'           => $isFrozen,
            'accesses'           => $accesses,
            'owner'              => $owner,
            'guardians'          => $guardians,
            'isMinorAccount'     => $isMinorAccount,
            'isGuardian'         => $isGuardian,
            'categories'         => Transaction::CATEGORIES,
            'mandates'           => $mandates,
            'upcomingMandates'   => $upcomingMandates,
            'linkedTxIds'        => $linkedTxIds,
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

        $owner   = $this->userModel->find((int) $account['user_id']);
        $isMinor = User::isMinorFromDate($owner['birth_date'] ?? null);

        $this->render('accounts/edit', [
            'title'        => 'Modifier le compte',
            'account'      => $account,
            'accountTypes' => Account::TYPES,
            'isMinor'      => $isMinor,
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

        $data = $this->getPostData(['name', 'currency', 'overdraft', 'account_type', 'cap']);

        if (empty($data['name']) || empty($data['currency'])) {
            $this->setFlash('danger', 'Le nom et la devise sont requis.');
            $this->redirect('/accounts/' . $id . '/edit');
            return;
        }

        // Les mineurs ne peuvent pas changer le type de leur compte
        $account = $this->accountModel->find($accountId);
        $accountOwner = $this->userModel->find((int) ($account['user_id'] ?? 0));
        if (User::isMinorFromDate($accountOwner['birth_date'] ?? null)) {
            $data['account_type'] = $account['type'] ?? 'savings';
        }

        $type      = array_key_exists($data['account_type'], Account::TYPES) ? $data['account_type'] : 'standard';
        $overdraft = Account::typeAllowsOverdraft($type) ? abs((float) ($data['overdraft'] ?: 0)) : 0.0;
        $cap       = Account::typeHasCap($type) && $data['cap'] !== '' ? abs((float) $data['cap']) : null;

        $this->accountModel->update($accountId, [
            'name'      => $data['name'],
            'currency'  => $data['currency'],
            'overdraft' => $overdraft,
            'type'      => $type,
            'cap'       => $cap,
        ]);

        $this->setFlash('success', 'Compte modifié avec succès.');
        $this->redirect('/accounts/' . $id);
    }

    public function deleteAccount(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $accountId   = (int) $id;
        $userId      = $this->getCurrentUserId();
        $isOwner     = $this->accountModel->isOwner($accountId, $userId);
        $isModerator = $this->isModerator();

        if (!$isOwner && !$isModerator) {
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
