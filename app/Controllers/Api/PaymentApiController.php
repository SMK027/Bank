<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Account;
use App\Models\ApiClient;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Services\CurrencyConverter;

/**
 * API REST de paiement par carte bancaire.
 *
 * Authentification : HTTP Basic ou Bearer.
 *   - Basic : Authorization: Basic base64(api_key:api_secret)
 *   - Bearer (alternative) : Authorization: Bearer api_key:api_secret
 *
 * Endpoints :
 *   POST /api/v1/payments/debit   { card_number, amount, currency?, comment? }
 *   POST /api/v1/payments/credit  { card_number, amount, currency?, comment? }
 *   POST /api/v1/cards/verify     { card_number }
 */
class PaymentApiController
{
    private ApiClient $clientModel;
    private PaymentCard $cardModel;
    private Account $accountModel;
    private Transaction $transactionModel;
    private ?array $currentClient = null;

    public function __construct()
    {
        $this->clientModel      = new ApiClient();
        $this->cardModel        = new PaymentCard();
        $this->accountModel     = new Account();
        $this->transactionModel = new Transaction();
    }

    // ── Endpoints publics ────────────────────────────────────────────────────

    public function debit(): void
    {
        $this->authenticate();
        $this->processOperation('debit');
    }

    public function credit(): void
    {
        $this->authenticate();
        $this->processOperation('credit');
    }

    public function verify(): void
    {
        $this->authenticate();
        $body = $this->getJsonBody();

        $card = $this->resolveCard((string) ($body['card_number'] ?? ''));
        if (!$card) {
            $this->json(['success' => false, 'message' => 'Carte inconnue ou invalide.'], 404);
            return;
        }

        $account = $this->accountModel->find((int) $card['account_id']);
        $this->json([
            'success' => true,
            'card' => [
                'last4'    => $card['last4'],
                'masked'   => PaymentCard::mask($card['card_number']),
                'status'   => $card['status'],
                'currency' => $account['currency'] ?? null,
            ],
        ]);
    }

    // ── Logique principale ───────────────────────────────────────────────────

    private function processOperation(string $operation): void
    {
        $body = $this->getJsonBody();

        $cardNumber = (string) ($body['card_number'] ?? '');
        $amount     = (float) ($body['amount'] ?? 0);
        $currency   = strtoupper(trim((string) ($body['currency'] ?? '')));
        $comment    = mb_substr((string) ($body['comment'] ?? ''), 0, 255);

        if ($amount <= 0) {
            $this->logFailure($operation, null, null, $amount, $currency, 'invalid_amount', $comment);
            $this->json(['success' => false, 'message' => 'Montant invalide.'], 400);
            return;
        }

        $card = $this->resolveCard($cardNumber);
        if (!$card) {
            $this->logFailure($operation, null, null, $amount, $currency, 'card_not_found', $comment);
            $this->json(['success' => false, 'message' => 'Carte inconnue ou invalide.'], 404);
            return;
        }

        if (($card['status'] ?? '') !== 'active') {
            $this->logFailure($operation, (int) $card['id'], (int) $card['account_id'], $amount, $currency, 'card_blocked', $comment);
            $this->json(['success' => false, 'message' => 'Carte bloquée.'], 403);
            return;
        }

        $account = $this->accountModel->find((int) $card['account_id']);
        if (!$account) {
            $this->logFailure($operation, (int) $card['id'], null, $amount, $currency, 'account_missing', $comment);
            $this->json(['success' => false, 'message' => 'Compte associé introuvable.'], 404);
            return;
        }

        $eventBlockedReason = Account::operationBlockedReason($account);
        if ($eventBlockedReason !== null) {
            $this->logFailure($operation, (int) $card['id'], (int) $account['id'], $amount, $currency, 'event_closed', $comment);
            $this->json(['success' => false, 'message' => $eventBlockedReason], 403);
            return;
        }

        // Comptes d'épargne interdits (sécurité défensive : on l'empêche déjà côté UI)
        if (!Account::typeAllowsCard($account['type'] ?? '')) {
            $this->logFailure($operation, (int) $card['id'], (int) $account['id'], $amount, $currency, 'savings_account', $comment);
            $this->json(['success' => false, 'message' => 'Ce compte ne peut pas être débité par carte.'], 403);
            return;
        }

        if (!empty($account['disabled_at'])) {
            $this->logFailure($operation, (int) $card['id'], (int) $account['id'], $amount, $currency, 'account_disabled', $comment);
            $this->json(['success' => false, 'message' => 'Compte désactivé.'], 403);
            return;
        }

        // Compte gelé : aucune opération sortante
        if ($operation === 'debit' && !empty($account['frozen'])) {
            $this->logFailure($operation, (int) $card['id'], (int) $account['id'], $amount, $currency, 'account_frozen', $comment);
            $this->json(['success' => false, 'message' => 'Compte gelé.'], 403);
            return;
        }

        // Conversion de devise si nécessaire
        $accountCurrency = $account['currency'] ?? 'EUR';
        $finalAmount     = $amount;
        $exchangeRate    = 1.0;
        if ($currency !== '' && $currency !== $accountCurrency) {
            try {
                $converter = new CurrencyConverter();
                $converted = $converter->convert($amount, $currency, $accountCurrency);
                $finalAmount  = (float) $converted['amount'];
                $exchangeRate = (float) $converted['rate'];
            } catch (\Throwable $e) {
                $this->logFailure($operation, (int) $card['id'], (int) $account['id'], $amount, $currency, 'currency_conversion_failed', $comment);
                $this->json(['success' => false, 'message' => 'Conversion de devise impossible.'], 502);
                return;
            }
        }
        $finalAmount = round($finalAmount, 2);

        // Vérification du découvert pour les débits
        if ($operation === 'debit') {
            $balance     = $this->accountModel->getFutureBalance((int) $account['id']);
            $overdraft   = (float) ($account['overdraft'] ?? 0);
            if (!Account::typeAllowsOverdraft($account['type'] ?? 'standard')) {
                $overdraft = 0.0;
            }
            if (($balance - $finalAmount) < -$overdraft) {
                $this->logFailure($operation, (int) $card['id'], (int) $account['id'], $finalAmount, $accountCurrency, 'insufficient_funds', $comment);
                $this->json(['success' => false, 'message' => 'Solde insuffisant.'], 402);
                return;
            }
        }

        // Vérification du plafond pour les crédits sur compte épargne (impossible ici, mais défensif)
        if ($operation === 'credit' && Account::typeHasCap($account['type'] ?? '')) {
            $cap = (float) ($account['cap'] ?? 0);
            if ($cap > 0) {
                $balance = $this->accountModel->getFutureBalance((int) $account['id']);
                if ($balance + $finalAmount > $cap) {
                    $this->logFailure($operation, (int) $card['id'], (int) $account['id'], $finalAmount, $accountCurrency, 'cap_exceeded', $comment);
                    $this->json(['success' => false, 'message' => 'Plafond du compte dépassé.'], 403);
                    return;
                }
            }
        }

        // Création de la transaction
        $type     = $operation === 'debit' ? 'expense' : 'income';
        $clientName = $this->currentClient['name'] ?? 'API';
        $category = $type === 'expense' ? 'Factures' : 'Autre';
        $finalComment = sprintf(
            '[API %s • carte %s] %s',
            $clientName,
            PaymentCard::mask($card['card_number']),
            $comment !== '' ? $comment : ($operation === 'debit' ? 'Débit par carte' : 'Crédit par carte')
        );

        try {
            $txId = $this->transactionModel->addTransaction(
                (int) $account['id'],
                $type,
                $finalAmount,
                $category,
                $finalComment,
                (int) $account['user_id'],
                null
            );
        } catch (\Throwable $e) {
            $this->logFailure($operation, (int) $card['id'], (int) $account['id'], $finalAmount, $accountCurrency, 'transaction_failed', $comment);
            $this->json(['success' => false, 'message' => 'Erreur interne lors de la transaction.'], 500);
            return;
        }

        // Journal API + audit + notification
        $this->logPayment([
            'api_client_id'  => (int) $this->currentClient['id'],
            'card_id'        => (int) $card['id'],
            'account_id'     => (int) $account['id'],
            'transaction_id' => $txId,
            'operation'      => $operation,
            'amount'         => $finalAmount,
            'currency'       => $accountCurrency,
            'status'         => 'success',
            'reason'         => '',
            'comment'        => $comment,
        ]);

        AuditLog::log(
            null,
            $operation === 'debit' ? AuditLog::ACTION_API_PAYMENT_DEBIT : AuditLog::ACTION_API_PAYMENT_CREDIT,
            [
                'api_client'     => $clientName,
                'card_last4'     => $card['last4'],
                'amount'         => $finalAmount,
                'currency'       => $accountCurrency,
                'transaction_id' => $txId,
                'rate'           => $exchangeRate,
                'comment'        => $comment,
            ],
            targetUserId: (int) $account['user_id'],
            targetAccountId: (int) $account['id']
        );

        // Notification utilisateur
        try {
            $notif = new Notification();
            $title = $operation === 'debit'
                ? sprintf('Débit de %s %s par %s', number_format($finalAmount, 2, ',', ' '), $accountCurrency, $clientName)
                : sprintf('Crédit de %s %s par %s', number_format($finalAmount, 2, ',', ' '), $accountCurrency, $clientName);
            $body = sprintf('Carte ****%s', $card['last4']);
            if ($comment !== '') {
                $body .= ' — ' . $comment;
            }
            $type = $operation === 'debit' ? 'direct_debit_success' : 'transfer_received';
            $notif->notify((int) $account['user_id'], $type, $title, $body, '/accounts/' . (int) $account['id']);
        } catch (\Throwable) {
            // Non bloquant
        }

        $this->json([
            'success' => true,
            'operation' => $operation,
            'transaction_id' => $txId,
            'amount' => $finalAmount,
            'currency' => $accountCurrency,
            'exchange_rate' => $exchangeRate,
            'card' => [
                'last4'  => $card['last4'],
                'masked' => PaymentCard::mask($card['card_number']),
            ],
        ]);
    }

    // ── Authentification ─────────────────────────────────────────────────────

    private function authenticate(): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        $apiKey = $apiSecret = '';

        if (preg_match('/^Basic\s+(.+)$/i', $header, $m)) {
            $decoded = base64_decode($m[1], true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$apiKey, $apiSecret] = explode(':', $decoded, 2);
            }
        } elseif (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            if (str_contains($m[1], ':')) {
                [$apiKey, $apiSecret] = explode(':', $m[1], 2);
            }
        } else {
            // Fallback : authentification HTTP Basic standard via PHP_AUTH_USER
            $apiKey    = $_SERVER['PHP_AUTH_USER'] ?? '';
            $apiSecret = $_SERVER['PHP_AUTH_PW']   ?? '';
        }

        if ($apiKey === '' || $apiSecret === '') {
            $this->json(['success' => false, 'message' => 'Authentification requise.'], 401);
            return;
        }

        $client = $this->clientModel->authenticate($apiKey, $apiSecret);
        if (!$client) {
            $this->json(['success' => false, 'message' => 'Identifiants API invalides.'], 401);
            return;
        }

        $this->currentClient = $client;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function resolveCard(string $number): ?array
    {
        $normalized = PaymentCard::normalize($number);
        if (!PaymentCard::isValidLuhn($normalized)) {
            return null;
        }
        return $this->cardModel->findByNumber($normalized);
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function logPayment(array $row): void
    {
        try {
            $cols = array_map(fn($c) => "`{$c}`", array_keys($row));
            $vals = array_fill(0, count($row), '?');
            $sql  = sprintf(
                'INSERT INTO `api_payments` (%s, `created_at`) VALUES (%s, NOW())',
                implode(', ', $cols),
                implode(', ', $vals)
            );
            $stmt = \App\Core\Database::getInstance()->prepare($sql);
            $stmt->execute(array_values($row));
        } catch (\Throwable) {
            // Non bloquant
        }
    }

    private function logFailure(
        string $operation,
        ?int $cardId,
        ?int $accountId,
        float $amount,
        string $currency,
        string $reason,
        string $comment
    ): void {
        $clientId = (int) ($this->currentClient['id'] ?? 0);
        if ($clientId === 0) {
            return;
        }
        $this->logPayment([
            'api_client_id'  => $clientId,
            'card_id'        => $cardId,
            'account_id'     => $accountId,
            'transaction_id' => null,
            'operation'      => $operation,
            'amount'         => $amount,
            'currency'       => $currency ?: 'EUR',
            'status'         => 'failed',
            'reason'         => $reason,
            'comment'        => $comment,
        ]);

        AuditLog::log(null, AuditLog::ACTION_API_PAYMENT_FAIL, [
            'api_client' => $this->currentClient['name'] ?? '',
            'operation'  => $operation,
            'reason'     => $reason,
            'amount'     => $amount,
            'currency'   => $currency,
        ], targetAccountId: $accountId);
    }
}
