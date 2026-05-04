<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AccountAccess;
use App\Models\AuditLog;
use App\Models\DirectDebit;
use App\Models\Guardianship;
use App\Models\Mandate;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Models\SavingsInterest;
use App\Models\SavingsRate;
use App\Models\DeferredDebit;
use App\Models\RecurringTransfer;

class ModerationController extends Controller
{
    private Account $accountModel;
    private AccountAccess $accessModel;
    private User $userModel;
    private Transfer $transferModel;
    private Transaction $transactionModel;
    private DirectDebit $directDebitModel;
    private Guardianship   $guardianshipModel;
    private Ticket         $ticketModel;
    private TicketMessage  $ticketMessageModel;
    private Mandate        $mandateModel;
    private Notification   $notifModel;
    private SavingsRate     $rateModel;
    private SavingsInterest $interestModel;
    private RecurringTransfer $recurringTransferModel;

    public function __construct()
    {
        $this->accountModel       = new Account();
        $this->accessModel        = new AccountAccess();
        $this->userModel          = new User();
        $this->transferModel      = new Transfer();
        $this->transactionModel   = new Transaction();
        $this->directDebitModel   = new DirectDebit();
        $this->guardianshipModel  = new Guardianship();
        $this->ticketModel        = new Ticket();
        $this->ticketMessageModel = new TicketMessage();
        $this->mandateModel       = new Mandate();
        $this->notifModel         = new Notification();
        $this->rateModel          = new SavingsRate();
        $this->interestModel      = new SavingsInterest();
        $this->recurringTransferModel = new RecurringTransfer();
    }

    /**
     * Tableau de bord modérateur — liste tous les comptes.
     */
    public function index(): void
    {
        $this->requireModerator();

        $allAccounts = $this->accountModel->findAll('id', 'ASC');

        // Enrichir chaque compte avec son solde, son propriétaire et les utilisateurs avec accès partagé
        foreach ($allAccounts as &$acc) {
            $acc['balance'] = $this->accountModel->getBalance((int) $acc['id']);
            $owner = $this->userModel->find((int) $acc['user_id']);
            $acc['owner_name'] = $owner ? $owner['username'] : 'Inconnu';

            $sharedUsers = [];
            $accesses = $this->accessModel->getAccessesForAccount((int) $acc['id']);
            foreach ($accesses as $access) {
                $u = $this->userModel->find((int) $access['user_id']);
                if ($u) {
                    $sharedUsers[] = $u['username'];
                }
            }
            $acc['shared_users'] = $sharedUsers;
        }
        unset($acc);

        $this->render('moderation/index', [
            'title'       => 'Modération — Comptes',
            'allAccounts' => $allAccounts,
            'pendingClosureCount' => $this->accountModel->countDisabled(),
        ]);
    }

    /**
     * Geler un compte (POST).
     */
    public function freeze(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $accountId = (int) $id;
        $account   = $this->accountModel->find($accountId);

        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/moderation');
            return;
        }

        $this->accountModel->freezeAccount($accountId);
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_ACCOUNT_FREEZE, ['name' => $account['name']], targetUserId: (int) $account['user_id'], targetAccountId: $accountId);
        // Notifier le propriétaire du compte (et ses tuteurs si compte mineur)
        $this->notifyAccountOwner(
            (int) $account['user_id'],
            $accountId,
            'account_frozen',
            'Compte « ' . $account['name'] . ' » gelé',
            'Votre compte a été gelé par la modération. Les opérations sortantes sont bloquées.'
        );
        $this->setFlash('success', 'Compte « ' . $account['name'] . ' » gelé avec succès.');
        $this->redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/moderation');
    }

    /**
     * Dégeler un compte (POST).
     */
    public function unfreeze(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $accountId = (int) $id;
        $account   = $this->accountModel->find($accountId);

        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/moderation');
            return;
        }

        $this->accountModel->unfreezeAccount($accountId);
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_ACCOUNT_UNFREEZE, ['name' => $account['name']], targetUserId: (int) $account['user_id'], targetAccountId: $accountId);
        // Notifier le propriétaire du compte (et ses tuteurs si compte mineur)
        $this->notifyAccountOwner(
            (int) $account['user_id'],
            $accountId,
            'account_unfrozen',
            'Compte « ' . $account['name'] . ' » dégelé',
            'Les restrictions sur votre compte ont été levées. Vous pouvez effectuer à nouveau des opérations sortantes.'
        );
        $this->setFlash('success', 'Compte « ' . $account['name'] . ' » dégelé avec succès.');
        $this->redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/moderation');
    }

    /**
     * Activer / désactiver le débit différé sur un compte (POST).
     */
    public function toggleDeferredDebit(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $accountId = (int) $id;
        $account   = $this->accountModel->find($accountId);

        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/moderation');
            return;
        }

        if (!Account::typeAllowsDeferredDebit($account['type'] ?? '')) {
            $this->setFlash('danger', 'Ce type de compte ne peut pas disposer du débit différé.');
            $this->redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/moderation');
            return;
        }

        $current = !empty($account['deferred_debit_enabled']);
        $this->accountModel->update($accountId, ['deferred_debit_enabled' => $current ? 0 : 1]);

        $action = $current ? 'désactivé' : 'activé';
        AuditLog::log(
            $this->getCurrentUserId(),
            'account.deferred_debit_toggle',
            ['name' => $account['name'], 'enabled' => !$current],
            targetUserId: (int) $account['user_id'],
            targetAccountId: $accountId
        );
        $this->setFlash('success', sprintf(
            'Débit différé %s sur le compte « %s ».',
            $action,
            $account['name']
        ));
        $this->redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/moderation');
    }

    /**
     * Désactiver un compte (modération) — POST.
     * Le compte est marqué disabled_at = NOW() et sera définitivement supprimé en fin de mois.
     */
    public function disableAccount(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $accountId = (int) $id;
        $account   = $this->accountModel->find($accountId);

        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/moderation');
            return;
        }

        if (!empty($account['disabled_at'])) {
            $this->setFlash('info', 'Ce compte est déjà en cours de résiliation.');
            $this->redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/moderation');
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
            $this->redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/moderation');
            return;
        }

        // Bloquer la résiliation si des débits différés sont en attente
        $deferredDebitModel = new DeferredDebit();
        $pendingDD = $deferredDebitModel->getPendingByAccount($accountId);
        if (!empty($pendingDD)) {
            $this->setFlash('danger', sprintf(
                'Impossible de résilier ce compte : %d opération(s) à débit différé en attente d\'encaissement.',
                count($pendingDD)
            ));
            $this->redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/moderation');
            return;
        }

        $this->accountModel->disableAccount($accountId);
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_ACCOUNT_DISABLE, ['name' => $account['name']], targetUserId: (int) $account['user_id'], targetAccountId: $accountId);
        $this->notifyAccountOwner(
            (int) $account['user_id'],
            $accountId,
            'account_disabled',
            'Compte « ' . $account['name'] . ' » désactivé',
            'Votre compte a été désactivé par la modération. Il sera définitivement supprimé à la fin du mois. Les prélèvements du mois en cours restent effectifs.'
        );
        $this->setFlash('success', 'Compte « ' . $account['name'] . ' » désactivé. Suppression en fin de mois.');
        $this->redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/moderation');
    }

    /**
     * Forcer immédiatement l'exécution du processus de clôture des comptes désactivés
     * (équivalent du CRON mensuel, mais sans attendre la fin du mois).
     */
    public function forceCloseAccounts(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $pending = $this->accountModel->countDisabled();
        if ($pending === 0) {
            $this->setFlash('info', 'Aucun compte désactivé à clôturer.');
            $this->redirect('/moderation');
            return;
        }

        // Inclusion du script de cron en mode forçage, sortie capturée pour le journal.
        $forceClose = true;
        ob_start();
        try {
            require dirname(__DIR__, 2) . '/database/process_account_closures.php';
        } catch (\Throwable $e) {
            ob_end_clean();
            AuditLog::log(
                $this->getCurrentUserId(),
                'account.force_close_failed',
                ['error' => $e->getMessage()]
            );
            $this->setFlash('danger', 'Erreur lors de la clôture forcée : ' . $e->getMessage());
            $this->redirect('/moderation');
            return;
        }
        $output = ob_get_clean();

        // Persiste la sortie dans le même fichier de log que le CRON.
        $logFile = dirname(__DIR__, 2) . '/data/closures-cron.log';
        @file_put_contents(
            $logFile,
            sprintf("[%s] === Forçage manuel par modérateur #%d ===\n", date('Y-m-d H:i:s'), $this->getCurrentUserId())
                . $output . "\n",
            FILE_APPEND
        );

        $closed = $result['closed'] ?? 0;
        $errors = $result['errors'] ?? 0;

        AuditLog::log(
            $this->getCurrentUserId(),
            'account.force_close',
            ['closed' => $closed, 'errors' => $errors, 'pending_before' => $pending]
        );

        if ($errors > 0) {
            $this->setFlash('warning', sprintf(
                'Clôture forcée terminée : %d compte(s) clôturé(s), %d erreur(s). Voir le journal.',
                $closed, $errors
            ));
        } else {
            $this->setFlash('success', sprintf(
                'Clôture forcée terminée : %d compte(s) définitivement supprimé(s).',
                $closed
            ));
        }

        $this->redirect('/moderation');
    }

    /**
     * Réactiver un compte désactivé (modération) — POST.
     */
    public function enableAccount(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $accountId = (int) $id;
        $account   = $this->accountModel->find($accountId);

        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/moderation');
            return;
        }

        if (empty($account['disabled_at'])) {
            $this->setFlash('info', 'Ce compte n\'est pas désactivé.');
            $this->redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/moderation');
            return;
        }

        $this->accountModel->enableAccount($accountId);
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_ACCOUNT_ENABLE, ['name' => $account['name']], targetUserId: (int) $account['user_id'], targetAccountId: $accountId);
        $this->notifyAccountOwner(
            (int) $account['user_id'],
            $accountId,
            'account_enabled',
            'Compte « ' . $account['name'] . ' » réactivé',
            'La résiliation de votre compte a été annulée par la modération. Votre compte fonctionne de nouveau normalement.'
        );
        $this->setFlash('success', 'Compte « ' . $account['name'] . ' » réactivé avec succès.');
        $this->redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/moderation');
    }
    public function users(): void
    {
        $this->requireModerator();

        $allUsers = $this->userModel->findAll('id', 'ASC');
        $currentUserId = $this->getCurrentUserId();

        $this->render('moderation/users', [
            'title'         => 'Modération — Utilisateurs',
            'allUsers'      => $allUsers,
            'currentUserId' => $currentUserId,
        ]);
    }

    /**
     * Modifier le rôle d'un utilisateur (POST).
     */
    public function setRole(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $targetId = (int) $id;

        // Un modérateur ne peut pas changer son propre rôle
        if ($targetId === $this->getCurrentUserId()) {
            $this->setFlash('danger', 'Vous ne pouvez pas modifier votre propre rôle.');
            $this->redirect('/moderation/users');
            return;
        }

        $data = $this->getPostData(['role']);
        $role = $data['role'];

        $target = $this->userModel->find($targetId);
        if (!$target) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/moderation/users');
            return;
        }

        if (!$this->userModel->updateRole($targetId, $role)) {
            $this->setFlash('danger', 'Rôle invalide.');
            $this->redirect('/moderation/users');
            return;
        }

        $labels = ['user' => 'Utilisateur', 'moderator' => 'Modérateur'];
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_USER_ROLE_CHANGE, ['username' => $target['username'], 'new_role' => $role], targetUserId: $targetId);
        $this->setFlash('success', 'Rôle de « ' . $target['username'] . ' » mis à jour : ' . ($labels[$role] ?? $role) . '.');
        $this->redirect('/moderation/users');
    }

    /**
     * Suspendre un compte utilisateur (POST).
     */
    public function suspendUser(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $targetId = (int) $id;

        if ($targetId === $this->getCurrentUserId()) {
            $this->setFlash('danger', 'Vous ne pouvez pas suspendre votre propre compte.');
            $this->redirect('/moderation/users');
            return;
        }

        $target = $this->userModel->find($targetId);
        if (!$target) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/moderation/users');
            return;
        }

        if (($target['global_role'] ?? 'user') === 'moderator') {
            $this->setFlash('danger', 'Impossible de suspendre un modérateur.');
            $this->redirect('/moderation/users');
            return;
        }

        $data  = $this->getPostData(['suspended_until']);
        $until = !empty($data['suspended_until']) ? trim($data['suspended_until']) : null;

        if ($until !== null) {
            $dt = \DateTime::createFromFormat('Y-m-d', $until);
            if (!$dt || $dt->format('Y-m-d') !== $until) {
                $this->setFlash('danger', 'Date de suspension invalide.');
                $this->redirect('/moderation/users');
                return;
            }
            if ($dt <= new \DateTime('today')) {
                $this->setFlash('danger', 'La date de fin de suspension doit être dans le futur.');
                $this->redirect('/moderation/users');
                return;
            }
            $until = $dt->format('Y-m-d') . ' 23:59:59';
        }

        $this->userModel->suspend($targetId, $until);

        $msg  = '« ' . $target['username'] . ' » est suspendu';
        $msg .= $until
            ? ' jusqu\'au ' . (new \DateTime($until))->format('d/m/Y') . '.'
            : ' indéfiniment.';
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_USER_SUSPEND, ['username' => $target['username'], 'until' => $until], targetUserId: $targetId);
        // Notifier l'utilisateur suspendu
        $notifBody = 'Votre compte a été suspendu' . ($until
            ? ' jusqu\'au ' . (new \DateTime($until))->format('d/m/Y') . '.'
            : ' indéfiniment.');
        $this->notifModel->notify(
            $targetId,
            'account_suspended',
            'Votre compte a été suspendu',
            $notifBody,
            null
        );
        $this->setFlash('success', $msg);
        $this->redirect('/moderation/users');
    }

    /**
     * Bannir un compte utilisateur (POST).
     */
    public function banUser(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $targetId = (int) $id;

        if ($targetId === $this->getCurrentUserId()) {
            $this->setFlash('danger', 'Vous ne pouvez pas bannir votre propre compte.');
            $this->redirect('/moderation/users');
            return;
        }

        $target = $this->userModel->find($targetId);
        if (!$target) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/moderation/users');
            return;
        }

        if (($target['global_role'] ?? 'user') === 'moderator') {
            $this->setFlash('danger', 'Impossible de bannir un modérateur.');
            $this->redirect('/moderation/users');
            return;
        }

        $this->userModel->ban($targetId);
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_USER_BAN, ['username' => $target['username']], targetUserId: $targetId);
        // Notifier l'utilisateur banni
        $this->notifModel->notify(
            $targetId,
            'account_banned',
            'Votre compte a été banni',
            'Votre compte a été définitivement banni de la plateforme par la modération.',
            null
        );
        $this->setFlash('success', '« ' . $target['username'] . ' » a été banni de la plateforme.');
        $this->redirect('/moderation/users');
    }

    /**
     * Réactiver un compte utilisateur suspendu ou banni (POST).
     */
    public function activateUser(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $targetId = (int) $id;

        $target = $this->userModel->find($targetId);
        if (!$target) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/moderation/users');
            return;
        }

        $this->userModel->activate($targetId);
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_USER_ACTIVATE, ['username' => $target['username']], targetUserId: $targetId);
        // Notifier l'utilisateur réactivé
        $this->notifModel->notify(
            $targetId,
            'account_activated',
            'Votre compte a été réactivé',
            'Votre compte a été réactivé par la modération. Vous pouvez de nouveau vous connecter et utiliser la plateforme.',
            null
        );
        $this->setFlash('success', 'Le compte de « ' . $target['username'] . ' » a été réactivé.');
        $this->redirect('/moderation/users');
    }

    /**
     * Réinitialise le code PIN d'un utilisateur et génère un code temporaire (POST).
     * Le code temporaire est affiché une seule fois dans le flash de succès.
     */
    public function resetUserPin(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $targetId = (int) $id;

        if ($targetId === $this->getCurrentUserId()) {
            $this->setFlash('danger', 'Vous ne pouvez pas réinitialiser votre propre code PIN.');
            $this->redirect('/moderation/users');
            return;
        }

        $target = $this->userModel->find($targetId);
        if (!$target) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/moderation/users');
            return;
        }

        $tempPin = $this->userModel->resetPinByModerator($targetId);

        AuditLog::log(
            $this->getCurrentUserId(),
            AuditLog::ACTION_USER_PIN_RESET,
            ['username' => $target['username']],
            targetUserId: $targetId
        );

        $this->notifModel->notify(
            $targetId,
            'pin_reset_by_moderator',
            'Code PIN réinitialisé par la modération',
            'Votre code PIN a été réinitialisé par la modération. Un code temporaire vous a été communiqué. Vous devrez le modifier lors de votre prochaine connexion ou depuis votre profil.',
            null
        );

        $this->setFlash(
            'success',
            'Code PIN de « ' . e($target['username']) . ' » réinitialisé. '
            . 'Code temporaire : <strong>' . e($tempPin) . '</strong>'
            . ' — à communiquer à l\'utilisateur (visible une seule fois).'
        );
        $this->redirect('/moderation/users');
    }

    /**
     * Liste de tous les virements (espace modération).
     */
    public function transfers(): void
    {
        $this->requireModerator();

        $allUsers    = $this->userModel->findAll('username', 'ASC');
        $usersMap    = array_column($allUsers, null, 'id');
        $accountsMap = array_column($this->accountModel->findAll('id', 'ASC'), null, 'id');

        $rawTransfers      = $this->transferModel->findAll('created_at', 'DESC');
        $enrichedTransfers = [];
        foreach ($rawTransfers as $t) {
            $tUid    = (int) ($t['user_id'] ?? 0);
            $fromAcc = $accountsMap[$t['from_account_id']] ?? null;
            $toAcc   = $accountsMap[$t['to_account_id']]   ?? null;
            $tUser   = $usersMap[$tUid] ?? null;
            $enrichedTransfers[] = [
                'id'           => (int) $t['id'],
                'user_name'    => $tUser   ? ($tUser['username']  ?? 'Utilisateur #' . $tUid)            : 'Utilisateur #' . $tUid,
                'from_account' => $fromAcc ? ($fromAcc['name']    ?? 'Compte #' . $t['from_account_id']) : 'Compte #' . $t['from_account_id'],
                'to_account'   => $toAcc   ? ($toAcc['name']      ?? 'Compte #' . $t['to_account_id'])   : 'Compte #' . $t['to_account_id'],
                'amount'       => (float)  ($t['amount']       ?? 0),
                'motif'        => $t['motif']       ?? '',
                'status'       => $t['status']       ?? Transfer::STATUS_SUCCESS,
                'scheduled_at' => $t['scheduled_at'] ?? null,
                'executed_at'  => $t['executed_at']  ?? null,
                'created_at'   => $t['created_at']   ?? null,
            ];
        }

        $transfersJson = json_encode(
            $enrichedTransfers,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

        $tfAuthors = [];
        foreach ($enrichedTransfers as $tf) {
            if (!empty($tf['user_name']) && !in_array($tf['user_name'], $tfAuthors, true)) {
                $tfAuthors[] = $tf['user_name'];
            }
        }
        sort($tfAuthors);

        $this->render('moderation/transfers', [
            'title'         => 'Modération — Virements',
            'transfersJson' => $transfersJson,
            'tfAuthors'     => $tfAuthors,
            'totalCount'    => count($enrichedTransfers),
            'csrfToken'     => csrf_token(),
        ]);
    }

    /**
     * Annuler un virement (POST) — modérateurs uniquement.
     * Conditions : statut « scheduled » OU « success » exécuté il y a ≤ 7 jours.
     */
    public function cancelTransfer(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $transferId = (int) $id;
        $transfer   = $this->transferModel->find($transferId);

        if (!$transfer) {
            $this->setFlash('danger', 'Virement introuvable.');
            $this->redirect('/moderation/transfers');
            return;
        }

        if (!$this->transferModel->canCancel($transfer)) {
            $this->setFlash('danger', 'Ce virement ne peut pas être annulé : statut incompatible ou délai de 7 jours dépassé.');
            $this->redirect('/moderation/transfers');
            return;
        }

        $moderatorId = $this->getCurrentUserId();
        $amount      = (float) $transfer['amount'];
        $motif       = 'Annulation virement #' . $transferId;

        // Remboursement sur le compte émetteur (income)
        // user_id = 0 : action de modération → affiché comme "Modération" sur le compte
        $this->transactionModel->addTransaction(
            (int) $transfer['from_account_id'],
            'income',
            $amount,
            'Virement',
            $motif,
            0
        );

        // Récupération sur le compte destinataire (expense)
        $this->transactionModel->addTransaction(
            (int) $transfer['to_account_id'],
            'expense',
            $amount,
            'Virement',
            $motif,
            0
        );

        $this->transferModel->markCancelled($transferId);

        AuditLog::log($moderatorId, AuditLog::ACTION_TRANSFER_CANCEL, ['transfer_id' => $transferId, 'amount' => $amount], targetAccountId: (int) $transfer['from_account_id']);
        // Notifier les propriétaires des comptes concernés (et leurs tuteurs si mineurs)
        $fromAccount = $this->accountModel->find((int) $transfer['from_account_id']);
        $toAccount   = $this->accountModel->find((int) $transfer['to_account_id']);
        if ($fromAccount) {
            $this->notifyAccountOwner(
                (int) $fromAccount['user_id'],
                (int) $fromAccount['id'],
                'transfer_cancelled',
                'Virement #' . $transferId . ' annulé',
                'Le virement de ' . number_format($amount, 2, ',', ' ') . ' € depuis votre compte « ' . $fromAccount['name'] . ' » a été annulé par la modération. Le montant a été recrédité.'
            );
        }
        if ($toAccount && $toAccount['user_id'] !== ($fromAccount['user_id'] ?? null)) {
            $this->notifyAccountOwner(
                (int) $toAccount['user_id'],
                (int) $toAccount['id'],
                'transfer_cancelled',
                'Virement #' . $transferId . ' annulé',
                'Un virement de ' . number_format($amount, 2, ',', ' ') . ' € vers votre compte « ' . $toAccount['name'] . ' » a été annulé par la modération.'
            );
        }

        $this->setFlash('success', 'Virement #' . $transferId . ' annulé avec succès. Les soldes ont été rétablis.');
        $this->redirect('/moderation/transfers');
    }

    // =========================================================
    // PRÉLÈVEMENTS
    // =========================================================

    /**
     * Liste de tous les prélèvements.
     */
    public function directDebits(): void
    {
        $this->requireModerator();

        $accountsMap = array_column($this->accountModel->findAll('id', 'ASC'), null, 'id');
        $allUsers    = $this->userModel->findAll('username', 'ASC');
        $usersMap    = array_column($allUsers, null, 'id');

        $rawDebits = $this->directDebitModel->findAll('created_at', 'DESC');
        $enriched  = [];
        foreach ($rawDebits as $d) {
            $toAcc   = $accountsMap[$d['to_account_id']]   ?? null;
            $fromAcc = ($d['from_account_id'] !== null) ? ($accountsMap[$d['from_account_id']] ?? null) : null;
            $creator = $usersMap[$d['created_by']] ?? null;
            $enriched[] = [
                'id'              => (int) $d['id'],
                'mandate_number'  => $d['mandate_number'],
                'scheduled_at'    => $d['scheduled_at'],
                'executed_at'     => $d['executed_at'],
                'amount'          => (float) $d['amount'],
                'motif'           => $d['motif'] ?? '',
                'from_account'    => $fromAcc ? ($fromAcc['name'] ?? 'Compte #' . $d['from_account_id']) : 'Banque',
                'to_account'      => $toAcc   ? ($toAcc['name']   ?? 'Compte #' . $d['to_account_id'])   : 'Compte #' . $d['to_account_id'],
                'status'          => $d['status'],
                'created_by_name' => $creator ? $creator['username'] : 'Modération',
                'created_at'      => $d['created_at'],
            ];
        }

        $debitsJson = json_encode(
            $enriched,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

        $this->render('moderation/direct_debits', [
            'title'      => 'Modération — Prélèvements',
            'debitsJson' => $debitsJson,
            'totalCount' => count($enriched),
            'csrfToken'  => csrf_token(),
        ]);
    }

    /**
     * Formulaire de création d'un prélèvement (GET).
     */
    public function createDirectDebitForm(): void
    {
        $this->requireModerator();

        $allAccounts = $this->accountModel->findAll('name', 'ASC');

        $this->render('moderation/direct_debits_create', [
            'title'       => 'Modération — Nouveau prélèvement',
            'allAccounts' => $allAccounts,
            'csrfToken'   => csrf_token(),
        ]);
    }

    /**
     * Créer un prélèvement (POST).
     */
    public function createDirectDebit(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $data = $this->getPostData(['mandate_number', 'scheduled_at', 'amount', 'motif', 'from_account_id', 'to_account_id']);

        // Validation du numéro de mandat
        $mandateNumber = trim($data['mandate_number'] ?? '');
        if ($mandateNumber === '') {
            $this->setFlash('danger', 'Le numéro de mandat est obligatoire.');
            $this->redirect('/moderation/direct-debits/create');
            return;
        }

        // Validation de la date d'exécution
        $dt = parse_datetime_input($data['scheduled_at'] ?? '');
        if (!$dt) {
            $this->setFlash('danger', 'La date d\'exécution est invalide (format attendu : jj/mm/aaaa hh:mm).');
            $this->redirect('/moderation/direct-debits/create');
            return;
        }
        $scheduledAt = $dt->format('Y-m-d H:i:s');

        // Validation du montant
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant doit être strictement positif.');
            $this->redirect('/moderation/direct-debits/create');
            return;
        }

        // Compte destinataire (obligatoire)
        $toAccountId = (int) ($data['to_account_id'] ?? 0);
        $toAccount   = $toAccountId > 0 ? $this->accountModel->find($toAccountId) : null;
        if (!$toAccount) {
            $this->setFlash('danger', 'Compte destinataire invalide ou introuvable.');
            $this->redirect('/moderation/direct-debits/create');
            return;
        }

        // Compte émetteur (facultatif — vide ou 0 = banque)
        $fromAccountRaw = trim($data['from_account_id'] ?? '');
        $fromAccountId  = null;
        if ($fromAccountRaw !== '' && $fromAccountRaw !== '0') {
            $fromAccountId = (int) $fromAccountRaw;
            if (!$this->accountModel->find($fromAccountId)) {
                $this->setFlash('danger', 'Compte émetteur introuvable.');
                $this->redirect('/moderation/direct-debits/create');
                return;
            }
            if ($fromAccountId === $toAccountId) {
                $this->setFlash('danger', 'Le compte émetteur et le compte destinataire doivent être différents.');
                $this->redirect('/moderation/direct-debits/create');
                return;
            }
        }

        $motif = trim($data['motif'] ?? '') ?: null;

        $this->directDebitModel->createDirectDebit(
            $mandateNumber,
            $scheduledAt,
            $amount,
            $toAccountId,
            $fromAccountId,
            $motif,
            $this->getCurrentUserId()
        );

        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_DIRECT_DEBIT_CREATE, ['mandate' => $mandateNumber, 'amount' => $amount], targetAccountId: $toAccountId);
        $this->setFlash('success', sprintf(
            'Prélèvement de %s € planifié pour le %s sur « %s » (mandat %s).',
            number_format($amount, 2, ',', ' '),
            date('d/m/Y à H\hi', $dt->getTimestamp()),
            $toAccount['name'],
            $mandateNumber
        ));
        $this->redirect('/moderation/direct-debits');
    }

    /**
     * Annuler un prélèvement planifié (POST).
     */
    public function cancelDirectDebit(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $debitId     = (int) $id;
        $directDebit = $this->directDebitModel->find($debitId);

        if (!$directDebit) {
            $this->setFlash('danger', 'Prélèvement introuvable.');
            $this->redirect('/moderation/direct-debits');
            return;
        }

        if (!$this->directDebitModel->canCancel($directDebit)) {
            $this->setFlash('danger', 'Ce prélèvement ne peut pas être annulé (statut incompatible).');
            $this->redirect('/moderation/direct-debits');
            return;
        }

        $this->directDebitModel->markCancelled($debitId);
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_DIRECT_DEBIT_CANCEL, ['mandate_number' => $directDebit['mandate_number'], 'amount' => (float) $directDebit['amount']], targetAccountId: (int) $directDebit['to_account_id']);
        // Notifier le propriétaire du compte débité (et ses tuteurs si compte mineur)
        $toAccount = $this->accountModel->find((int) $directDebit['to_account_id']);
        if ($toAccount) {
            $this->notifyAccountOwner(
                (int) $toAccount['user_id'],
                (int) $directDebit['to_account_id'],
                'direct_debit_cancelled',
                'Prélèvement #' . $debitId . ' annulé',
                'Le prélèvement (mandat ' . $directDebit['mandate_number'] . ') de ' . number_format((float) $directDebit['amount'], 2, ',', ' ') . ' € prévu sur votre compte « ' . $toAccount['name'] . ' » a été annulé par la modération.'
            );
        }
        $this->setFlash('success', 'Prélèvement #' . $debitId . ' (mandat ' . $directDebit['mandate_number'] . ') annulé.');
        $this->redirect('/moderation/direct-debits');
    }

    /**
     * Rejeter un prélèvement exécuté (POST) — crée des transactions inverses.
     */
    public function rejectDirectDebit(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $debitId     = (int) $id;
        $directDebit = $this->directDebitModel->find($debitId);

        if (!$directDebit) {
            $this->setFlash('danger', 'Prélèvement introuvable.');
            $this->redirect('/moderation/direct-debits');
            return;
        }

        if (!$this->directDebitModel->canReject($directDebit)) {
            $this->setFlash('danger', 'Ce prélèvement ne peut pas être rejeté (seuls les prélèvements exécutés sont rejetables).');
            $this->redirect('/moderation/direct-debits');
            return;
        }

        $moderatorId = $this->getCurrentUserId();
        $within48h   = $this->directDebitModel->isWithin48hOfExecution($directDebit);
        $reason      = trim((string) ($_POST['reason'] ?? ''));

        // Dans les 48 h suivant l'exécution : motif + mot de passe obligatoires.
        // Au-delà : rejet libre, sans motif ni mot de passe.
        if ($within48h) {
            if ($reason === '') {
                $this->setFlash('danger', 'Le motif de rejet est obligatoire dans les 48 h suivant l\'exécution.');
                $this->redirect('/moderation/direct-debits');
                return;
            }
            if (mb_strlen($reason) > 500) {
                $reason = mb_substr($reason, 0, 500);
            }

            $password = (string) ($_POST['password'] ?? '');
            $modUser  = $this->userModel->find($moderatorId);
            if (!$modUser || $password === '' || !password_verify($password, $modUser['password'])) {
                $this->setFlash('danger', 'Mot de passe incorrect : rejet annulé.');
                $this->redirect('/moderation/direct-debits');
                return;
            }
        } else {
            // Hors fenêtre 48 h : motif facultatif (tronqué si fourni).
            if ($reason !== '' && mb_strlen($reason) > 500) {
                $reason = mb_substr($reason, 0, 500);
            }
        }

        $amount        = (float) $directDebit['amount'];
        $toAccountId   = (int) $directDebit['to_account_id'];
        $fromAccountId = $directDebit['from_account_id'] !== null ? (int) $directDebit['from_account_id'] : null;
        $comment       = 'Rejet prélèvement mandat ' . $directDebit['mandate_number']
            . ($reason !== '' ? ' — Motif : ' . $reason : '');

        // Remboursement du compte débité (crédit = reversal)
        $this->transactionModel->addTransaction(
            $toAccountId,
            'income',
            $amount,
            'Rejet de prélèvement',
            $comment,
            $moderatorId
        );

        // Reprise sur le compte émetteur si applicable (débit = reversal)
        if ($fromAccountId !== null) {
            $this->transactionModel->addTransaction(
                $fromAccountId,
                'expense',
                $amount,
                'Rejet de prélèvement',
                $comment,
                $moderatorId
            );
        }

        $this->directDebitModel->markRejected($debitId, $reason !== '' ? $reason : null);

        AuditLog::log($moderatorId, AuditLog::ACTION_DIRECT_DEBIT_REJECT, ['mandate_number' => $directDebit['mandate_number'], 'amount' => $amount, 'reason' => $reason], targetAccountId: $toAccountId);
        // Notifier le propriétaire du compte débité (et ses tuteurs si compte mineur)
        $toAccount = $this->accountModel->find($toAccountId);
        if ($toAccount) {
            $notifBody = 'Le prélèvement (mandat ' . $directDebit['mandate_number'] . ') de '
                . number_format($amount, 2, ',', ' ') . ' € sur votre compte « ' . $toAccount['name']
                . ' » a été rejeté par la modération.'
                . ($reason !== '' ? ' Motif : ' . $reason . '.' : '')
                . ' Le montant a été recrédité.';
            $this->notifyAccountOwner(
                (int) $toAccount['user_id'],
                $toAccountId,
                'direct_debit_rejected',
                'Prélèvement rejeté — ' . number_format($amount, 2, ',', ' ') . ' €',
                $notifBody
            );
        }

        $this->setFlash('success', sprintf(
            'Prélèvement #%d (mandat %s) rejeté — montant de %s € recrédité sur le compte.',
            $debitId,
            $directDebit['mandate_number'],
            number_format($amount, 2, ',', ' ')
        ));
        $this->redirect('/moderation/direct-debits');
    }

    /**
     * Réexécute un prélèvement rejeté ou échoué (POST).
     */
    public function retryDirectDebit(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $debitId     = (int) $id;
        $directDebit = $this->directDebitModel->find($debitId);

        if (!$directDebit) {
            $this->setFlash('danger', 'Prélèvement introuvable.');
            $this->redirect('/moderation/direct-debits');
            return;
        }

        if (!$this->directDebitModel->canRetry($directDebit)) {
            $this->setFlash('danger', 'Ce prélèvement ne peut pas être réexécuté (statut incompatible ou limite d\'une réexécution déjà atteinte).');
            $this->redirect('/moderation/direct-debits');
            return;
        }

        $retryData    = $this->getPostData(['scheduled_at']);
        $scheduledAt  = null;
        $rawRetryDate = trim($retryData['scheduled_at'] ?? '');
        if ($rawRetryDate !== '') {
            $dt = parse_datetime_input($rawRetryDate);
            if (!$dt) {
                $this->setFlash('danger', 'Date de planification invalide (format attendu : jj/mm/aaaa hh:mm).');
                $this->redirect('/moderation/direct-debits');
                return;
            }
            $scheduledAt = $dt->format('Y-m-d H:i:s');
        }

        $newId = $this->directDebitModel->retry($debitId, $scheduledAt);

        if ($newId === null) {
            $this->setFlash('danger', 'Erreur lors de la réexécution du prélèvement.');
            $this->redirect('/moderation/direct-debits');
            return;
        }

        $dateInfo = $scheduledAt ? ' pour le ' . (new \DateTime($scheduledAt))->format('d/m/Y à H\hi') : '';
        $this->setFlash('success', sprintf(
            'Prélèvement #%d (mandat %s) reprogrammé%s → nouveau prélèvement #%d planifié.',
            $debitId,
            $directDebit['mandate_number'],
            $dateInfo,
            $newId
        ));
        $this->redirect('/moderation/direct-debits');
    }

    /**
     * Recherche de comptes pour l'autocomplete (GET, JSON).
     * Paramètre : ?q=terme_de_recherche
     */
    public function searchAccounts(): void
    {
        $this->requireModerator();

        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            $this->json([]);
            return;
        }

        $type = isset($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : null;

        $rows    = $this->accountModel->searchByQuery($q, 15, $type);
        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id'       => (int) $row['id'],
                'label'    => $row['name'] . ' (' . $row['username'] . ') — ' . strtoupper((string) $row['currency']),
                'name'     => $row['name'],
                'currency' => $row['currency'],
                'owner'    => $row['username'],
            ];
        }

        $this->json($results);
    }

    public function searchUsers(): void
    {
        $this->requireModerator();

        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            $this->json([]);
            return;
        }

        $type = $_GET['type'] ?? 'all'; // 'minor', 'adult', 'all'
        $rows = $this->userModel->searchByQuery($q, 20);

        $results = [];
        foreach ($rows as $row) {
            $isMinor = User::isMinorFromDate($row['birth_date'] ?? null);
            if ($type === 'minor' && !$isMinor) continue;
            if ($type === 'adult' && $isMinor) continue;

            $results[] = [
                'id'       => (int) $row['id'],
                'label'    => $row['username'] . ' — ' . $row['email'],
                'username' => $row['username'],
                'email'    => $row['email'],
            ];
        }

        $this->json($results);
    }

    // =========================================================
    // TUTELLES LÉGALES — COMPTES MINEURS
    // =========================================================

    /**
     * Formulaire de création d'un compte mineur (GET).
     * Le modérateur choisit l'utilisateur mineur, le nom du compte et 1 ou 2 tuteurs adultes.
     */
    public function createMinorAccountForm(): void
    {
        $this->requireModerator();

        $allUsers   = $this->userModel->findAll('username', 'ASC');
        $minorUsers = array_values(array_filter($allUsers, fn($u) => User::isMinorFromDate($u['birth_date'] ?? null)));

        $this->render('moderation/minor_account_create', [
            'title'      => 'Modération — Nouveau compte mineur',
            'minorUsers' => $minorUsers,
            'csrfToken'  => csrf_token(),
        ]);
    }

    /**
     * Créer un compte mineur (POST).
     * Les tutelles légales doivent être configurées séparément avant la création du compte.
     */
    public function createMinorAccount(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $data = $this->getPostData(['minor_user_id', 'name', 'currency']);

        $minorUserId = (int) ($data['minor_user_id'] ?? 0);
        $minor       = $minorUserId > 0 ? $this->userModel->find($minorUserId) : null;
        if (!$minor || !User::isMinorFromDate($minor['birth_date'] ?? null)) {
            $this->setFlash('danger', 'Utilisateur mineur introuvable ou non mineur.');
            $this->redirect('/moderation/minor-accounts/create');
            return;
        }

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $this->setFlash('danger', 'Le nom du compte est obligatoire.');
            $this->redirect('/moderation/minor-accounts/create');
            return;
        }

        $allowedCurrencies = ['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'JPY', 'XOF', 'MAD'];
        $currency = in_array($data['currency'] ?? '', $allowedCurrencies, true) ? $data['currency'] : 'EUR';

        $moderatorId = $this->getCurrentUserId();

        $minorAccountId = $this->accountModel->createAccount($minorUserId, $name, $currency, 0.0, 'minor', null);

        AuditLog::log($moderatorId, AuditLog::ACTION_MINOR_ACCOUNT_CREATE, [
            'name'  => $name,
            'minor' => $minor['username'],
        ], targetUserId: $minorUserId, targetAccountId: $minorAccountId);

        $this->setFlash('success', sprintf(
            'Compte mineur « %s » créé pour %s.',
            $name,
            $minor['username']
        ));
        $this->redirect('/moderation/guardianships');
    }

    /**
     * Liste de toutes les tutelles légales (GET).
     */
    public function guardianships(): void
    {
        $this->requireModerator();

        $allGuardianships = $this->guardianshipModel->findAll('minor_user_id', 'ASC');
        $enriched         = [];
        foreach ($allGuardianships as $g) {
            $minor    = $this->userModel->find((int) $g['minor_user_id']);
            $guardian = $this->userModel->find((int) $g['guardian_user_id']);
            $enriched[] = [
                'id'               => (int) $g['id'],
                'minor_user_id'    => (int) $g['minor_user_id'],
                'guardian_user_id' => (int) $g['guardian_user_id'],
                'minor_username'   => $minor    ? $minor['username']    : 'Utilisateur #' . $g['minor_user_id'],
                'minor_birth_date' => $minor    ? ($minor['birth_date'] ?? null) : null,
                'is_still_minor'   => $minor    ? User::isMinorFromDate($minor['birth_date'] ?? null) : false,
                'guardian_username' => $guardian ? $guardian['username'] : 'Utilisateur #' . $g['guardian_user_id'],
                'created_at'       => $g['created_at'],
            ];
        }

        $allUsers   = $this->userModel->findAll('username', 'ASC');
        $adultUsers = array_values(array_filter($allUsers, fn($u) => !User::isMinorFromDate($u['birth_date'] ?? null)));
        $minorUsers = array_values(array_filter($allUsers, fn($u) => User::isMinorFromDate($u['birth_date'] ?? null)));

        $this->render('moderation/guardianships', [
            'title'         => 'Modération — Tutelles légales',
            'guardianships' => $enriched,
            'adultUsers'    => $adultUsers,
            'minorUsers'    => $minorUsers,
            'csrfToken'     => csrf_token(),
        ]);
    }

    /**
     * Ajouter un responsable légal à un mineur (POST).
     */
    public function addGuardian(string $minorId): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $minorUserId = (int) $minorId;
        $minor       = $this->userModel->find($minorUserId);
        if (!$minor || !User::isMinorFromDate($minor['birth_date'] ?? null)) {
            $this->setFlash('danger', 'Utilisateur mineur introuvable ou non mineur.');
            $this->redirect('/moderation/guardianships');
            return;
        }

        $data           = $this->getPostData(['guardian_user_id']);
        $guardianUserId = (int) ($data['guardian_user_id'] ?? 0);
        $guardian       = $guardianUserId > 0 ? $this->userModel->find($guardianUserId) : null;

        if (!$guardian) {
            $this->setFlash('danger', 'Responsable légal introuvable.');
            $this->redirect('/moderation/guardianships');
            return;
        }
        if (User::isMinorFromDate($guardian['birth_date'] ?? null)) {
            $this->setFlash('danger', 'Le responsable légal doit être majeur.');
            $this->redirect('/moderation/guardianships');
            return;
        }
        if ($guardianUserId === $minorUserId) {
            $this->setFlash('danger', 'Le responsable légal ne peut pas être le mineur lui-même.');
            $this->redirect('/moderation/guardianships');
            return;
        }
        if ($this->guardianshipModel->countGuardiansOf($minorUserId) >= 2) {
            $this->setFlash('danger', sprintf(
                '%s a déjà 2 responsables légaux (maximum autorisé).',
                $minor['username']
            ));
            $this->redirect('/moderation/guardianships');
            return;
        }
        if ($this->guardianshipModel->isGuardianOf($guardianUserId, $minorUserId)) {
            $this->setFlash('warning', sprintf(
                '%s est déjà responsable légal de %s.',
                $guardian['username'],
                $minor['username']
            ));
            $this->redirect('/moderation/guardianships');
            return;
        }

        $this->guardianshipModel->addGuardian($minorUserId, $guardianUserId, $this->getCurrentUserId());
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_GUARDIANSHIP_ADD, ['minor' => $minor['username'], 'guardian' => $guardian['username']], targetUserId: $minorUserId);
        $this->setFlash('success', sprintf(
            '%s est désormais responsable légal de %s.',
            $guardian['username'],
            $minor['username']
        ));
        $this->redirect('/moderation/guardianships');
    }

    /**
     * Supprimer une tutelle légale (POST).
     */
    public function removeGuardian(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $guardianshipId = (int) $id;
        $guardianship   = $this->guardianshipModel->find($guardianshipId);
        if (!$guardianship) {
            $this->setFlash('danger', 'Tutelle introuvable.');
            $this->redirect('/moderation/guardianships');
            return;
        }

        $this->guardianshipModel->removeGuardianship($guardianshipId);
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_GUARDIANSHIP_REMOVE, ['minor_user_id' => (int) $guardianship['minor_user_id'], 'guardian_user_id' => (int) $guardianship['guardian_user_id']], targetUserId: (int) $guardianship['minor_user_id']);
        $this->setFlash('success', 'Tutelle légale supprimée.');
        $this->redirect('/moderation/guardianships');
    }

    // ============================================================
    // TICKETING
    // ============================================================

    /**
     * Liste de tous les tickets (modération).
     */
    public function ticketIndex(): void
    {
        $this->requireModerator();

        $statusFilter = trim($_GET['status'] ?? '');
        if ($statusFilter !== '' && !array_key_exists($statusFilter, Ticket::STATUSES)) {
            $statusFilter = '';
        }

        $tickets   = $this->ticketModel->getAll($statusFilter);
        $openCount = $this->ticketModel->countOpen();

        $this->render('moderation/tickets', [
            'title'        => 'Tickets — Modération',
            'tickets'      => $tickets,
            'statuses'     => Ticket::STATUSES,
            'statusFilter' => $statusFilter,
            'openCount'    => $openCount,
        ]);
    }

    /**
     * Détail d'un ticket + réponse modérateur.
     */
    public function ticketShow(string $id): void
    {
        $this->requireModerator();

        $ticketId = (int) $id;
        $ticket   = $this->ticketModel->findWithUser($ticketId);
        if (!$ticket) {
            $this->setFlash('danger', 'Ticket introuvable.');
            $this->redirect('/moderation/tickets');
            return;
        }

        // Passer automatiquement en "in_progress" à la première ouverture
        if ($ticket['status'] === 'open') {
            $this->ticketModel->update($ticketId, ['status' => 'in_progress']);
            $ticket['status'] = 'in_progress';
        }

        $messages = $this->ticketMessageModel->getByTicket($ticketId);
        $account  = $ticket['account_id'] ? $this->accountModel->find((int) $ticket['account_id']) : null;

        $this->render('moderation/ticket_show', [
            'title'    => '[Mod] Ticket #' . $ticketId . ' — ' . $ticket['subject'],
            'ticket'   => $ticket,
            'messages' => $messages,
            'account'  => $account,
            'statuses' => Ticket::STATUSES,
        ]);
    }

    /**
     * Réponse du modérateur à un ticket.
     */
    public function ticketReply(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $ticketId    = (int) $id;
        $moderatorId = $this->getCurrentUserId();

        $ticket = $this->ticketModel->find($ticketId);
        if (!$ticket) {
            $this->setFlash('danger', 'Ticket introuvable.');
            $this->redirect('/moderation/tickets');
            return;
        }

        $data = $this->getPostData(['body']);
        $body = trim($data['body']);
        if (mb_strlen($body) < 2) {
            $this->setFlash('danger', 'Réponse trop courte.');
            $this->redirect('/moderation/tickets/' . $ticketId);
            return;
        }

        $this->ticketMessageModel->post($ticketId, $moderatorId, $body, true);

        // Passer en "pending_user" une fois que le modérateur a répondu
        if (!Ticket::isClosed($ticket['status'])) {
            $this->ticketModel->update($ticketId, ['status' => 'pending_user']);
        }

        // Notifier le créateur du ticket (si différent du modérateur)
        if ((int) $ticket['user_id'] !== $moderatorId) {
            $this->notifModel->notify(
                (int) $ticket['user_id'],
                'ticket_replied',
                'Réponse à votre ticket #' . $ticketId,
                'La modération a répondu à votre ticket « ' . $ticket['subject'] . ' ».',
                '/tickets/' . $ticketId
            );
        }

        $this->setFlash('success', 'Réponse envoyée.');
        $this->redirect('/moderation/tickets/' . $ticketId . '#messages');
    }

    /**
     * Mise à jour du statut d'un ticket.
     */
    public function ticketUpdateStatus(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $ticketId = (int) $id;
        $ticket   = $this->ticketModel->find($ticketId);
        if (!$ticket) {
            $this->setFlash('danger', 'Ticket introuvable.');
            $this->redirect('/moderation/tickets');
            return;
        }

        $data      = $this->getPostData(['status']);
        $newStatus = $data['status'];
        if (!array_key_exists($newStatus, Ticket::STATUSES)) {
            $this->setFlash('danger', 'Statut invalide.');
            $this->redirect('/moderation/tickets/' . $ticketId);
            return;
        }

        $this->ticketModel->update($ticketId, ['status' => $newStatus]);
        $this->setFlash('success', 'Statut mis à jour : ' . Ticket::statusLabel($newStatus) . '.');
        $this->redirect('/moderation/tickets/' . $ticketId);
    }

    // ================================================================
    // Mandats professionnels
    // ================================================================

    /**
     * Liste des mandats.
     */
    public function mandates(): void
    {
        $this->requireModerator();

        $mandates = $this->mandateModel->getAllWithAccounts();

        $this->render('moderation/mandates', [
            'title'    => 'Modération — Mandats',
            'mandates' => $mandates,
        ]);
    }

    /**
     * Formulaire de création de mandat.
     */
    public function createMandateForm(): void
    {
        $this->requireModerator();

        $this->render('moderation/mandate_create', [
            'title' => 'Créer un mandat',
            'types' => Mandate::TYPES,
        ]);
    }

    /**
     * Traitement de la création de mandat (POST).
     */
    public function createMandate(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $data = $this->getPostData([
            'number', 'bank_mandate', 'emitter_account_id', 'recipient_account_id',
            'description', 'amount', 'type', 'interval_days', 'first_execution_at',
        ]);

        $number = trim($data['number'] ?? '');
        if ($number === '') {
            $this->setFlash('danger', 'Le numéro de mandat est obligatoire.');
            $this->redirect('/moderation/mandates/create');
            return;
        }

        if ($this->mandateModel->numberExists($number)) {
            $this->setFlash('danger', 'Ce numéro de mandat existe déjà.');
            $this->redirect('/moderation/mandates/create');
            return;
        }

        // Mandat émis par la banque : pas de compte émetteur, aucun crédit lors de l'exécution
        $isBankMandate = !empty($data['bank_mandate']);

        // Compte émetteur (pro à créditer) — facultatif si mandat bancaire
        $emitterAccountId = null;
        $emitterAccount   = null;
        if (!$isBankMandate) {
            $emitterAccountId = (int) ($data['emitter_account_id'] ?? 0);
            $emitterAccount   = $emitterAccountId > 0 ? $this->accountModel->find($emitterAccountId) : null;
            if (!$emitterAccount) {
                $this->setFlash('danger', 'Compte émetteur (professionnel) invalide.');
                $this->redirect('/moderation/mandates/create');
                return;
            }
            if (($emitterAccount['type'] ?? '') !== 'pro') {
                $this->setFlash('danger', 'Le compte émetteur doit être un compte professionnel.');
                $this->redirect('/moderation/mandates/create');
                return;
            }
        }

        // Compte destinataire (à débiter)
        $recipientAccountId = (int) ($data['recipient_account_id'] ?? 0);
        $recipientAccount = $recipientAccountId > 0 ? $this->accountModel->find($recipientAccountId) : null;
        if (!$recipientAccount) {
            $this->setFlash('danger', 'Compte destinataire invalide.');
            $this->redirect('/moderation/mandates/create');
            return;
        }

        if ($emitterAccountId !== null && $emitterAccountId === $recipientAccountId) {
            $this->setFlash('danger', 'Le compte émetteur et le compte destinataire doivent être différents.');
            $this->redirect('/moderation/mandates/create');
            return;
        }

        $description = trim($data['description'] ?? '');

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant doit être strictement positif.');
            $this->redirect('/moderation/mandates/create');
            return;
        }

        $type = $data['type'] ?? '';
        if (!array_key_exists($type, Mandate::TYPES)) {
            $this->setFlash('danger', 'Type de mandat invalide.');
            $this->redirect('/moderation/mandates/create');
            return;
        }

        $intervalDays = null;
        if ($type === Mandate::TYPE_RECURRING) {
            $intervalDays = (int) ($data['interval_days'] ?? 0);
            if ($intervalDays < 1) {
                $this->setFlash('danger', 'L\'intervalle de prélèvement doit être d\'au moins 1 jour.');
                $this->redirect('/moderation/mandates/create');
                return;
            }
        }

        $firstExecutionAt = null;
        $rawDate = trim($data['first_execution_at'] ?? '');
        if ($rawDate !== '') {
            $dt = parse_datetime_input($rawDate);
            if (!$dt) {
                $this->setFlash('danger', 'Date de première exécution invalide (format attendu : jj/mm/aaaa hh:mm).');
                $this->redirect('/moderation/mandates/create');
                return;
            }
            $firstExecutionAt = $dt->format('Y-m-d H:i:s');
        }

        $this->mandateModel->createMandate(
            $number,
            $emitterAccountId,
            $recipientAccountId,
            $description,
            $amount,
            $type,
            $intervalDays,
            $this->getCurrentUserId(),
            $firstExecutionAt
        );

        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_MANDATE_CREATE, ['number' => $number, 'amount' => $amount, 'type' => $type, 'bank_mandate' => $isBankMandate], targetAccountId: $recipientAccountId);
        $emitterLabel = $emitterAccount ? $emitterAccount['name'] : 'Banque';
        $this->setFlash('success', sprintf(
            'Mandat %s créé — %s € %s, émetteur « %s », destinataire « %s ».',
            $number,
            number_format($amount, 2, ',', ' '),
            Mandate::TYPES[$type],
            $emitterLabel,
            $recipientAccount['name']
        ));
        $this->redirect('/moderation/mandates');
    }

    /**
     * Reprogrammer la date de prochaine exécution d'un mandat actif (POST).
     */
    public function rescheduleMandate(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $mandateId = (int) $id;
        $mandate   = $this->mandateModel->find($mandateId);

        if (!$mandate || $mandate['status'] !== Mandate::STATUS_ACTIVE) {
            $this->setFlash('danger', 'Mandat introuvable ou non actif.');
            $this->redirect('/moderation/mandates');
            return;
        }

        $data    = $this->getPostData(['next_execution_at']);
        $rawDate = trim($data['next_execution_at'] ?? '');
        $dt      = parse_datetime_input($rawDate);
        if (!$dt) {
            $this->setFlash('danger', 'Date invalide (format attendu : jj/mm/aaaa hh:mm).');
            $this->redirect('/moderation/mandates');
            return;
        }

        $nextExecutionAt = $dt->format('Y-m-d H:i:s');
        $this->mandateModel->updateNextExecution($mandateId, $nextExecutionAt);

        AuditLog::log(
            $this->getCurrentUserId(),
            AuditLog::ACTION_MANDATE_RESCHEDULE,
            ['number' => $mandate['number'], 'new_date' => $nextExecutionAt],
            targetAccountId: (int) $mandate['recipient_account_id']
        );
        $this->setFlash('success', sprintf(
            'Mandat %s reprogrammé au %s.',
            $mandate['number'],
            $dt->format('d/m/Y à H\\hi')
        ));
        $this->redirect('/moderation/mandates');
    }

    /**
     * Reprogrammer la date d’exécution d’un prélèvement planifié (POST).
     */
    public function rescheduleDirectDebit(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $debitId     = (int) $id;
        $directDebit = $this->directDebitModel->find($debitId);

        if (!$directDebit || $directDebit['status'] !== DirectDebit::STATUS_SCHEDULED) {
            $this->setFlash('danger', 'Prélèvement introuvable ou non planifié.');
            $this->redirect('/moderation/direct-debits');
            return;
        }

        $data        = $this->getPostData(['scheduled_at']);
        $rawDate     = trim($data['scheduled_at'] ?? '');
        $dt          = parse_datetime_input($rawDate);
        if (!$dt) {
            $this->setFlash('danger', 'Date invalide (format attendu : jj/mm/aaaa hh:mm).');
            $this->redirect('/moderation/direct-debits');
            return;
        }

        $scheduledAt = $dt->format('Y-m-d H:i:s');
        $this->directDebitModel->reschedule($debitId, $scheduledAt);

        AuditLog::log(
            $this->getCurrentUserId(),
            AuditLog::ACTION_DIRECT_DEBIT_RESCHEDULE,
            ['id' => $debitId, 'mandate' => $directDebit['mandate_number'], 'new_date' => $scheduledAt],
            targetAccountId: (int) $directDebit['to_account_id']
        );
        $this->setFlash('success', sprintf(
            'Prélèvement #%d (mandat %s) reprogrammé au %s.',
            $debitId,
            $directDebit['mandate_number'],
            $dt->format('d/m/Y à H\\hi')
        ));
        $this->redirect('/moderation/direct-debits');
    }

    /**
     * Révoquer un mandat (POST).
     */
    public function revokeMandate(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $mandateId = (int) $id;
        $mandate   = $this->mandateModel->find($mandateId);

        if (!$mandate) {
            $this->setFlash('danger', 'Mandat introuvable.');
            $this->redirect('/moderation/mandates');
            return;
        }

        if ($mandate['status'] === Mandate::STATUS_REVOKED) {
            $this->setFlash('warning', 'Ce mandat est déjà révoqué.');
            $this->redirect('/moderation/mandates');
            return;
        }

        $this->mandateModel->revoke($mandateId);
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_MANDATE_REVOKE, ['number' => $mandate['number'], 'amount' => $mandate['amount']], targetAccountId: (int) $mandate['emitter_account_id']);
        // Notifier les propriétaires des comptes concernés (et leurs tuteurs si mineurs)
        $emitterAccount   = $this->accountModel->find((int) $mandate['emitter_account_id']);
        $recipientAccount = $this->accountModel->find((int) $mandate['recipient_account_id']);
        if ($emitterAccount) {
            $this->notifyAccountOwner(
                (int) $emitterAccount['user_id'],
                (int) $emitterAccount['id'],
                'mandate_revoked',
                'Mandat ' . $mandate['number'] . ' révoqué',
                'Le mandat ' . $mandate['number'] . ' associé à votre compte « ' . $emitterAccount['name'] . ' » a été révoqué par la modération.'
            );
        }
        if ($recipientAccount && (int) $recipientAccount['user_id'] !== (int) ($emitterAccount['user_id'] ?? -1)) {
            $this->notifyAccountOwner(
                (int) $recipientAccount['user_id'],
                (int) $recipientAccount['id'],
                'mandate_revoked',
                'Mandat ' . $mandate['number'] . ' révoqué',
                'Le mandat ' . $mandate['number'] . ' associé à votre compte « ' . $recipientAccount['name'] . ' » a été révoqué par la modération.'
            );
        }
        $this->setFlash('success', 'Mandat ' . $mandate['number'] . ' révoqué.');
        $this->redirect('/moderation/mandates');
    }

    // =========================================================
    // TAUX D'INTÉRÊT ÉPARGNE
    // =========================================================

    /**
     * Affiche les taux d'intérêt actuels par type de compte et l'historique (GET).
     */
    public function savingsRate(): void
    {
        $this->requireModerator();

        // Filtre optionnel par type pour l'historique
        $filterType   = isset($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : null;
        $allRates     = $this->rateModel->getAllCurrentRates();
        $history      = $this->rateModel->getHistory(30, $filterType);
        $eligibleTypes = Account::getInterestEligibleTypes();

        // Filtre utilisateurs pour l'aperçu : IDs passés en GET (ex: ?user_ids[]=3&user_ids[]=7)
        $filterUserIds = [];
        if (!empty($_GET['user_ids']) && is_array($_GET['user_ids'])) {
            foreach ($_GET['user_ids'] as $uid) {
                $uid = (int) $uid;
                if ($uid > 0) {
                    $filterUserIds[] = $uid;
                }
            }
            $filterUserIds = array_unique($filterUserIds);
        }

        // Résoudre les account_ids autorisés pour les utilisateurs filtrés
        // (comptes propres, partagés et comptes de mineurs sous tutelle)
        $allowedAccountIds = null; // null = tous les comptes
        $filterUserLabels  = [];
        if (!empty($filterUserIds)) {
            $allowedAccountIds = [];
            foreach ($filterUserIds as $uid) {
                $user = $this->userModel->find($uid);
                if (!$user) {
                    continue;
                }
                $filterUserLabels[$uid] = $user['username'];
                $accessible = $this->accountModel->getAccessibleAccounts($uid);
                foreach (array_merge($accessible['own'], $accessible['shared']) as $acc) {
                    $allowedAccountIds[] = (int) $acc['id'];
                }
            }
            $allowedAccountIds = array_unique($allowedAccountIds);
        }

        // Aperçu indicatif des intérêts en cours (?preview=1) — lecture seule, aucune écriture BDD.
        $previewResults = null;
        if (isset($_GET['preview'])) {
            $previewResults = [];
            // Pré-calcul des segments de taux par type (une seule requête par type)
            $typeSegments = [];
            foreach ($eligibleTypes as $t) {
                $typeSegments[$t] = $this->rateModel->getRateSegmentsForYear($t, (int) date('Y'));
            }
            foreach ($eligibleTypes as $accountType) {
                foreach ($this->accountModel->findBy(['type' => $accountType]) as $account) {
                    $accountId = (int) $account['id'];

                    // Appliquer le filtre utilisateurs si actif
                    if ($allowedAccountIds !== null && !in_array($accountId, $allowedAccountIds, true)) {
                        continue;
                    }

                    $accountRate = isset($account['interest_rate']) && $account['interest_rate'] !== null
                        ? (float) $account['interest_rate']
                        : 0.0;
                    if ($accountRate <= 0) {
                        continue;
                    }
                    $accrued = SavingsInterest::calculateAccrued($accountId, $accountRate, $this->transactionModel, $typeSegments[$accountType] ?? []);
                    $owner   = $this->userModel->find((int) $account['user_id']);
                    $previewResults[] = [
                        'account_id'   => $accountId,
                        'account_name' => $account['name'],
                        'account_type' => $accountType,
                        'username'     => $owner ? $owner['username'] : '—',
                        'rate'         => $accountRate,
                        'accrued'      => $accrued,
                        'currency'     => $account['currency'],
                    ];
                }
            }
        }

        $this->render('moderation/savings_rate', [
            'title'            => 'Modération — Taux d\'intérêt épargne',
            'allRates'         => $allRates,
            'history'          => $history,
            'eligibleTypes'    => $eligibleTypes,
            'filterType'       => $filterType,
            'filterUserIds'    => $filterUserIds,
            'filterUserLabels' => $filterUserLabels,
            'previewResults'   => $previewResults,
        ]);
    }

    /**
     * Met à jour le taux d'intérêt pour un type de compte donné (POST).
     */
    public function setSavingsRate(): void
    {
        $this->requireModerator();
        $this->validateCSRF();
        $data    = $this->getPostData(['rate', 'account_type', 'effective_date']);
        $rateRaw = (float) str_replace(',', '.', $data['rate'] ?? '');
        $rate    = round($rateRaw / 100, 6); // formulaire en %, on stocke en décimal

        $accountType = $data['account_type'] ?? '';
        if (!Account::typeHasInterest($accountType)) {
            $this->setFlash('danger', 'Type de compte invalide ou non éligible aux intérêts.');
            $this->redirect('/moderation/savings-rate');
            return;
        }
        if ($rate < 0 || $rate > 1) {
            $this->setFlash('danger', 'Le taux doit être compris entre 0 et 100 %.');
            $this->redirect('/moderation/savings-rate');
            return;
        }

        // Date d'effet : si fournie, doit être dans l'année courante
        $effectiveAt = null;
        $rawDate     = trim($data['effective_date'] ?? '');
        if ($rawDate !== '') {
            $currentYear = (int) date('Y');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)
                || (int) substr($rawDate, 0, 4) !== $currentYear
            ) {
                $this->setFlash('danger', sprintf(
                    'La date d\'effet doit être dans l\'année en cours (%d).', $currentYear
                ));
                $this->redirect('/moderation/savings-rate');
                return;
            }
            $effectiveAt = $rawDate . ' 00:00:00';
        }

        $userId = $this->getCurrentUserId();
        $this->rateModel->setRate($rate, $accountType, $userId, $effectiveAt);
        AuditLog::log($userId, AuditLog::ACTION_INTEREST_RATE_SET, [
            'account_type' => $accountType,
            'rate'         => $rate,
            'effective_at' => $effectiveAt ?? date('Y-m-d H:i:s'),
        ]);
        $label      = Account::TYPES[$accountType]['label'] ?? $accountType;
        $dateLabel  = $effectiveAt
            ? ' (effectif le ' . date('d/m/Y', strtotime($effectiveAt)) . ')'
            : '';
        $this->setFlash('success', sprintf(
            'Taux d\'intérêt « %s » enregistré : %s %%%s.',
            $label,
            number_format($rateRaw, 2, ',', ' '),
            $dateLabel
        ));
        $this->redirect('/moderation/savings-rate');
    }

    // =========================================================
    // HELPERS PRIVÉS
    // =========================================================

    /**
     * Notifie le propriétaire d'un compte ET ses éventuels responsables légaux (tuteurs).
     * Garantit que les comptes mineurs transmettent bien l'alerte au responsable légal.
     */
    private function notifyAccountOwner(
        int $userId,
        int $accountId,
        string $type,
        string $title,
        string $body
    ): void {
        $link = '/accounts/' . $accountId;
        $this->notifModel->notify($userId, $type, $title, $body, $link);
        foreach ($this->guardianshipModel->getGuardiansOf($userId) as $g) {
            $this->notifModel->notify((int) $g['guardian_user_id'], $type, $title, $body, $link);
        }
    }

    // =========================================================
    // CRÉATION DE COMPTE POUR UN UTILISATEUR DÉFINI
    // =========================================================

    /**
     * Formulaire de création d'un compte pour un utilisateur défini (GET).
     * Le modérateur peut choisir n'importe quel type, sans les restrictions utilisateur.
     */
    public function createAccountForm(): void
    {
        $this->requireModerator();

        $maxRates = [];
        foreach (Account::getInterestEligibleTypes() as $iType) {
            $maxRates[$iType] = $this->rateModel->getCurrentRate($iType);
        }

        $this->render('moderation/account_create', [
            'title'        => 'Modération — Créer un compte',
            'accountTypes' => Account::TYPES,
            'maxRates'     => $maxRates,
            'csrfToken'    => csrf_token(),
        ]);
    }

    /**
     * Création du compte pour l'utilisateur sélectionné (POST).
     */
    public function createAccountForUser(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $data = $this->getPostData(['target_user_id', 'name', 'currency', 'account_type', 'overdraft', 'cap', 'interest_rate']);

        $targetUserId = (int) ($data['target_user_id'] ?? 0);
        $targetUser   = $targetUserId > 0 ? $this->userModel->find($targetUserId) : null;

        if (!$targetUser) {
            $this->setFlash('danger', 'Utilisateur introuvable. Veuillez sélectionner un utilisateur valide.');
            $this->redirect('/moderation/accounts/create');
            return;
        }

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $this->setFlash('danger', 'Le nom du compte est obligatoire.');
            $this->redirect('/moderation/accounts/create');
            return;
        }

        $allowedCurrencies = ['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'JPY', 'XOF', 'MAD'];
        $currency = in_array($data['currency'] ?? '', $allowedCurrencies, true) ? $data['currency'] : 'EUR';

        $type = $data['account_type'] ?? 'standard';
        if (!array_key_exists($type, Account::TYPES)) {
            $this->setFlash('danger', 'Type de compte invalide.');
            $this->redirect('/moderation/accounts/create');
            return;
        }

        $overdraft = Account::typeAllowsOverdraft($type) ? abs((float) ($data['overdraft'] ?: 0)) : 0.0;
        $cap       = Account::typeHasCap($type) && ($data['cap'] ?? '') !== '' ? abs((float) $data['cap']) : null;

        $interestRate = null;
        if (Account::typeHasInterest($type) && ($data['interest_rate'] ?? '') !== '') {
            $rawPct       = (float) str_replace(',', '.', $data['interest_rate']);
            $interestRate = round($rawPct / 100, 6);
            $maxRate      = $this->rateModel->getCurrentRate($type);
            if ($maxRate !== null && $interestRate > $maxRate) {
                $interestRate = $maxRate;
            }
            if ($interestRate < 0) {
                $interestRate = 0.0;
            }
        }

        $moderatorId = $this->getCurrentUserId();
        $accountId   = $this->accountModel->createAccount($targetUserId, $name, $currency, $overdraft, $type, $cap);

        if ($interestRate !== null) {
            $this->accountModel->update($accountId, ['interest_rate' => $interestRate]);
        }

        AuditLog::log($moderatorId, AuditLog::ACTION_ACCOUNT_CREATE, [
            'name' => $name,
            'type' => $type,
            'for'  => $targetUser['username'],
        ], targetUserId: $targetUserId, targetAccountId: $accountId);

        $this->setFlash('success', sprintf(
            'Compte « %s » (%s) créé pour %s avec succès.',
            $name,
            Account::TYPES[$type]['label'],
            $targetUser['username']
        ));
        $this->redirect('/moderation');
    }

    // =========================================================
    // CRÉATION DE COMPTES INTERNES (TEST DE MODÉRATION)
    // =========================================================

    /**
     * Formulaire de création d'un compte interne (GET).
     * Les comptes internes appartiennent au modérateur connecté, ne peuvent pas
     * être partagés à des utilisateurs normaux, et autorisent toutes les opérations
     * bancaires sans restriction de profil. Ils sont destinés aux tests.
     */
    public function createInternalAccountForm(): void
    {
        $this->requireModerator();

        $maxRates = [];
        foreach (Account::getInterestEligibleTypes() as $iType) {
            $maxRates[$iType] = $this->rateModel->getCurrentRate($iType);
        }

        $this->render('moderation/internal_account_create', [
            'title'        => 'Modération — Créer un compte interne',
            'accountTypes' => Account::TYPES,
            'maxRates'     => $maxRates,
            'csrfToken'    => csrf_token(),
        ]);
    }

    /**
     * Création du compte interne (POST). Le propriétaire est le modérateur connecté.
     */
    public function createInternalAccount(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $data = $this->getPostData(['name', 'currency', 'account_type', 'overdraft', 'cap', 'interest_rate']);

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $this->setFlash('danger', 'Le nom du compte est obligatoire.');
            $this->redirect('/moderation/internal-accounts/create');
            return;
        }

        $allowedCurrencies = ['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'JPY', 'XOF', 'MAD'];
        $currency = in_array($data['currency'] ?? '', $allowedCurrencies, true) ? $data['currency'] : 'EUR';

        $type = $data['account_type'] ?? 'standard';
        if (!array_key_exists($type, Account::TYPES)) {
            $this->setFlash('danger', 'Type de compte invalide.');
            $this->redirect('/moderation/internal-accounts/create');
            return;
        }

        $overdraft = Account::typeAllowsOverdraft($type) ? abs((float) ($data['overdraft'] ?: 0)) : 0.0;
        $cap       = Account::typeHasCap($type) && ($data['cap'] ?? '') !== '' ? abs((float) $data['cap']) : null;

        $interestRate = null;
        if (Account::typeHasInterest($type) && ($data['interest_rate'] ?? '') !== '') {
            $rawPct       = (float) str_replace(',', '.', $data['interest_rate']);
            $interestRate = round($rawPct / 100, 6);
            $maxRate      = $this->rateModel->getCurrentRate($type);
            if ($maxRate !== null && $interestRate > $maxRate) {
                $interestRate = $maxRate;
            }
            if ($interestRate < 0) {
                $interestRate = 0.0;
            }
        }

        $moderatorId = $this->getCurrentUserId();
        $accountId   = $this->accountModel->createAccount(
            $moderatorId,
            $name,
            $currency,
            $overdraft,
            $type,
            $cap,
            true // internal = true
        );

        if ($interestRate !== null) {
            $this->accountModel->update($accountId, ['interest_rate' => $interestRate]);
        }

        AuditLog::log($moderatorId, AuditLog::ACTION_ACCOUNT_CREATE, [
            'name'     => $name,
            'type'     => $type,
            'internal' => true,
        ], targetUserId: $moderatorId, targetAccountId: $accountId);

        $this->setFlash('success', sprintf(
            'Compte interne « %s » (%s) créé avec succès.',
            $name,
            Account::TYPES[$type]['label']
        ));
        $this->redirect('/moderation');
    }

    // ── VIREMENTS RÉCURRENTS ─────────────────────────────────────────────────

    /**
     * Liste tous les virements récurrents (modération).
     */
    public function recurringTransfers(): void
    {
        $this->requireModerator();

        $accountsMap = array_column($this->accountModel->findAll('id', 'ASC'), null, 'id');
        $usersMap    = array_column($this->userModel->findAll('username', 'ASC'), null, 'id');

        $rawList  = $this->recurringTransferModel->findAll('created_at', 'DESC');
        $enriched = [];
        foreach ($rawList as $rt) {
            $uid     = (int) ($rt['user_id'] ?? 0);
            $fromAcc = $accountsMap[$rt['from_account_id']] ?? null;
            $toAcc   = $accountsMap[$rt['to_account_id']]   ?? null;
            $user    = $usersMap[$uid] ?? null;
            $enriched[] = [
                'id'                => (int)   $rt['id'],
                'user_name'         => $user    ? ($user['username']    ?? 'Utilisateur #' . $uid)              : 'Utilisateur #' . $uid,
                'from_account'      => $fromAcc ? ($fromAcc['name']     ?? 'Compte #' . $rt['from_account_id']) : 'Compte #' . $rt['from_account_id'],
                'to_account'        => $toAcc   ? ($toAcc['name']       ?? 'Compte #' . $rt['to_account_id'])   : 'Compte #' . $rt['to_account_id'],
                'amount'            => (float)  $rt['amount'],
                'motif'             => $rt['motif']             ?? '',
                'status'            => $rt['status']            ?? RecurringTransfer::STATUS_ACTIVE,
                'interval_days'     => (int)    ($rt['interval_days']     ?? 0),
                'next_execution_at' => $rt['next_execution_at']  ?? null,
                'last_executed_at'  => $rt['last_executed_at']   ?? null,
                'created_at'        => $rt['created_at']         ?? null,
            ];
        }

        $rtJson    = json_encode(
            $enriched,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );
        $rtAuthors = [];
        foreach ($enriched as $rt) {
            if (!empty($rt['user_name']) && !in_array($rt['user_name'], $rtAuthors, true)) {
                $rtAuthors[] = $rt['user_name'];
            }
        }
        sort($rtAuthors);

        $this->render('moderation/recurring_transfers', [
            'title'      => 'Modération — Virements récurrents',
            'rtJson'     => $rtJson,
            'rtAuthors'  => $rtAuthors,
            'totalCount' => count($enriched),
            'csrfToken'  => csrf_token(),
        ]);
    }

    /**
     * Reprogrammer la prochaine exécution d'un virement récurrent (POST).
     */
    public function rescheduleRecurringTransfer(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $rtId = (int) $id;
        $rt   = $this->recurringTransferModel->find($rtId);

        if (!$rt || $rt['status'] !== RecurringTransfer::STATUS_ACTIVE) {
            $this->setFlash('danger', 'Virement récurrent introuvable ou non actif.');
            $this->redirect('/moderation/recurring-transfers');
            return;
        }

        $data    = $this->getPostData(['next_execution_at']);
        $rawDate = trim($data['next_execution_at'] ?? '');
        $dt      = parse_datetime_input($rawDate);
        if (!$dt) {
            $this->setFlash('danger', 'Date invalide (format attendu : jj/mm/aaaa hh:mm).');
            $this->redirect('/moderation/recurring-transfers');
            return;
        }

        $nextExecutionAt = $dt->format('Y-m-d H:i:s');
        $this->recurringTransferModel->updateNextExecution($rtId, $nextExecutionAt);

        AuditLog::log(
            $this->getCurrentUserId(),
            AuditLog::ACTION_RECURRING_TRANSFER_RESCHEDULE,
            ['id' => $rtId, 'new_date' => $nextExecutionAt],
            targetAccountId: (int) $rt['from_account_id']
        );
        $this->setFlash('success', sprintf(
            'Virement récurrent #%d reprogrammé au %s.',
            $rtId,
            $dt->format('d/m/Y à H\\hi')
        ));
        $this->redirect('/moderation/recurring-transfers');
    }
}

