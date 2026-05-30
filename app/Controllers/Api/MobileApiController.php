<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\JWT;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\LoginRateLimit;
use App\Models\Notification;
use App\Models\PaymentCard;
use App\Models\PosStatus;
use App\Models\Transaction;
use App\Models\User;

/**
 * API mobile dédiée à l'application TPE Expo.
 *
 * Authentification par JWT (Bearer). Réservée aux comptes professionnels
 * (au moins un compte de type `pro` actif) et aux modérateurs.
 *
 * Endpoints :
 *   POST /api/v1/mobile/login          { email, password }
 *   GET  /api/v1/mobile/status         (Bearer)
 *   POST /api/v1/mobile/transaction    (Bearer) { type, card_number, label, amount, account_id? }
 */
class MobileApiController extends ApiController
{
    private User $userModel;
    private Account $accountModel;
    private PaymentCard $cardModel;
    private Transaction $transactionModel;

    public function __construct()
    {
        $this->userModel        = new User();
        $this->accountModel     = new Account();
        $this->cardModel        = new PaymentCard();
        $this->transactionModel = new Transaction();
    }

    // ── Authentification ────────────────────────────────────────────────────

    public function login(): void
    {
        $body     = $this->getJsonBody();
        $email    = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->error('Email et mot de passe requis.', 400);
        }

        $ip      = LoginRateLimit::resolveClientIp();
        $limiter = new LoginRateLimit();
        if ($limiter->isBlocked($ip)) {
            $this->error('Trop de tentatives. Réessayez plus tard.', 429);
        }

        $user = $this->userModel->findByEmail($email);
        if (!$user || !password_verify($password, $user['password'])) {
            $limiter->recordFailedAttempt($ip);
            $this->error('Identifiants invalides.', 401);
        }

        $isModerator = !empty($user['global_role']) && $user['global_role'] === 'moderator';
        $isPro       = User::isProfessional($user);

        if (!$isPro) {
            $accounts = $this->accountModel->getByUser((int) $user['id']);
            $isPro = !empty(array_filter(
                $accounts,
                fn(array $a) => ($a['type'] ?? '') === 'pro' && empty($a['disabled_at'])
            ));
        }

        if (!$isPro && !$isModerator) {
            $this->error('Accès réservé aux comptes professionnels et à la modération.', 403);
        }

        $limiter->clearIp($ip);

        $token = JWT::encode([
            'user_id'     => (int) $user['id'],
            'email'       => $user['email'],
            'global_role' => $user['global_role'] ?? 'user',
            'scope'       => 'mobile_pos',
        ], 86400 * 7);

        $this->json([
            'success' => true,
            'token'   => $token,
            'user'    => $this->publicUser($user, $isModerator),
            'pos'     => $this->buildPosStatus($user, $isModerator),
        ]);
    }

    public function status(): void
    {
        $this->requireAuth();
        $user = $this->userModel->find((int) $this->userId);
        if (!$user) {
            $this->error('Utilisateur introuvable.', 404);
        }
        $isModerator = ($user['global_role'] ?? '') === 'moderator';
        $this->json([
            'success' => true,
            'user'    => $this->publicUser($user, $isModerator),
            'pos'     => $this->buildPosStatus($user, $isModerator),
        ]);
    }

    // ── Transaction ─────────────────────────────────────────────────────────

    public function transaction(): void
    {
        $this->requireAuth();
        $user = $this->userModel->find((int) $this->userId);
        if (!$user) {
            $this->error('Utilisateur introuvable.', 404);
        }
        $isModerator = ($user['global_role'] ?? '') === 'moderator';

        $posStatus = PosStatus::current();
        if ($posStatus['is_disabled']) {
            $reason = !empty($posStatus['reason']) ? ' (motif : ' . $posStatus['reason'] . ')' : '';
            $this->error('Le TPE est actuellement désactivé par la modération' . $reason . '.', 403);
        }

        $body       = $this->getJsonBody();
        $type       = strtolower((string) ($body['type'] ?? ''));
        $cardNumber = (string) ($body['card_number'] ?? '');
        $label      = trim((string) ($body['label'] ?? ''));
        $amountRaw  = (string) ($body['amount'] ?? '');
        $amount     = (float) str_replace(',', '.', $amountRaw);
        $accountId  = (int) ($body['account_id'] ?? 0);

        if (!in_array($type, ['debit', 'credit'], true)) {
            $this->error('Type de transaction invalide.', 400);
        }
        if ($cardNumber === '') {
            $this->error('Numéro de carte requis.', 400);
        }
        if ($amount <= 0) {
            $this->error('Le montant doit être strictement positif.', 400);
        }
        if ($label === '') {
            $this->error('L\'intitulé de l\'opération est requis.', 400);
        }
        if (mb_strlen($label) > 120) {
            $this->error('Intitulé trop long (120 caractères max).', 400);
        }

        // Comptes pro éligibles
        $allProAccounts = array_values(array_filter(
            $this->accountModel->getByUser((int) $user['id']),
            fn(array $a) => ($a['type'] ?? '') === 'pro' && empty($a['disabled_at'])
        ));
        $merchantAccounts = array_values(array_filter(
            $allProAccounts,
            fn(array $a) => !Account::isPosSuspended($a)
        ));

        // Ban TPE : aucun compte non suspendu alors qu'il en avait
        if (!$isModerator && empty($merchantAccounts) && !empty($allProAccounts)) {
            $reason = '';
            foreach ($allProAccounts as $a) {
                if (Account::isPosSuspended($a) && !empty($a['pos_suspend_reason'])) {
                    $reason = $a['pos_suspend_reason'];
                    break;
                }
            }
            $msg = 'Votre accès au TPE est suspendu par la modération';
            if ($reason !== '') {
                $msg .= ' (motif : ' . $reason . ')';
            }
            $this->error($msg . '.', 403);
        }

        if (!$isModerator && empty($merchantAccounts)) {
            $this->error('Aucun compte professionnel actif rattaché.', 403);
        }

        // Sélection du compte marchand
        $merchantAccount = null;
        if ($accountId > 0) {
            foreach ($merchantAccounts as $a) {
                if ((int) $a['id'] === $accountId) {
                    $merchantAccount = $a;
                    break;
                }
            }
            if (!$merchantAccount) {
                $this->error('Compte d\'encaissement invalide.', 400);
            }
        } elseif (!empty($merchantAccounts)) {
            $merchantAccount = $merchantAccounts[0];
        }

        // Résolution de la carte
        $normalized = PaymentCard::normalize($cardNumber);
        $card = PaymentCard::isValidLuhn($normalized) ? $this->cardModel->findByNumber($normalized) : null;
        if (!$card) {
            $this->error('Carte inconnue ou numéro invalide.', 404);
        }
        if (($card['status'] ?? '') !== 'active') {
            $this->error('Cette carte est bloquée.', 403);
        }
        if (PaymentCard::isExpired($card)) {
            $this->error('Cette carte est expirée.', 403);
        }

        $customerAccount = $this->accountModel->find((int) $card['account_id']);
        if (!$customerAccount) {
            $this->error('Compte client introuvable.', 404);
        }
        if (!Account::typeAllowsCard($customerAccount['type'] ?? '')) {
            $this->error('Ce compte ne peut pas être débité par carte.', 403);
        }
        if (!empty($customerAccount['disabled_at'])) {
            $this->error('Compte client désactivé.', 403);
        }
        if (!empty($customerAccount['frozen'])) {
            $this->error('Compte client gelé.', 403);
        }
        if ($merchantAccount && (int) $card['account_id'] === (int) $merchantAccount['id']) {
            $this->error('Le compte de la carte est identique au compte d\'encaissement.', 400);
        }

        $masked = PaymentCard::mask($card['card_number']);
        $merchantAmount = round($amount, 2);
        $customerAmount = $merchantAmount; // pas de conversion multi-devise dans le MVP mobile

        if ($type === 'debit') {
            // Plafond mensuel carte
            $monthlyLimit = isset($card['monthly_limit']) && $card['monthly_limit'] !== null
                ? (float) $card['monthly_limit'] : null;
            if ($monthlyLimit !== null) {
                $spent = $this->cardModel->getMonthlyTotal((int) $card['id']);
                if ($customerAmount > round($monthlyLimit - $spent, 2)) {
                    $this->error('Plafond mensuel de la carte dépassé.', 403);
                }
            }
            // Solde + découvert client
            $balance   = $this->accountModel->getFutureBalance((int) $customerAccount['id']);
            $overdraft = Account::typeAllowsOverdraft($customerAccount['type'] ?? 'standard')
                ? (float) ($customerAccount['overdraft'] ?? 0) : 0.0;
            if (($balance - $customerAmount) < -$overdraft) {
                $this->error('Solde insuffisant sur le compte du client.', 402);
            }
        } else {
            // CREDIT : merchant -> customer (remboursement)
            if (!$merchantAccount) {
                $this->error('Un compte d\'encaissement est requis pour un crédit.', 400);
            }
            $mBalance   = $this->accountModel->getFutureBalance((int) $merchantAccount['id']);
            $mOverdraft = Account::typeAllowsOverdraft($merchantAccount['type'] ?? 'standard')
                ? (float) ($merchantAccount['overdraft'] ?? 0) : 0.0;
            if (($mBalance - $merchantAmount) < -$mOverdraft) {
                $this->error('Solde insuffisant sur le compte d\'encaissement.', 402);
            }
        }

        // Persistance
        try {
            if ($type === 'debit') {
                $customerTxId = $this->transactionModel->addTransaction(
                    (int) $customerAccount['id'],
                    'expense',
                    $customerAmount,
                    'Achats',
                    sprintf('[TPE mobile] %s — carte %s', $label, $masked),
                    (int) $customerAccount['user_id'],
                    null
                );
                $merchantTxId = $merchantAccount ? $this->transactionModel->addTransaction(
                    (int) $merchantAccount['id'],
                    'income',
                    $merchantAmount,
                    'Encaissement',
                    sprintf('[TPE mobile] %s — carte %s', $label, $masked),
                    (int) $merchantAccount['user_id'],
                    null
                ) : null;
            } else {
                $merchantTxId = $this->transactionModel->addTransaction(
                    (int) $merchantAccount['id'],
                    'expense',
                    $merchantAmount,
                    'Remboursement',
                    sprintf('[TPE mobile • remboursement] %s — carte %s', $label, $masked),
                    (int) $merchantAccount['user_id'],
                    null
                );
                $customerTxId = $this->transactionModel->addTransaction(
                    (int) $customerAccount['id'],
                    'income',
                    $customerAmount,
                    'Remboursement',
                    sprintf('[TPE mobile • remboursement] %s — carte %s', $label, $masked),
                    (int) $customerAccount['user_id'],
                    null
                );
            }
        } catch (\Throwable $e) {
            $this->error('Erreur interne lors de l\'enregistrement de la transaction.', 500);
        }

        // Journal api_payments (visible dans /moderation/pos-payments)
        try {
            $apiClientId = $this->getSystemApiClientId();
            $merchantName = trim((string) ($user['company_name'] ?? ''));
            if ($merchantName === '') {
                $merchantName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            }
            if ($merchantName === '') {
                $merchantName = $user['email'] ?? 'TPE mobile';
            }
            $commentLog = mb_substr($merchantName . ' (mobile) • ' . $label, 0, 255);
            $this->logApiPayment([
                'api_client_id'         => $apiClientId,
                'card_id'               => (int) $card['id'],
                'account_id'            => (int) $customerAccount['id'],
                'transaction_id'        => $type === 'debit' ? $customerTxId : $merchantTxId,
                'credit_transaction_id' => $type === 'debit' ? $merchantTxId : $customerTxId,
                'deferred_debit_id'     => null,
                'operation'             => $type,
                'amount'                => $merchantAmount,
                'currency'              => $merchantAccount['currency'] ?? ($customerAccount['currency'] ?? 'EUR'),
                'status'                => 'success',
                'reason'                => 'mobile',
                'comment'               => $commentLog,
            ]);
        } catch (\Throwable $e) {
            // best-effort : ne bloque pas la réponse mobile
        }

        AuditLog::log(
            (int) $user['id'],
            $type === 'debit' ? AuditLog::ACTION_POS_CHARGE : AuditLog::ACTION_POS_REFUND,
            [
                'source'           => 'mobile',
                'label'            => $label,
                'amount'           => $merchantAmount,
                'card_last4'       => $card['last4'] ?? '',
                'merchant_account' => $merchantAccount ? (int) $merchantAccount['id'] : null,
                'customer_account' => (int) $customerAccount['id'],
                'customer_tx'      => $customerTxId,
                'merchant_tx'      => $merchantTxId,
            ],
            targetUserId: (int) $card['user_id'],
            targetAccountId: (int) $customerAccount['id']
        );

        // Notification au porteur de la carte
        try {
            $notif = new Notification();
            if ($type === 'debit') {
                $notif->notify(
                    (int) $card['user_id'],
                    'pos_charge',
                    'Paiement par carte',
                    sprintf('Débit de %.2f %s sur la carte %s — %s', $customerAmount, $customerAccount['currency'] ?? 'EUR', $masked, $label),
                    '/transactions'
                );
            } else {
                $notif->notify(
                    (int) $card['user_id'],
                    'pos_refund',
                    'Remboursement sur carte',
                    sprintf('Crédit de %.2f %s sur la carte %s — %s', $customerAmount, $customerAccount['currency'] ?? 'EUR', $masked, $label),
                    '/transactions'
                );
            }
        } catch (\Throwable $e) {
            // notification non bloquante
        }

        $this->json([
            'success' => true,
            'message' => $type === 'debit' ? 'Débit effectué avec succès.' : 'Crédit effectué avec succès.',
            'transaction' => [
                'type'           => $type,
                'amount'         => $merchantAmount,
                'currency'       => $merchantAccount['currency'] ?? ($customerAccount['currency'] ?? 'EUR'),
                'card'           => $masked,
                'label'          => $label,
                'merchant_tx_id' => $merchantTxId,
                'customer_tx_id' => $customerTxId,
                'at'             => date('c'),
            ],
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────
    /**
     * Identifiant du client API système partagé avec le TPE web,
     * pour que les paiements mobiles apparaissent dans la même liste
     * de modération (/moderation/pos-payments).
     */
    private function getSystemApiClientId(): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id FROM api_clients WHERE api_key = ? LIMIT 1');
        $stmt->execute(['pos_internal']);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }
        $secret = bin2hex(random_bytes(24));
        $hash   = password_hash($secret, PASSWORD_BCRYPT);
        $ins = $pdo->prepare(
            'INSERT INTO api_clients (name, api_key, api_secret_hash, status, created_by)
             VALUES (?, ?, ?, ?, NULL)'
        );
        $ins->execute(['TPE Interne', 'pos_internal', $hash, 'active']);
        return (int) $pdo->lastInsertId();
    }

    private function logApiPayment(array $data): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO api_payments
                (api_client_id, card_id, account_id, transaction_id, credit_transaction_id, deferred_debit_id, operation, amount, currency, status, reason, comment)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['api_client_id'],
            $data['card_id']               ?? null,
            $data['account_id']            ?? null,
            $data['transaction_id']        ?? null,
            $data['credit_transaction_id'] ?? null,
            $data['deferred_debit_id']     ?? null,
            $data['operation'],
            $data['amount'],
            $data['currency'],
            $data['status'],
            $data['reason']  ?? '',
            $data['comment'] ?? '',
        ]);
    }
    private function publicUser(array $user, bool $isModerator): array
    {
        return [
            'id'         => (int) $user['id'],
            'email'      => $user['email'],
            'first_name' => $user['first_name'] ?? '',
            'last_name'  => $user['last_name'] ?? '',
            'company'    => $user['company_name'] ?? null,
            'role'       => $user['global_role'] ?? 'user',
            'is_moderator'    => $isModerator,
            'is_professional' => User::isProfessional($user),
        ];
    }

    private function buildPosStatus(array $user, bool $isModerator): array
    {
        $posStatus = PosStatus::current();
        $allProAccounts = array_values(array_filter(
            $this->accountModel->getByUser((int) $user['id']),
            fn(array $a) => ($a['type'] ?? '') === 'pro' && empty($a['disabled_at'])
        ));
        $merchantAccounts = array_values(array_filter(
            $allProAccounts,
            fn(array $a) => !Account::isPosSuspended($a)
        ));

        $banned = !$isModerator && empty($merchantAccounts) && !empty($allProAccounts);
        $banReason = '';
        if ($banned) {
            foreach ($allProAccounts as $a) {
                if (!empty($a['pos_suspend_reason'])) {
                    $banReason = (string) $a['pos_suspend_reason'];
                    break;
                }
            }
        }

        return [
            'active'      => !$posStatus['is_disabled'],
            'reason'      => $posStatus['reason'] ?? '',
            'banned'      => $banned,
            'ban_reason'  => $banReason,
            'can_operate' => !$posStatus['is_disabled'] && !$banned && ($isModerator || !empty($merchantAccounts)),
            'accounts'    => array_map(fn(array $a) => [
                'id'       => (int) $a['id'],
                'label'    => $a['label'] ?? ('Compte #' . $a['id']),
                'currency' => $a['currency'] ?? 'EUR',
            ], $merchantAccounts),
        ];
    }
}
