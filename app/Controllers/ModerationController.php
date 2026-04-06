<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AccountAccess;
use App\Models\DirectDebit;
use App\Models\Guardianship;
use App\Models\Mandate;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;

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
        // Notifier le propriétaire du compte
        $this->notifModel->notify(
            (int) $account['user_id'],
            'account_frozen',
            'Compte « ' . $account['name'] . ' » gelé',
            'Votre compte a été gelé par la modération. Les opérations sortantes sont bloquées.',
            '/accounts/' . $accountId
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
        // Notifier le propriétaire du compte
        $this->notifModel->notify(
            (int) $account['user_id'],
            'account_unfrozen',
            'Compte « ' . $account['name'] . ' » dégelé',
            'Les restrictions sur votre compte ont été levées. Vous pouvez effectuer à nouveau des opérations sortantes.',
            '/accounts/' . $accountId
        );
        $this->setFlash('success', 'Compte « ' . $account['name'] . ' » dégelé avec succès.');
        $this->redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/moderation');
    }

    /**
     * Liste des utilisateurs avec gestion des rôles.
     */
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

        // Notifier les propriétaires des comptes concernés
        $fromAccount = $this->accountModel->find((int) $transfer['from_account_id']);
        $toAccount   = $this->accountModel->find((int) $transfer['to_account_id']);
        if ($fromAccount) {
            $this->notifModel->notify(
                (int) $fromAccount['user_id'],
                'transfer_cancelled',
                'Virement #' . $transferId . ' annulé',
                'Le virement de ' . number_format($amount, 2, ',', ' ') . ' € depuis votre compte « ' . $fromAccount['name'] . ' » a été annulé par la modération. Le montant a été recrédité.',
                '/accounts/' . (int) $fromAccount['id']
            );
        }
        if ($toAccount && $toAccount['user_id'] !== ($fromAccount['user_id'] ?? null)) {
            $this->notifModel->notify(
                (int) $toAccount['user_id'],
                'transfer_cancelled',
                'Virement #' . $transferId . ' annulé',
                'Un virement de ' . number_format($amount, 2, ',', ' ') . ' € vers votre compte « ' . $toAccount['name'] . ' » a été annulé par la modération.',
                '/accounts/' . (int) $toAccount['id']
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
        $ts = strtotime($data['scheduled_at'] ?? '');
        if ($ts === false || $ts <= 0) {
            $this->setFlash('danger', 'La date d\'exécution est invalide.');
            $this->redirect('/moderation/direct-debits/create');
            return;
        }
        $scheduledAt = date('Y-m-d H:i:s', $ts);

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

        $this->setFlash('success', sprintf(
            'Prélèvement de %s € planifié pour le %s sur « %s » (mandat %s).',
            number_format($amount, 2, ',', ' '),
            date('d/m/Y à H\hi', $ts),
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

        $amount        = (float) $directDebit['amount'];
        $toAccountId   = (int) $directDebit['to_account_id'];
        $fromAccountId = $directDebit['from_account_id'] !== null ? (int) $directDebit['from_account_id'] : null;
        $comment       = 'Rejet prélèvement mandat ' . $directDebit['mandate_number'];
        $moderatorId   = $this->getCurrentUserId();

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

        $this->directDebitModel->markRejected($debitId);

        // Notifier le propriétaire du compte débité
        $toAccount = $this->accountModel->find($toAccountId);
        if ($toAccount) {
            $this->notifModel->notify(
                (int) $toAccount['user_id'],
                'direct_debit_rejected',
                'Prélèvement rejeté — ' . number_format($amount, 2, ',', ' ') . ' €',
                'Le prélèvement (mandat ' . $directDebit['mandate_number'] . ') de ' . number_format($amount, 2, ',', ' ') . ' € sur votre compte « ' . $toAccount['name'] . ' » a été rejeté par la modération. Le montant a été recrédité.',
                '/accounts/' . $toAccountId
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
            $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $rawRetryDate)
               ?: \DateTime::createFromFormat('Y-m-d H:i:s', $rawRetryDate)
               ?: \DateTime::createFromFormat('Y-m-d H:i', $rawRetryDate);
            if (!$dt) {
                $this->setFlash('danger', 'Date de planification invalide.');
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
        $adultUsers = array_values(array_filter($allUsers, fn($u) => !User::isMinorFromDate($u['birth_date'] ?? null)));

        $this->render('moderation/minor_account_create', [
            'title'      => 'Modération — Nouveau compte mineur',
            'minorUsers' => $minorUsers,
            'adultUsers' => $adultUsers,
            'csrfToken'  => csrf_token(),
        ]);
    }

    /**
     * Créer un compte mineur avec ses tuteurs légaux (POST).
     */
    public function createMinorAccount(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $data = $this->getPostData(['minor_user_id', 'name', 'currency', 'guardian_1_id', 'guardian_2_id']);

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

        // Premier tuteur (obligatoire)
        $guardian1Id = (int) ($data['guardian_1_id'] ?? 0);
        $guardian1   = $guardian1Id > 0 ? $this->userModel->find($guardian1Id) : null;
        if (!$guardian1 || User::isMinorFromDate($guardian1['birth_date'] ?? null)) {
            $this->setFlash('danger', 'Le premier responsable légal doit être un utilisateur adulte.');
            $this->redirect('/moderation/minor-accounts/create');
            return;
        }
        if ($guardian1Id === $minorUserId) {
            $this->setFlash('danger', 'Le responsable légal ne peut pas être le mineur lui-même.');
            $this->redirect('/moderation/minor-accounts/create');
            return;
        }

        // Second tuteur (facultatif)
        $guardian2Raw = trim($data['guardian_2_id'] ?? '');
        $guardian2Id  = ($guardian2Raw !== '' && $guardian2Raw !== '0') ? (int) $guardian2Raw : 0;
        $guardian2    = null;
        if ($guardian2Id > 0) {
            $guardian2 = $this->userModel->find($guardian2Id);
            if (!$guardian2 || User::isMinorFromDate($guardian2['birth_date'] ?? null)) {
                $this->setFlash('danger', 'Le second responsable légal doit être un utilisateur adulte.');
                $this->redirect('/moderation/minor-accounts/create');
                return;
            }
            if ($guardian2Id === $guardian1Id) {
                $this->setFlash('danger', 'Les deux responsables légaux doivent être des personnes différentes.');
                $this->redirect('/moderation/minor-accounts/create');
                return;
            }
            if ($guardian2Id === $minorUserId) {
                $this->setFlash('danger', 'Le responsable légal ne peut pas être le mineur lui-même.');
                $this->redirect('/moderation/minor-accounts/create');
                return;
            }
        }

        $moderatorId = $this->getCurrentUserId();

        // Créer le compte mineur
        $this->accountModel->createAccount($minorUserId, $name, $currency, 0.0, 'minor', null);

        // Créer les tutelles
        $this->guardianshipModel->addGuardian($minorUserId, $guardian1Id, $moderatorId);
        if ($guardian2Id > 0) {
            $this->guardianshipModel->addGuardian($minorUserId, $guardian2Id, $moderatorId);
        }

        $this->setFlash('success', sprintf(
            'Compte mineur « %s » créé pour %s. Responsable(s) légal(aux) : %s%s.',
            $name,
            $minor['username'],
            $guardian1['username'],
            $guardian2 ? ', ' . $guardian2['username'] : ''
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
            'number', 'emitter_account_id', 'recipient_account_id',
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

        // Compte émetteur (pro à créditer)
        $emitterAccountId = (int) ($data['emitter_account_id'] ?? 0);
        $emitterAccount = $emitterAccountId > 0 ? $this->accountModel->find($emitterAccountId) : null;
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

        // Compte destinataire (à débiter)
        $recipientAccountId = (int) ($data['recipient_account_id'] ?? 0);
        $recipientAccount = $recipientAccountId > 0 ? $this->accountModel->find($recipientAccountId) : null;
        if (!$recipientAccount) {
            $this->setFlash('danger', 'Compte destinataire invalide.');
            $this->redirect('/moderation/mandates/create');
            return;
        }

        if ($emitterAccountId === $recipientAccountId) {
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
            $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $rawDate)
               ?: \DateTime::createFromFormat('Y-m-d H:i:s', $rawDate)
               ?: \DateTime::createFromFormat('Y-m-d H:i', $rawDate);
            if (!$dt) {
                $this->setFlash('danger', 'Date de première exécution invalide.');
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

        $this->setFlash('success', sprintf(
            'Mandat %s créé — %s € %s, émetteur « %s », destinataire « %s ».',
            $number,
            number_format($amount, 2, ',', ' '),
            Mandate::TYPES[$type],
            $emitterAccount['name'],
            $recipientAccount['name']
        ));
        $this->redirect('/moderation/mandates');
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
        $this->setFlash('success', 'Mandat ' . $mandate['number'] . ' révoqué.');
        $this->redirect('/moderation/mandates');
    }
}
