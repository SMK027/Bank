<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AccountAccess;
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

    public function __construct()
    {
        $this->accountModel     = new Account();
        $this->accessModel      = new AccountAccess();
        $this->userModel        = new User();
        $this->transferModel    = new Transfer();
        $this->transactionModel = new Transaction();
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
        $this->transactionModel->addTransaction(
            (int) $transfer['from_account_id'],
            'income',
            $amount,
            'Virement',
            $motif,
            $moderatorId
        );

        // Récupération sur le compte destinataire (expense)
        $this->transactionModel->addTransaction(
            (int) $transfer['to_account_id'],
            'expense',
            $amount,
            'Virement',
            $motif,
            $moderatorId
        );

        $this->transferModel->markCancelled($transferId);

        $this->setFlash('success', 'Virement #' . $transferId . ' annulé avec succès. Les soldes ont été rétablis.');
        $this->redirect('/moderation/transfers');
    }
}
