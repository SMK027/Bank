<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Check;
use App\Models\Checkbook;
use App\Models\Guardianship;
use App\Models\Notification;

/**
 * Gestion des chéquiers et des chèques émis.
 */
class CheckbookController extends Controller
{
    private Checkbook      $checkbookModel;
    private Check          $checkModel;
    private Account        $accountModel;
    private Guardianship   $guardianshipModel;
    private Notification   $notifModel;

    public function __construct()
    {
        $this->checkbookModel    = new Checkbook();
        $this->checkModel        = new Check();
        $this->accountModel      = new Account();
        $this->guardianshipModel = new Guardianship();
        $this->notifModel        = new Notification();
    }

    // ─── Liste des chéquiers ──────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $checkbooks = $this->checkbookModel->getByUser($userId);

        // Enrichir chaque chéquier avec le compte associé et le nombre de chèques
        $accountsById = [];
        foreach ($this->accountModel->getByUser($userId) as $acc) {
            $accountsById[(int) $acc['id']] = $acc;
        }

        $checkCounts = [];
        foreach ($checkbooks as &$cb) {
            $cbId = (int) $cb['id'];
            $checks = $this->checkModel->getByCheckbook($cbId);
            $checkCounts[$cbId] = [
                'total'    => count($checks),
                'emitted'  => count(array_filter($checks, fn($c) => $c['status'] === Check::STATUS_EMITTED)),
                'cashed'   => count(array_filter($checks, fn($c) => $c['status'] === Check::STATUS_CASHED)),
                'opposed'  => count(array_filter($checks, fn($c) => $c['status'] === Check::STATUS_OPPOSED)),
            ];
            $cb['account'] = $accountsById[(int) $cb['account_id']] ?? null;
        }
        unset($cb);

        // Comptes éligibles pour la création d'un chéquier
        $eligibleAccounts = array_values(array_filter(
            $accountsById,
            fn(array $a) => Checkbook::typeAllowsCheckbook($a['type'] ?? '') && empty($a['disabled_at'])
        ));

        $this->render('checkbooks/index', [
            'title'            => 'Mes chéquiers',
            'checkbooks'       => $checkbooks,
            'checkCounts'      => $checkCounts,
            'eligibleAccounts' => $eligibleAccounts,
        ]);
    }

    // ─── Création d'un chéquier ──────────────────────────────────────────────

    public function createForm(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $accounts = $this->accountModel->getByUser($userId);
        $eligibleAccounts = array_values(array_filter(
            $accounts,
            fn(array $a) => Checkbook::typeAllowsCheckbook($a['type'] ?? '') && empty($a['disabled_at'])
        ));

        $this->render('checkbooks/create', [
            'title'            => 'Nouveau chéquier',
            'eligibleAccounts' => $eligibleAccounts,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requireFeature('checkbooks.create');
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $data   = $this->getPostData(['account_id', 'label']);

        $accountId = (int) ($data['account_id'] ?? 0);
        if ($accountId <= 0) {
            $this->setFlash('danger', 'Compte invalide.');
            $this->redirect('/checkbooks/create');
            return;
        }

        $account = $this->accountModel->find($accountId);
        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/checkbooks/create');
            return;
        }

        // Vérification d'accès : propriétaire ou tuteur légal uniquement
        $isOwner    = $this->accountModel->isOwner($accountId, $userId);
        $isGuardian = !$isOwner && $this->guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id']);
        if (!$isOwner && !$isGuardian) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/checkbooks');
            return;
        }

        if (!Checkbook::typeAllowsCheckbook($account['type'] ?? '')) {
            $this->setFlash('danger', 'Ce type de compte ne peut pas être associé à un chéquier.');
            $this->redirect('/checkbooks/create');
            return;
        }

        if (!empty($account['disabled_at'])) {
            $this->setFlash('danger', 'Impossible de créer un chéquier sur un compte en résiliation.');
            $this->redirect('/checkbooks/create');
            return;
        }

        $label = trim($data['label'] ?? '');
        if ($label === '') {
            $label = 'Chéquier du ' . date('d/m/Y');
        }

        $cbId = $this->checkbookModel->create([
            'user_id'    => $isOwner ? $userId : (int) $account['user_id'],
            'account_id' => $accountId,
            'label'      => mb_substr($label, 0, 100),
            'status'     => Checkbook::STATUS_ACTIVE,
        ]);

        AuditLog::log(
            $userId,
            'checkbook_created',
            ['label' => $label, 'account_id' => $accountId],
            targetAccountId: $accountId
        );

        $this->setFlash('success', 'Chéquier « ' . htmlspecialchars($label, ENT_QUOTES) . ' » créé.');
        $this->redirect('/checkbooks/' . $cbId);
    }

    // ─── Détail d'un chéquier ────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $checkbook = $this->checkbookModel->find((int) $id);
        if (!$checkbook) {
            $this->setFlash('danger', 'Chéquier introuvable.');
            $this->redirect('/checkbooks');
            return;
        }

        $account    = $this->accountModel->find((int) $checkbook['account_id']);
        $isOwner    = $account && $this->accountModel->isOwner((int) $checkbook['account_id'], $userId);
        $isGuardian = !$isOwner && $account
            && $this->guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id']);
        $isModerator = $this->isModerator();

        if (!$isOwner && !$isGuardian && !$isModerator) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/checkbooks');
            return;
        }

        $checks = $this->checkModel->getByCheckbook((int) $checkbook['id']);

        $this->render('checkbooks/show', [
            'title'      => 'Chéquier : ' . $checkbook['label'],
            'checkbook'  => $checkbook,
            'account'    => $account,
            'checks'     => $checks,
            'isOwner'    => $isOwner,
            'isGuardian' => $isGuardian,
            'isModerator'=> $isModerator,
        ]);
    }

    // ─── Opposition chéquier ─────────────────────────────────────────────────

    public function oppose(string $id): void
    {
        $this->requireAuth();
        $this->requireFeature('checkbooks.oppose');
        $this->validateCSRF();

        $userId    = $this->getCurrentUserId();
        $checkbook = $this->checkbookModel->find((int) $id);

        if (!$checkbook) {
            $this->setFlash('danger', 'Chéquier introuvable.');
            $this->redirect('/checkbooks');
            return;
        }

        if ($checkbook['status'] === Checkbook::STATUS_OPPOSED) {
            $this->setFlash('warning', 'Ce chéquier est déjà en opposition.');
            $this->redirect('/checkbooks/' . $id);
            return;
        }

        $account    = $this->accountModel->find((int) $checkbook['account_id']);
        $isOwner    = $account && $this->accountModel->isOwner((int) $checkbook['account_id'], $userId);
        $isGuardian = !$isOwner && $account
            && $this->guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id']);

        if (!$isOwner && !$isGuardian && !$this->isModerator()) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/checkbooks');
            return;
        }

        $this->checkbookModel->oppose((int) $id);

        AuditLog::log(
            $userId,
            'checkbook_opposed',
            ['checkbook_id' => (int) $id, 'label' => $checkbook['label']],
            targetAccountId: (int) $checkbook['account_id']
        );

        // Notification au propriétaire du compte
        if ($account) {
            $this->notifModel->notify(
                (int) $account['user_id'],
                'check_opposition',
                'Opposition chéquier',
                'Le chéquier « ' . $checkbook['label'] . ' » a été mis en opposition. Tous les chèques non encaissés sont annulés.',
                '/checkbooks/' . $id
            );
        }

        $this->setFlash('success', 'Chéquier mis en opposition. Tous les chèques en attente sont annulés.');
        $this->redirect('/checkbooks/' . $id);
    }

    // ─── Opposition chèque individuel ────────────────────────────────────────

    public function opposeCheck(string $checkbookId, string $checkId): void
    {
        $this->requireAuth();
        $this->requireFeature('checkbooks.oppose');
        $this->validateCSRF();

        $userId    = $this->getCurrentUserId();
        $checkbook = $this->checkbookModel->find((int) $checkbookId);

        if (!$checkbook) {
            $this->setFlash('danger', 'Chéquier introuvable.');
            $this->redirect('/checkbooks');
            return;
        }

        $check = $this->checkModel->find((int) $checkId);
        if (!$check || (int) $check['checkbook_id'] !== (int) $checkbookId) {
            $this->setFlash('danger', 'Chèque introuvable.');
            $this->redirect('/checkbooks/' . $checkbookId);
            return;
        }

        if ($check['status'] !== Check::STATUS_EMITTED) {
            $this->setFlash('warning', 'Ce chèque ne peut plus être mis en opposition (déjà encaissé ou opposé).');
            $this->redirect('/checkbooks/' . $checkbookId);
            return;
        }

        $account    = $this->accountModel->find((int) $checkbook['account_id']);
        $isOwner    = $account && $this->accountModel->isOwner((int) $checkbook['account_id'], $userId);
        $isGuardian = !$isOwner && $account
            && $this->guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id']);

        if (!$isOwner && !$isGuardian && !$this->isModerator()) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/checkbooks/' . $checkbookId);
            return;
        }

        if ($this->checkModel->oppose((int) $checkId)) {
            AuditLog::log(
                $userId,
                'check_opposed',
                [
                    'check_id'     => (int) $checkId,
                    'check_number' => (int) $check['check_number'],
                    'checkbook_id' => (int) $checkbookId,
                ],
                targetAccountId: (int) $checkbook['account_id']
            );

            $this->setFlash('success', sprintf(
                'Chèque n°%d mis en opposition.',
                (int) $check['check_number']
            ));
        } else {
            $this->setFlash('danger', 'Impossible de mettre ce chèque en opposition.');
        }

        $this->redirect('/checkbooks/' . $checkbookId);
    }

    // ─── Confirmation d'encaissement d'un chèque ────────────────────────────

    public function confirmCheck(string $checkbookId, string $checkId): void
    {
        $this->requireAuth();
        $this->requireFeature('checkbooks.confirm');
        $this->validateCSRF();

        $userId    = $this->getCurrentUserId();
        $checkbook = $this->checkbookModel->find((int) $checkbookId);

        if (!$checkbook) {
            $this->setFlash('danger', 'Chéquier introuvable.');
            $this->redirect('/checkbooks');
            return;
        }

        $check = $this->checkModel->find((int) $checkId);
        if (!$check || (int) $check['checkbook_id'] !== (int) $checkbookId) {
            $this->setFlash('danger', 'Chèque introuvable.');
            $this->redirect('/checkbooks/' . $checkbookId);
            return;
        }

        if ($check['status'] !== Check::STATUS_EMITTED) {
            $this->setFlash('warning', 'Ce chèque est déjà ' . ($check['status'] === Check::STATUS_CASHED ? 'encaissé' : 'en opposition') . '.');
            $this->redirect('/checkbooks/' . $checkbookId);
            return;
        }

        $account    = $this->accountModel->find((int) $checkbook['account_id']);
        $isOwner    = $account && $this->accountModel->isOwner((int) $checkbook['account_id'], $userId);
        $isGuardian = !$isOwner && $account
            && $this->guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id']);

        if (!$isOwner && !$isGuardian && !$this->isModerator()) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/checkbooks/' . $checkbookId);
            return;
        }

        if ($this->checkModel->confirmCash((int) $checkId)) {
            AuditLog::log(
                $userId,
                'check_cashed',
                [
                    'check_id'     => (int) $checkId,
                    'check_number' => (int) $check['check_number'],
                    'amount'       => (float) $check['amount'],
                    'checkbook_id' => (int) $checkbookId,
                ],
                targetAccountId: (int) $checkbook['account_id']
            );

            $this->setFlash('success', sprintf(
                'Chèque n°%d (%.2f €) confirmé comme encaissé.',
                (int) $check['check_number'],
                (float) $check['amount']
            ));
        } else {
            $this->setFlash('danger', 'Impossible de confirmer l\'encaissement.');
        }

        $this->redirect('/checkbooks/' . $checkbookId);
    }

    // ─── Confirmation depuis la page du compte ───────────────────────────────

    /**
     * Confirme l'encaissement d'un chèque depuis la page d'un compte,
     * puis redirige vers ce compte.
     */
    public function confirmCheckFromAccount(string $accountId, string $checkId): void
    {
        $this->requireAuth();
        $this->requireFeature('checkbooks.confirm');
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $accId  = (int) $accountId;

        $check = $this->checkModel->find((int) $checkId);
        if (!$check) {
            $this->setFlash('danger', 'Chèque introuvable.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $checkbook = $this->checkbookModel->find((int) $check['checkbook_id']);
        if (!$checkbook || (int) $checkbook['account_id'] !== $accId) {
            $this->setFlash('danger', 'Chèque non associé à ce compte.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        if ($check['status'] !== Check::STATUS_EMITTED) {
            $this->setFlash('warning', 'Ce chèque est déjà ' . ($check['status'] === Check::STATUS_CASHED ? 'encaissé' : 'en opposition') . '.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $account    = $this->accountModel->find($accId);
        $isOwner    = $account && $this->accountModel->isOwner($accId, $userId);
        $isGuardian = !$isOwner && $account
            && $this->guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id']);

        if (!$isOwner && !$isGuardian && !$this->isModerator()) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        if ($this->checkModel->confirmCash((int) $checkId)) {
            AuditLog::log(
                $userId,
                'check_cashed',
                [
                    'check_id'     => (int) $checkId,
                    'check_number' => (int) $check['check_number'],
                    'amount'       => (float) $check['amount'],
                ],
                targetAccountId: $accId
            );
            $this->setFlash('success', sprintf(
                'Chèque n°%d (%.2f €) confirmé comme encaissé — débit effectué.',
                (int) $check['check_number'],
                (float) $check['amount']
            ));
        } else {
            $this->setFlash('danger', 'Impossible de confirmer l\'encaissement.');
        }

        $this->redirect('/accounts/' . $accountId);
    }

    // ─── Opposition chèque depuis la page du compte ──────────────────────────

    public function opposeCheckFromAccount(string $accountId, string $checkId): void
    {
        $this->requireAuth();
        $this->requireFeature('checkbooks.oppose');
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $accId  = (int) $accountId;

        $check = $this->checkModel->find((int) $checkId);
        if (!$check) {
            $this->setFlash('danger', 'Chèque introuvable.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $checkbook = $this->checkbookModel->find((int) $check['checkbook_id']);
        if (!$checkbook || (int) $checkbook['account_id'] !== $accId) {
            $this->setFlash('danger', 'Chèque non associé à ce compte.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        if ($check['status'] !== Check::STATUS_EMITTED) {
            $this->setFlash('warning', 'Ce chèque ne peut plus être mis en opposition.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $account    = $this->accountModel->find($accId);
        $isOwner    = $account && $this->accountModel->isOwner($accId, $userId);
        $isGuardian = !$isOwner && $account
            && $this->guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id']);

        if (!$isOwner && !$isGuardian && !$this->isModerator()) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        if ($this->checkModel->oppose((int) $checkId)) {
            AuditLog::log(
                $userId,
                'check_opposed',
                [
                    'check_id'     => (int) $checkId,
                    'check_number' => (int) $check['check_number'],
                ],
                targetAccountId: $accId
            );
            $this->setFlash('success', sprintf(
                'Chèque n°%d mis en opposition — débit annulé.',
                (int) $check['check_number']
            ));
        } else {
            $this->setFlash('danger', 'Impossible de mettre ce chèque en opposition.');
        }

        $this->redirect('/accounts/' . $accountId);
    }
}
