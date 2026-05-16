<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AccountAccess;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;

class AccessController extends Controller
{
    private Account $accountModel;
    private AccountAccess $accessModel;
    private Notification $notifModel;
    private User $userModel;

    public function __construct()
    {
        $this->accountModel = new Account();
        $this->accessModel  = new AccountAccess();
        $this->notifModel   = new Notification();
        $this->userModel    = new User();
    }

    public function grant(string $accountId): void
    {
        $this->requireAuth();
        $this->requireFeature('accounts.share');
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

        // Les comptes internes de modération ne peuvent pas être partagés.
        if ($account && Account::isInternal($account)) {
            $this->setFlash('danger', 'Les comptes internes de modération ne peuvent pas être partagés.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Partage interdit sur les comptes mineurs sauf par un modérateur
        if (!$isModerator && $account) {
            $accountOwner = $this->userModel->find((int) $account['user_id']);
            if (User::isMinorFromDate($accountOwner['birth_date'] ?? null)) {
                $this->setFlash('danger', 'Le partage d\'accès est interdit sur un compte mineur. Seul un modérateur peut accorder des accès.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
        }

        // Un mineur ne peut pas être ajouté à un compte réservé aux majeurs (sauf modérateur)
        if (!$isModerator && $account && User::isMinorFromDate($targetUser['birth_date'] ?? null)) {
            if (Account::isAdultOnlyAccount($account)) {
                $typeLabel = Account::TYPES[$account['type']]['label'] ?? $account['type'];
                $this->setFlash('danger',
                    'L\'ajout d\'un mineur est interdit sur ce type de compte (' . $typeLabel . '). '
                    . 'Contactez un modérateur si une dérogation est nécessaire.'
                );
                $this->redirect('/accounts/' . $accountId);
                return;
            }
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
        // Notifier l'utilisateur
        $this->notifModel->notify(
            (int) $targetUser['id'],
            'access_granted',
            'Accès accordé au compte « ' . ($account['name'] ?? '?') . ' »',
            'Vous avez reçu un accès ' . ($type === 'temporary' ? 'temporaire' : 'permanent') . ' au compte bancaire « ' . ($account['name'] ?? '?') . ' ».',
            '/accounts/' . $accId
        );
        $this->setFlash('success', 'Accès accordé à ' . $targetUser['username'] . '.');
        AuditLog::log($userId, AuditLog::ACTION_ACCESS_GRANT, ['shared_with' => $targetUser['username'], 'name' => $account['name'] ?? '?', 'type' => $type], targetUserId: (int) $targetUser['id'], targetAccountId: $accId);
        $this->redirect('/accounts/' . $accountId);
    }

    public function revoke(string $accountId, string $userId): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $accId = (int) $accountId;
        $currentUserId = $this->getCurrentUserId();

        $isModerator = $this->isModerator();

        if (!$isModerator && !$this->accountModel->isOwner($accId, $currentUserId)) {
            $this->setFlash('danger', 'Seul le propriétaire peut révoquer un accès.');
            $this->redirect('/dashboard');
            return;
        }

        // Révocation interdite sur les comptes mineurs sauf par un modérateur
        if (!$isModerator) {
            $account = $this->accountModel->find($accId);
            if ($account) {
                $accountOwner = $this->userModel->find((int) $account['user_id']);
                if (User::isMinorFromDate($accountOwner['birth_date'] ?? null)) {
                    $this->setFlash('danger', 'La gestion des accès est interdite sur un compte mineur.');
                    $this->redirect('/accounts/' . $accountId);
                    return;
                }
            }
        }

        $revokedUser    = $this->userModel->find((int) $userId);
        $revokedAccount = $this->accountModel->find($accId);

        $this->accessModel->revokeAccess($accId, (int) $userId);
        // Notifier l'utilisateur révoqué
        if ($revokedUser) {
            $this->notifModel->notify(
                (int) $revokedUser['id'],
                'access_revoked',
                'Accès révoqué — compte « ' . ($revokedAccount['name'] ?? '?') . ' »',
                'Votre accès au compte bancaire « ' . ($revokedAccount['name'] ?? '?') . ' » a été révoqué.',
                null
            );
        }
        $this->setFlash('success', 'Accès révoqué.');
        AuditLog::log($currentUserId, AuditLog::ACTION_ACCESS_REVOKE, ['target_username' => $revokedUser['username'] ?? '?', 'name' => $revokedAccount['name'] ?? '?'], targetUserId: (int) $userId, targetAccountId: $accId);
        $this->redirect('/accounts/' . $accountId);
    }
}
