<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\OverdraftAuthorization;
use App\Models\User;

class ModerationOverdraftController extends Controller
{
    private OverdraftAuthorization $authModel;
    private Account $accountModel;
    private User $userModel;
    private Notification $notifModel;

    public function __construct()
    {
        $this->authModel    = new OverdraftAuthorization();
        $this->accountModel = new Account();
        $this->userModel    = new User();
        $this->notifModel   = new Notification();
    }

    // ── Liste globale ─────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireModerator();

        $authorizations = $this->authModel->getAll();

        // Calculer le statut de chaque autorisation
        foreach ($authorizations as &$a) {
            $a['status'] = OverdraftAuthorization::computeStatus($a);
        }
        unset($a);

        // Liste des comptes pour le formulaire de création
        $allAccounts = $this->accountModel->findAll('id', 'ASC');
        foreach ($allAccounts as &$acc) {
            $owner = $this->userModel->find((int) $acc['user_id']);
            $acc['owner_name'] = $owner ? $owner['username'] : 'Inconnu';
        }
        unset($acc);

        $this->render('moderation/overdraft_authorizations', [
            'title'          => 'Autorisations de dépassement',
            'authorizations' => $authorizations,
            'allAccounts'    => $allAccounts,
        ]);
    }

    // ── Création ─────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $moderatorId = $this->getCurrentUserId();
        $data = $this->getPostData(['account_id', 'extra_limit', 'start_date', 'end_date', 'reason']);

        $accountId  = (int) ($data['account_id'] ?? 0);
        $extraLimit = abs((float) ($data['extra_limit'] ?? 0));
        $startDate  = trim($data['start_date'] ?? '');
        $endDate    = !empty(trim($data['end_date'] ?? '')) ? trim($data['end_date']) : null;
        $reason     = trim($data['reason'] ?? '');

        // Validations
        if ($accountId <= 0) {
            $this->setFlash('danger', 'Compte invalide.');
            $this->redirect('/moderation/overdraft-authorizations');
            return;
        }

        $account = $this->accountModel->find($accountId);
        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/moderation/overdraft-authorizations');
            return;
        }

        if ($extraLimit <= 0) {
            $this->setFlash('danger', 'Le montant supplémentaire doit être supérieur à zéro.');
            $this->redirect('/moderation/overdraft-authorizations');
            return;
        }

        if (empty($startDate) || !\DateTime::createFromFormat('Y-m-d', $startDate)) {
            $this->setFlash('danger', 'Date de début invalide.');
            $this->redirect('/moderation/overdraft-authorizations');
            return;
        }

        if ($endDate !== null) {
            if (!\DateTime::createFromFormat('Y-m-d', $endDate)) {
                $this->setFlash('danger', 'Date de fin invalide.');
                $this->redirect('/moderation/overdraft-authorizations');
                return;
            }
            if ($endDate < $startDate) {
                $this->setFlash('danger', 'La date de fin doit être postérieure ou égale à la date de début.');
                $this->redirect('/moderation/overdraft-authorizations');
                return;
            }
        }

        // Vérifier qu'il n'existe pas déjà une autorisation active sur ce compte
        $existing = $this->authModel->getActiveForAccount($accountId);
        if ($existing) {
            $this->setFlash('danger', sprintf(
                'Le compte « %s » possède déjà une autorisation active (#%d). Révoquez-la avant d\'en créer une nouvelle.',
                $account['name'],
                $existing['id']
            ));
            $this->redirect('/moderation/overdraft-authorizations');
            return;
        }

        $authId = $this->authModel->createAuthorization(
            $accountId,
            $moderatorId,
            $extraLimit,
            $startDate,
            $endDate,
            $reason
        );

        AuditLog::log($moderatorId, 'overdraft_authorization.create', [
            'authorization_id' => $authId,
            'account_id'       => $accountId,
            'extra_limit'      => $extraLimit,
            'start_date'       => $startDate,
            'end_date'         => $endDate,
            'reason'           => $reason,
        ], targetAccountId: $accountId);

        // Notifier le propriétaire du compte
        $owner = $this->userModel->find((int) $account['user_id']);
        if ($owner) {
            $currency = $account['currency'] ?? 'EUR';
            $this->notifModel->notify(
                (int) $owner['id'],
                'overdraft_authorization',
                'Autorisation de découvert accordée',
                sprintf(
                    'Une autorisation de dépassement de %s %s a été accordée sur votre compte « %s » à partir du %s%s.',
                    number_format($extraLimit, 2, ',', ' '),
                    $currency,
                    $account['name'],
                    date('d/m/Y', strtotime($startDate)),
                    $endDate ? ' jusqu\'au ' . date('d/m/Y', strtotime($endDate)) : ''
                ),
                '/accounts/' . $accountId
            );
        }

        $this->setFlash('success', sprintf(
            'Autorisation de dépassement de %s %s créée pour le compte « %s ».',
            number_format($extraLimit, 2, ',', ' '),
            $account['currency'] ?? '',
            $account['name']
        ));
        $this->redirect('/moderation/overdraft-authorizations');
    }

    // ── Révocation ────────────────────────────────────────────────────────────

    public function revoke(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $authId      = (int) $id;
        $moderatorId = $this->getCurrentUserId();

        $auth = $this->authModel->find($authId);
        if (!$auth) {
            $this->setFlash('danger', 'Autorisation introuvable.');
            $this->redirect('/moderation/overdraft-authorizations');
            return;
        }

        if ($auth['revoked_at'] !== null) {
            $this->setFlash('warning', 'Cette autorisation est déjà révoquée.');
            $this->redirect('/moderation/overdraft-authorizations');
            return;
        }

        $ok = $this->authModel->revoke($authId, $moderatorId);
        if (!$ok) {
            $this->setFlash('danger', 'Révocation échouée.');
            $this->redirect('/moderation/overdraft-authorizations');
            return;
        }

        AuditLog::log($moderatorId, 'overdraft_authorization.revoke', [
            'authorization_id' => $authId,
            'account_id'       => (int) $auth['account_id'],
        ], targetAccountId: (int) $auth['account_id']);

        // Notifier le propriétaire
        $account = $this->accountModel->find((int) $auth['account_id']);
        if ($account) {
            $owner = $this->userModel->find((int) $account['user_id']);
            if ($owner) {
                $this->notifModel->notify(
                    (int) $owner['id'],
                    'overdraft_authorization_revoked',
                    'Autorisation de découvert révoquée',
                    sprintf(
                        'L\'autorisation de dépassement sur votre compte « %s » a été révoquée.',
                        $account['name']
                    ),
                    '/accounts/' . $account['id']
                );
            }
        }

        $this->setFlash('success', 'Autorisation révoquée.');
        $this->redirect('/moderation/overdraft-authorizations');
    }
}
