<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Account;
use App\Models\ApiPayment;
use App\Models\AuditLog;
use App\Models\DeferredDebit;
use App\Models\Transaction;
use App\Models\AccountAccess;
use App\Models\DirectDebit;
use App\Models\Guardianship;
use App\Models\Mandate;
use App\Models\PaymentCard;
use App\Models\RecurringTransfer;
use App\Models\SavingsInterest;
use App\Models\SavingsRate;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Check;
use App\Models\Checkbook;
use App\Models\OverdraftAuthorization;
use App\Models\User;

class AccountController extends Controller
{
    private Account $accountModel;
    private Transaction $transactionModel;
    private AccountAccess $accessModel;
    private DirectDebit $directDebitModel;
    private DeferredDebit $deferredDebitModel;
    private User $userModel;
    private RecurringTransfer $recurringTransferModel;
    private SavingsRate $rateModel;
    private Loan $loanModel;
    private PaymentCard $cardModel;
    private Checkbook $checkbookModel;
    private Check $checkModel;

    public function __construct()
    {
        $this->accountModel           = new Account();
        $this->rateModel              = new SavingsRate();
        $this->checkbookModel         = new Checkbook();
        $this->checkModel             = new Check();
        $this->transactionModel       = new Transaction();
        $this->accessModel            = new AccountAccess();
        $this->directDebitModel       = new DirectDebit();
        $this->deferredDebitModel     = new DeferredDebit();
        $this->userModel              = new User();
        $this->recurringTransferModel = new RecurringTransfer();
        $this->loanModel              = new Loan();
        $this->cardModel              = new PaymentCard();
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $this->requireFeature('accounts.create');
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
        $this->requireFeature('accounts.create');
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

        $accountId = $this->accountModel->createAccount(
            $this->getCurrentUserId(),
            $data['name'],
            $data['currency'],
            $overdraft,
            $type,
            $cap
        );

        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_ACCOUNT_CREATE, ['name' => $data['name'], 'type' => $type], targetAccountId: $accountId);
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
        $isDisabled  = $this->accountModel->isDisabled($accountId);

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

        // Transactions à venir (programmées futures) — enrichies depuis le chargement complet
        // On exclut les transactions liées à un chèque (affichées dans la section chèques).
        $pendingTransactions = array_values(array_filter(
            $transactions,
            fn($t) => $t['is_pending'] && empty($t['check_id'])
        ));

        // Pagination des transactions exécutées (serveur)
        $txPerPage    = max(5, min(100, (int) ($_GET['per_page'] ?? 25)));
        $txTotalCount = $this->transactionModel->countExecutedByAccount($accountId);
        $txTotalPages = max(1, (int) ceil($txTotalCount / $txPerPage));
        $txPage       = max(1, min($txTotalPages, (int) ($_GET['page'] ?? 1)));
        $txOffset     = ($txPage - 1) * $txPerPage;

        $executedTransactions = $this->transactionModel->getExecutedByAccountPaginated($accountId, $txPerPage, $txOffset);
        foreach ($executedTransactions as &$t) {
            $authorId = isset($t['user_id']) ? (int) $t['user_id'] : 0;
            if ($authorId === 0) {
                $t['author_name'] = 'Modération';
            } else {
                $author = $this->userModel->find($authorId);
                if ($author && ($author['global_role'] ?? 'user') === 'moderator'
                    && !$this->accountModel->hasAccess($accountId, $authorId)) {
                    $t['author_name'] = 'Modération';
                } else {
                    $t['author_name'] = $author ? $author['username'] : 'Inconnu';
                }
            }
            $t['is_pending'] = false;
        }
        unset($t);

        // IDs des transactions de la page courante liées à un virement/prélèvement (non supprimables)
        $pageTxIds   = array_column($executedTransactions, 'id');
        $linkedTxIds = $pageTxIds ? $this->transactionModel->getProtectedIds($pageTxIds) : [];

        // IDs des transactions liées à un débit différé exécuté :
        // non supprimables par les utilisateurs normaux.
        $deferredTxIds = (!$isModerator && $pageTxIds)
            ? $this->deferredDebitModel->getExecutedTransactionIds($pageTxIds)
            : [];

        // Prélèvements planifiés sur ce compte (to_account) non encore exécutés
        // Prélèvements planifiés liés au compte (qu'il soit débité ou crédité via mandat émis)
        $upcomingDebits = $this->directDebitModel->getUpcomingByAccount($accountId);
        foreach ($upcomingDebits as &$d) {
            $isCredit = $d['from_account_id'] !== null && (int) $d['from_account_id'] === $accountId;
            $d['direction'] = $isCredit ? 'credit' : 'debit';
            // Contrepartie (autre compte impliqué)
            $counterpartyId = $isCredit
                ? ($d['to_account_id'] !== null ? (int) $d['to_account_id'] : null)
                : ($d['from_account_id'] !== null ? (int) $d['from_account_id'] : null);
            if ($counterpartyId === null) {
                $d['counterparty_name'] = 'Banque';
            } else {
                $cp = $this->accountModel->find($counterpartyId);
                $d['counterparty_name'] = $cp['name'] ?? ('Compte #' . $counterpartyId);
            }
        }
        unset($d);

        // Mandats actifs où ce compte est débité (recipient) avec prochaine exécution dans le mois en cours :
        // affichés comme des prélèvements virtuels.
        $mandateModel  = new Mandate();
        $monthStart    = date('Y-m-01 00:00:00');
        $monthEnd      = date('Y-m-t 23:59:59');
        foreach ($mandateModel->getUpcomingByAccount($accountId) as $m) {
            if ((int) $m['recipient_account_id'] !== $accountId) {
                continue; // on ne prend que les mandats débiteurs pour ce compte
            }
            if (empty($m['next_execution_at'])) {
                continue;
            }
            $next = $m['next_execution_at'];
            if ($next < $monthStart || $next > $monthEnd) {
                continue;
            }
            $emitterId = $m['emitter_account_id'] !== null ? (int) $m['emitter_account_id'] : null;
            $upcomingDebits[] = [
                'id'                => null,
                'mandate_number'    => $m['number'],
                'scheduled_at'      => $next,
                'amount'            => (float) $m['amount'],
                'motif'             => $m['description'] ?? null,
                'from_account_id'   => $emitterId,
                'to_account_id'     => $accountId,
                'status'            => 'scheduled',
                'direction'         => 'debit',
                'counterparty_name' => $emitterId === null
                    ? 'Banque'
                    : ($m['emitter_name'] ?? ('Compte #' . $emitterId)),
                'is_mandate'        => true,
            ];
        }
        // Re-tri par date d'exécution prévue
        usort($upcomingDebits, fn($a, $b) => strcmp($a['scheduled_at'], $b['scheduled_at']));

        // Échéances de crédit à venir (pending, sur ce compte)
        $installmentModel         = new LoanInstallment();
        $upcomingLoanInstallments = $installmentModel->getUpcomingByAccount($accountId);

        // Débits différés en attente (encours carte)
        $pendingDeferredDebits = [];
        $deferredDebitEnabled  = !empty($account['deferred_debit_enabled']);
        $executedDeferredDebits = [];
        if ($deferredDebitEnabled) {
            $pendingDeferredDebits = $this->deferredDebitModel->getPendingByAccount($accountId);
            foreach ($pendingDeferredDebits as &$dd) {
                $authorId = (int) ($dd['user_id'] ?? 0);
                $author   = $this->userModel->find($authorId);
                $dd['author_name'] = $author ? $author['username'] : 'Inconnu';
            }
            unset($dd);

            // Débits différés exécutés (modérateurs uniquement, limités à 7 jours)
            if ($isModerator) {
                $executedDeferredDebits = $this->deferredDebitModel->getRecentlyExecutedByAccount($accountId);
                foreach ($executedDeferredDebits as &$dd) {
                    $authorId = (int) ($dd['user_id'] ?? 0);
                    $author   = $this->userModel->find($authorId);
                    $dd['author_name'] = $author ? $author['username'] : 'Inconnu';
                }
                unset($dd);
            }
        }

        // Intérêts accumulés en cours d'année (TWAB Jan 1 → aujourd'hui)
        $accruedInterest = null;
        $accountRate     = isset($account['interest_rate']) ? (float) $account['interest_rate'] : 0.0;
        if (Account::typeHasInterest($account['type'] ?? '') && $accountRate !== 0.0) {
            // Comptes internes : pas de plafonnement par le taux de modération
            $rateSegments    = !empty($account['internal'])
                ? []
                : $this->rateModel->getRateSegmentsForYear($account['type'], (int) date('Y'));
            $accruedInterest = SavingsInterest::calculateAccrued(
                $accountId,
                $accountRate,
                $this->transactionModel,
                $rateSegments
            );
        }

        // Encaissements TPE (comptes professionnels uniquement)
        $posPayments = [];
        if (($account['type'] ?? '') === 'pro') {
            $paymentModel = new ApiPayment();
            $posPayments  = $paymentModel->getByMerchantAccount($accountId);
        }

        // Virements récurrents liés à ce compte (émetteur ou destinataire)
        $recurringTransfers = $this->recurringTransferModel->getByAccount($accountId);
        foreach ($recurringTransfers as &$r) {
            $fromAcc = $this->accountModel->find((int) $r['from_account_id']);
            $toAcc   = $this->accountModel->find((int) $r['to_account_id']);
            $r['from_account_name'] = $fromAcc['name'] ?? ('Compte #' . $r['from_account_id']);
            $r['to_account_name']   = $toAcc['name']   ?? ('Compte #' . $r['to_account_id']);
        }
        unset($r);

        // Cartes bancaires actives et non expirées du compte
        // $txCards : pour le formulaire "Nouvelle opération" (toutes dépenses, facultatif)
        // $accountCards : pour le formulaire "Débit différé" (avec totaux mensuels)
        $txCards = [];
        foreach ($this->cardModel->getByAccount($accountId) as $c) {
            if ($c['status'] !== 'active' || PaymentCard::isExpired($c)) {
                continue;
            }
            $txMonthlyLimit = isset($c['monthly_limit']) && $c['monthly_limit'] !== null
                ? (float) $c['monthly_limit']
                : null;
            $txMonthlyTotal = $txMonthlyLimit !== null
                ? $this->cardModel->getMonthlyTotal((int) $c['id'])
                : null;
            $txCards[] = [
                'id'            => (int) $c['id'],
                'label'         => $c['label'] ?? null,
                'masked'        => PaymentCard::mask($c['card_number']),
                'monthly_limit' => $txMonthlyLimit,
                'monthly_total' => $txMonthlyTotal,
            ];
        }

        $accountCards = [];
        if ($deferredDebitEnabled) {
            foreach ($this->cardModel->getByAccount($accountId) as $c) {
                if ($c['status'] !== 'active' || PaymentCard::isExpired($c)) {
                    continue;
                }
                $monthlyTotal = null;
                if (isset($c['monthly_limit']) && $c['monthly_limit'] !== null) {
                    $monthlyTotal = $this->cardModel->getMonthlyTotal((int) $c['id']);
                }
                $accountCards[] = [
                    'id'           => (int) $c['id'],
                    'label'        => $c['label'] ?? null,
                    'masked'       => PaymentCard::mask($c['card_number']),
                    'monthly_limit' => isset($c['monthly_limit']) ? (float) $c['monthly_limit'] : null,
                    'monthly_total' => $monthlyTotal,
                ];
            }
        }

        $this->render('accounts/show', [
            'title'                => $account['name'],
            'account'             => $account,
            'transactions'        => $transactions,
            'pendingTransactions'  => $pendingTransactions,
            'executedTransactions' => $executedTransactions,
            'txPage'               => $txPage,
            'txTotalPages'         => $txTotalPages,
            'txTotalCount'         => $txTotalCount,
            'txPerPage'            => $txPerPage,
            'upcomingDebits'       => $upcomingDebits,
            'upcomingLoanInstallments' => $upcomingLoanInstallments,
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
            'isDisabled'         => $isDisabled,
            'accesses'           => $accesses,
            'owner'              => $owner,
            'guardians'          => $guardians,
            'isMinorAccount'     => $isMinorAccount,
            'isGuardian'         => $isGuardian,
            'categories'         => Transaction::CATEGORIES,
            'expenseCategories'  => Transaction::EXPENSE_CATEGORIES,
            'incomeCategories'   => Transaction::INCOME_CATEGORIES,
            'mandates'           => [],
            'upcomingMandates'   => [],
            'linkedTxIds'        => $linkedTxIds,
            'deferredTxIds'      => $deferredTxIds,
            'recurringTransfers' => $recurringTransfers,
            'accruedInterest'    => $accruedInterest,
            'deferredDebitEnabled'    => $deferredDebitEnabled,
            'pendingDeferredDebits'  => $pendingDeferredDebits,
            'executedDeferredDebits' => $executedDeferredDebits,
            'deferredDebitDay'       => $deferredDebitEnabled ? ($account['deferred_debit_day'] ?? null) : null,
            'posPayments'            => $posPayments,
            'accountCards'           => $accountCards,
            'txCards'                => $txCards,
            // Sync avec TransactionController::CARD_REQUIRED_SINCE
            'cardRequiredSince'      => \App\Controllers\TransactionController::CARD_REQUIRED_SINCE,
            // Autorisation de dépassement de découvert active (null si aucune)
            'overdraftAuthorization' => (new OverdraftAuthorization())->getActiveForAccount($accountId),
            // Chéquiers actifs du compte (pour le formulaire de dépense par chèque)
            'activeCheckbooks'       => Checkbook::typeAllowsCheckbook($account['type'] ?? '')
                ? $this->checkbookModel->getActiveByAccount($accountId)
                : [],
            // Chèques en attente d'encaissement sur ce compte
            'pendingChecks'          => $this->checkModel->getEmittedByAccount($accountId),
        ]);
    }

    public function editForm(string $id): void
    {
        $this->requireAuth();
        $this->requireFeature('accounts.edit');
        $accountId = (int) $id;
        $userId = $this->getCurrentUserId();

        $account    = $this->accountModel->find($accountId);
        $isOwner    = $account && $this->accountModel->isOwner($accountId, $userId);
        $isGuardian = false;
        if (!$isOwner && $account) {
            $guardianshipModel = new Guardianship();
            $isGuardian = $guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id']);
        }

        if (!$account || (!$isOwner && !$isGuardian)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        // Pour les comptes internes : autoriser le changement de type + pas de limite de taux.
        $isInternal = !empty($account['internal']);
        $maxRate    = null;
        $maxRates   = [];
        if ($isInternal) {
            foreach (Account::getInterestEligibleTypes() as $iType) {
                $maxRates[$iType] = null; // pas de plafond pour les comptes internes
            }
        } elseif (Account::typeHasInterest($account['type'] ?? '')) {
            $maxRate = $this->rateModel->getCurrentRate($account['type']);
        }

        $this->render('accounts/edit', [
            'title'      => 'Modifier le compte',
            'account'    => $account,
            'maxRate'    => $maxRate,
            'isGuardian' => $isGuardian,
            'isInternal' => $isInternal,
            'accountTypes' => Account::TYPES,
            'maxRates'   => $maxRates,
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireAuth();
        $this->requireFeature('accounts.edit');
        $this->validateCSRF();
        $accountId = (int) $id;
        $userId    = $this->getCurrentUserId();

        $account    = $this->accountModel->find($accountId);
        $isOwner    = $account && $this->accountModel->isOwner($accountId, $userId);
        $isGuardian = false;
        if (!$isOwner && $account) {
            $guardianshipModel = new Guardianship();
            $isGuardian = $guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id']);
        }

        if (!$account || (!$isOwner && !$isGuardian)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $data = $this->getPostData(['name', 'currency', 'overdraft', 'account_type', 'cap', 'balance_alert_threshold', 'interest_rate', 'deferred_debit_day']);

        if (empty($data['name']) || empty($data['currency'])) {
            $this->setFlash('danger', 'Le nom et la devise sont requis.');
            $this->redirect('/accounts/' . $id . '/edit');
            return;
        }

        $type           = $account['type'] ?? 'standard';
        // Comptes internes de modération : changement de type autorisé.
        if (!empty($account['internal']) && !empty($data['account_type'])
            && array_key_exists($data['account_type'], Account::TYPES)) {
            $type = $data['account_type'];
        }
        $alertThreshold = ($data['balance_alert_threshold'] ?? '') !== ''
            ? max(0.0, (float) $data['balance_alert_threshold'])
            : null;

        if ($isGuardian && !$isOwner) {
            // Responsable légal : modification limitée au nom, devise et seuil d'alerte
            $this->accountModel->update($accountId, [
                'name'                    => $data['name'],
                'currency'                => $data['currency'],
                'balance_alert_threshold' => $alertThreshold,
            ]);
        } else {
            $overdraft    = Account::typeAllowsOverdraft($type) ? abs((float) ($data['overdraft'] ?: 0)) : 0.0;
            $cap          = Account::typeHasCap($type) && $data['cap'] !== '' ? abs((float) $data['cap']) : null;
            $isInternal   = !empty($account['internal']);
            $interestRate = null;
            if (Account::typeHasInterest($type) && ($data['interest_rate'] ?? '') !== '') {
                $rawPct       = (float) str_replace(',', '.', $data['interest_rate']);
                $interestRate = round($rawPct / 100, 5);
                if (!$isInternal) {
                    $maxRate = $this->rateModel->getCurrentRate($type);
                    if ($maxRate !== null && $interestRate > $maxRate) {
                        $interestRate = $maxRate;
                    }
                }
            }
            $this->accountModel->update($accountId, [
                'name'                    => $data['name'],
                'currency'                => $data['currency'],
                'overdraft'               => $overdraft,
                'type'                    => $type,
                'cap'                     => $cap,
                'balance_alert_threshold' => $alertThreshold,
                'interest_rate'           => $interestRate,
                'deferred_debit_day'      => $this->parseDeferredDebitDay($data['deferred_debit_day'] ?? null, $account),
            ]);
        }

        $this->setFlash('success', 'Compte modifié avec succès.');
        $this->redirect('/accounts/' . $id);
    }

    /**
     * Parse et valide le jour préféré de débit différé (1-28 ou null).
     */
    private function parseDeferredDebitDay(?string $value, array $account): ?int
    {
        if (!$account['deferred_debit_enabled']) {
            return $account['deferred_debit_day'] ?? null;
        }
        if ($value === null || $value === '') {
            return null;
        }
        $day = (int) $value;
        return ($day >= 1 && $day <= 31) ? $day : null;
    }

    /**
     * Bascule la visibilité d'un compte mineur pour son propriétaire.
     * Seul un responsable légal actif peut effectuer cette action.
     */
    public function toggleHidden(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $accountId = (int) $id;
        $userId    = $this->getCurrentUserId();

        $account = $this->accountModel->find($accountId);
        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/dashboard');
            return;
        }

        $guardianshipModel = new Guardianship();
        if (!$guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id'])) {
            $this->setFlash('danger', 'Accès refusé. Seul un responsable légal peut modifier la visibilité de ce compte.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $hide  = empty($account['hidden_from_owner']);
        $minor = $this->userModel->find((int) $account['user_id']);
        $this->accountModel->update($accountId, ['hidden_from_owner' => $hide ? 1 : 0]);

        AuditLog::log(
            $userId,
            $hide ? AuditLog::ACTION_ACCOUNT_HIDE : AuditLog::ACTION_ACCOUNT_SHOW,
            ['name' => $account['name']],
            targetUserId: (int) $account['user_id'],
            targetAccountId: $accountId
        );

        $this->setFlash('success', $hide
            ? sprintf('Le compte « %s » est maintenant masqué pour %s.', $account['name'], $minor['username'] ?? 'le mineur')
            : sprintf('Le compte « %s » est à nouveau visible pour %s.', $account['name'], $minor['username'] ?? 'le mineur')
        );
        $this->redirect('/accounts/' . $accountId);
    }

    public function disableAccount(string $id): void
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

        $account = $this->accountModel->find($accountId);
        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/dashboard');
            return;
        }

        if (!empty($account['disabled_at'])) {
            $this->setFlash('info', 'Ce compte est déjà en cours de résiliation.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Bloquer la résiliation si le solde est négatif (découvert)
        $balance = $this->accountModel->getBalance($accountId);
        if ($balance < 0) {
            $this->setFlash('danger', sprintf(
                'Impossible de résilier ce compte : le solde est négatif (%s %s). Le découvert doit être apuré avant la résiliation.',
                number_format($balance, 2, ',', ' '),
                $account['currency'] ?? 'EUR'
            ));
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Si un crédit actif est associé au compte, on n'efface pas le crédit :
        // la suppression du compte (en fin de mois) mettra simplement account_id à NULL
        // (ON DELETE SET NULL). La modération devra ensuite réaffecter ce crédit
        // à un autre compte du contractant pour les prélèvements de mensualités.
        $hasActiveLoan = $this->loanModel->hasActiveLoanForAccount($accountId);

        // Bloquer la résiliation si des débits différés sont en attente
        $pendingDD = $this->deferredDebitModel->getPendingByAccount($accountId);
        if (!empty($pendingDD)) {
            $this->setFlash('danger', sprintf(
                'Impossible de résilier ce compte : %d opération(s) à débit différé en attente d\'encaissement.',
                count($pendingDD)
            ));
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $this->accountModel->disableAccount($accountId);
        AuditLog::log($userId, AuditLog::ACTION_ACCOUNT_DISABLE, ['name' => $account['name'] ?? '?', 'has_active_loan' => $hasActiveLoan], targetAccountId: $accountId);
        if ($hasActiveLoan) {
            $this->setFlash('warning',
                'Compte désactivé. Il sera définitivement supprimé à la fin du mois. '
                . 'Attention : un crédit actif est associé à ce compte. Il sera conservé, '
                . 'mais la modération devra réaffecter les prélèvements à un autre de vos comptes.'
            );
        } else {
            $this->setFlash('success', 'Compte désactivé. Il sera définitivement supprimé à la fin du mois.');
        }
        $this->redirect('/accounts/' . $accountId);
    }

    public function enableAccount(string $id): void
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

        $account = $this->accountModel->find($accountId);
        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/dashboard');
            return;
        }

        if (empty($account['disabled_at'])) {
            $this->setFlash('info', 'Ce compte n\'est pas désactivé.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $this->accountModel->enableAccount($accountId);
        AuditLog::log($userId, AuditLog::ACTION_ACCOUNT_ENABLE, ['name' => $account['name'] ?? '?'], targetAccountId: $accountId);
        $this->setFlash('success', 'Compte réactivé avec succès.');
        $this->redirect('/accounts/' . $accountId);
    }

    // -----------------------------------------------------------------------
    // Relevé de compte (PDF)
    // -----------------------------------------------------------------------

    public function statementForm(string $id): void
    {
        $this->requireAuth();
        $accountId   = (int) $id;
        $userId      = $this->getCurrentUserId();
        $isModerator = $this->isModerator();

        $account = $this->accountModel->find($accountId);
        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/dashboard');
            return;
        }

        $hasAccess = $this->accountModel->isOwner($accountId, $userId)
            || $this->accountModel->hasAccess($accountId, $userId)
            || $isModerator;

        if (!$hasAccess) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $this->render('accounts/statement_form', [
            'title'   => 'Relevé de compte — ' . $account['name'],
            'account' => $account,
        ]);
    }

    public function generateStatement(string $id): void
    {
        $this->requireAuth();
        $accountId   = (int) $id;
        $userId      = $this->getCurrentUserId();
        $isModerator = $this->isModerator();

        $account = $this->accountModel->find($accountId);
        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/dashboard');
            return;
        }

        $hasAccess = $this->accountModel->isOwner($accountId, $userId)
            || $this->accountModel->hasAccess($accountId, $userId)
            || $isModerator;

        if (!$hasAccess) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        // Validation des dates (GET params)
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo   = $_GET['date_to']   ?? '';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $this->setFlash('danger', 'Dates invalides.');
            $this->redirect("/accounts/{$accountId}/statement");
            return;
        }

        if ($dateFrom > $dateTo) {
            $this->setFlash('danger', 'La date de début doit être antérieure à la date de fin.');
            $this->redirect("/accounts/{$accountId}/statement");
            return;
        }

        $owner        = $this->userModel->find((int) $account['user_id']);
        $transactions = $this->transactionModel->getByAccountBetween($accountId, $dateFrom, $dateTo);
        $openingBal   = $this->transactionModel->getBalanceBeforeDate($accountId, $dateFrom);

        // Calcul du solde courant et des totaux
        $totalIncome  = 0.0;
        $totalExpense = 0.0;
        $runningBal   = $openingBal;

        foreach ($transactions as &$t) {
            if ($t['type'] === 'income') {
                $totalIncome += (float) $t['amount'];
                $runningBal  += (float) $t['amount'];
            } else {
                $totalExpense += (float) $t['amount'];
                $runningBal   -= (float) $t['amount'];
            }
            $t['running_balance'] = $runningBal;
        }
        unset($t);
        $closingBal = $runningBal;

        // Génération du HTML du relevé
        $accountTypes = \App\Models\Account::TYPES;
        $typeLabel    = $accountTypes[$account['type']]['label'] ?? ucfirst($account['type']);
        $generatedAt  = (new \DateTime())->format('d/m/Y à H:i');

        $html = $this->renderStatementHtml(
            $account,
            $owner,
            $typeLabel,
            $dateFrom,
            $dateTo,
            $openingBal,
            $closingBal,
            $totalIncome,
            $totalExpense,
            $transactions,
            $generatedAt
        );

        // Génération PDF avec mPDF
        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 15,
            'margin_bottom' => 20,
            'margin_left'   => 15,
            'margin_right'  => 15,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('Relevé de compte — ' . $account['name']);
        $mpdf->SetAuthor('BankApp');
        $mpdf->SetCreator('BankApp');

        $mpdf->SetHTMLFooter('
            <table width="100%" style="font-size:8pt;color:#888;border-top:1px solid #ddd;padding-top:4px;">
                <tr>
                    <td>Document généré le ' . $generatedAt . ' — Confidentiel</td>
                    <td style="text-align:right;">Page {PAGENO} / {nbpg}</td>
                </tr>
            </table>');

        $mpdf->WriteHTML($html);

        $filename = 'releve_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($account['name']))
                  . '_' . str_replace('-', '', $dateFrom)
                  . '_' . str_replace('-', '', $dateTo)
                  . '.pdf';

        $mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);
        exit;
    }

    private function renderStatementHtml(
        array   $account,
        ?array  $owner,
        string  $typeLabel,
        string  $dateFrom,
        string  $dateTo,
        float   $openingBal,
        float   $closingBal,
        float   $totalIncome,
        float   $totalExpense,
        array   $transactions,
        string  $generatedAt
    ): string {
        $fmt = fn(float $v): string => number_format($v, 2, ',', ' ') . ' ' . e($account['currency']);

        $fromFmt = (new \DateTime($dateFrom))->format('d/m/Y');
        $toFmt   = (new \DateTime($dateTo))->format('d/m/Y');

        $rows = '';
        foreach ($transactions as $t) {
            $isIncome  = $t['type'] === 'income';
            $amount    = (float) $t['amount'];
            $bal       = (float) $t['running_balance'];
            $color     = $isIncome ? '#16a34a' : '#dc2626';
            $sign      = $isIncome ? '+' : '−';
            $balColor  = $bal < 0 ? '#dc2626' : '#1e293b';
            $date      = (new \DateTime($t['created_at']))->format('d/m/Y H:i');

            $rows .= '<tr>
                <td style="color:#64748b;font-size:10pt;">' . e($date) . '</td>
                <td>' . e($t['category']) . '</td>
                <td style="max-width:200px;">' . e($t['comment'] ?: '—') . '</td>
                <td style="text-align:right;color:' . $color . ';font-weight:600;">'
                    . $sign . ' ' . number_format($amount, 2, ',', ' ') . '</td>
                <td style="text-align:right;color:' . $balColor . ';font-weight:600;">'
                    . number_format($bal, 2, ',', ' ') . ' ' . e($account['currency']) . '</td>
            </tr>';
        }

        if (empty($transactions)) {
            $rows = '<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:20px;">Aucune opération sur cette période.</td></tr>';
        }

        $ownerName = e($owner['username'] ?? 'Inconnu');

        return '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body          { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1e293b; margin: 0; }
    h1            { font-size: 18pt; color: #1e40af; margin: 0 0 4px; }
    h2            { font-size: 12pt; color: #334155; margin: 0 0 10px; font-weight: normal; }
    .header-block { border-bottom: 2px solid #1e40af; padding-bottom: 12px; margin-bottom: 18px; }
    .bank-name    { font-size: 22pt; font-weight: 700; color: #1e40af; letter-spacing: 1px; }
    .meta-grid    { width: 100%; }
    .meta-grid td { vertical-align: top; padding: 2px 0; }
    .label        { color: #64748b; font-size: 9pt; }
    .value        { color: #1e293b; font-weight: 600; }
    .summary-box  { background: #f1f5f9; border-radius: 6px; padding: 12px 16px; margin: 16px 0; }
    .summary-grid { width: 100%; }
    .summary-grid td { padding: 4px 8px; font-size: 9.5pt; }
    .s-label      { color: #64748b; }
    .s-value      { font-weight: 700; text-align: right; }
    .income       { color: #16a34a; }
    .expense      { color: #dc2626; }
    .neutral      { color: #1e293b; }
    table.ops     { width: 100%; border-collapse: collapse; margin-top: 12px; }
    table.ops th  { background: #1e40af; color: #fff; padding: 7px 8px; font-size: 9pt; text-align: left; }
    table.ops td  { padding: 6px 8px; font-size: 9pt; border-bottom: 1px solid #e2e8f0; }
    table.ops tr:nth-child(even) td { background: #f8fafc; }
    .closing-row td { font-weight: 700; background: #eff6ff !important; border-top: 2px solid #1e40af; }
    .no-break     { page-break-inside: avoid; }
</style>
</head>
<body>

<div class="header-block">
    <table style="width:100%">
        <tr>
            <td>
                <div class="bank-name">&#127981; BankApp</div>
                <div style="color:#64748b;font-size:9pt;">Banque de simulation — Relevé de compte</div>
            </td>
            <td style="text-align:right;vertical-align:bottom;">
                <div style="font-size:9pt;color:#64748b;">Généré le ' . $generatedAt . '</div>
            </td>
        </tr>
    </table>
</div>

<h1>' . e($account['name']) . '</h1>
<h2>Relevé du ' . $fromFmt . ' au ' . $toFmt . '</h2>

<table class="meta-grid" style="margin-bottom:6px;">
    <tr>
        <td style="width:50%">
            <span class="label">Type de compte&nbsp;</span>
            <span class="value">' . $typeLabel . '</span>
        </td>
        <td>
            <span class="label">Titulaire&nbsp;</span>
            <span class="value">' . $ownerName . '</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Devise&nbsp;</span>
            <span class="value">' . e($account['currency']) . '</span>
        </td>
        <td>
            <span class="label">N° de compte&nbsp;</span>
            <span class="value">#' . (int) $account['id'] . '</span>
        </td>
    </tr>
</table>

<div class="summary-box no-break">
    <table class="summary-grid">
        <tr>
            <td class="s-label">Solde d\'ouverture (' . $fromFmt . ')</td>
            <td class="s-value neutral">' . $fmt($openingBal) . '</td>
            <td style="width:50px;"></td>
            <td class="s-label">Nombre d\'opérations</td>
            <td class="s-value neutral">' . count($transactions) . '</td>
        </tr>
        <tr>
            <td class="s-label">Total crédits (entrées)</td>
            <td class="s-value income">+ ' . $fmt($totalIncome) . '</td>
            <td></td>
            <td class="s-label">Total débits (sorties)</td>
            <td class="s-value expense">− ' . $fmt($totalExpense) . '</td>
        </tr>
        <tr>
            <td class="s-label" style="font-weight:700;font-size:10.5pt;">Solde de clôture (' . $toFmt . ')</td>
            <td class="s-value neutral" style="font-size:12pt;">' . $fmt($closingBal) . '</td>
            <td colspan="3"></td>
        </tr>
    </table>
</div>

<table class="ops">
    <thead>
        <tr>
            <th style="width:17%">Date</th>
            <th style="width:16%">Catégorie</th>
            <th>Libellé</th>
            <th style="width:14%;text-align:right;">Montant (' . e($account['currency']) . ')</th>
            <th style="width:18%;text-align:right;">Solde courant</th>
        </tr>
    </thead>
    <tbody>
        ' . $rows . '
    </tbody>
</table>

</body>
</html>';
    }
}

