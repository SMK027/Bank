<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AccountAccess;
use App\Models\User;

class ModerationController extends Controller
{
    private Account $accountModel;
    private AccountAccess $accessModel;
    private User $userModel;

    public function __construct()
    {
        $this->accountModel = new Account();
        $this->accessModel  = new AccountAccess();
        $this->userModel    = new User();
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
}
