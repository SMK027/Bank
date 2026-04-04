<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AccountAccess;
use App\Models\User;

class AccessController extends Controller
{
    private Account $accountModel;
    private AccountAccess $accessModel;
    private User $userModel;

    public function __construct()
    {
        $this->accountModel = new Account();
        $this->accessModel = new AccountAccess();
        $this->userModel = new User();
    }

    public function grant(string $accountId): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $accId = (int) $accountId;
        $userId = $this->getCurrentUserId();
        $isModerator = $this->isModerator();

        if (!$isModerator && !$this->accountModel->isOwner($accId, $userId)) {
            $this->setFlash('danger', 'Seul le propriétaire peut partager ce compte.');
            $this->redirect('/dashboard');
            return;
        }

        $data = $this->getPostData(['email', 'access_type', 'expires_at']);

        if (empty($data['email'])) {
            $this->setFlash('danger', 'L\'email de l\'utilisateur est requis.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $targetUser = $this->userModel->findByEmail($data['email']);
        if (!$targetUser) {
            $this->setFlash('danger', 'Aucun utilisateur trouvé avec cet email.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $account = $this->accountModel->find($accId);
        if ($account && (int) $targetUser['id'] === (int) $account['user_id']) {
            $this->setFlash('danger', 'Cet utilisateur est déjà propriétaire de ce compte.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        if (!$isModerator && (int) $targetUser['id'] === $userId) {
            $this->setFlash('danger', 'Vous ne pouvez pas partager un compte avec vous-même.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $type = in_array($data['access_type'], ['permanent', 'temporary'], true) ? $data['access_type'] : 'permanent';
        $expiresAt = null;

        if ($type === 'temporary') {
            if (empty($data['expires_at'])) {
                $this->setFlash('danger', 'La date d\'expiration est requise pour un accès temporaire.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            $expiresAt = $data['expires_at'];
            if (strtotime($expiresAt) <= time()) {
                $this->setFlash('danger', 'La date d\'expiration doit être dans le futur.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
        }

        $this->accessModel->grantAccess($accId, (int) $targetUser['id'], $type, $expiresAt);
        $this->setFlash('success', 'Accès accordé à ' . $targetUser['username'] . '.');
        $this->redirect('/accounts/' . $accountId);
    }

    public function revoke(string $accountId, string $userId): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $accId = (int) $accountId;
        $currentUserId = $this->getCurrentUserId();

        if (!$this->isModerator() && !$this->accountModel->isOwner($accId, $currentUserId)) {
            $this->setFlash('danger', 'Seul le propriétaire peut révoquer un accès.');
            $this->redirect('/dashboard');
            return;
        }

        $this->accessModel->revokeAccess($accId, (int) $userId);
        $this->setFlash('success', 'Accès révoqué.');
        $this->redirect('/accounts/' . $accountId);
    }
}
