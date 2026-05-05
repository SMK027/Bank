<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CurrencyConverter;

/**
 * Terminal de Paiement Électronique (TPE) raccordé à la plateforme.
 *
 * Permet à un commerçant (compte professionnel) ou à un modérateur
 * d'encaisser un paiement par carte bancaire fictive : la carte
 * du client est débitée, et le compte professionnel sélectionné
 * du commerçant est crédité du montant correspondant.
 */
class PosController extends Controller
{
    private PaymentCard $cardModel;
    private Account $accountModel;
    private Transaction $transactionModel;
    private User $userModel;

    public function __construct()
    {
        $this->cardModel        = new PaymentCard();
        $this->accountModel     = new Account();
        $this->transactionModel = new Transaction();
        $this->userModel        = new User();
    }

    /**
     * Vérifie l'accès au TPE : utilisateur professionnel ou modérateur.
     */
    private function requirePosAccess(): array
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();
        $user   = $this->userModel->find((int) $userId);

        if (!$user) {
            $this->redirect('/login');
            exit;
        }

        $isPro = User::isProfessional($user);
        $isMod = $this->isModerator();

        if (!$isPro && !$isMod) {
            $this->setFlash('danger', 'Le TPE est réservé aux comptes professionnels et à la modération.');
            $this->redirect('/dashboard');
            exit;
        }

        return $user;
    }

    /** Comptes professionnels du commerçant éligibles pour recevoir un encaissement. */
    private function getMerchantAccounts(int $userId): array
    {
        $accounts = $this->accountModel->getByUser($userId);
        return array_values(array_filter($accounts, function (array $a): bool {
            return ($a['type'] ?? '') === 'pro' && empty($a['disabled_at']);
        }));
    }

    /** Affiche le TPE. */
    public function index(): void
    {
        $user             = $this->requirePosAccess();
        $merchantAccounts = $this->getMerchantAccounts((int) $user['id']);

        $this->render('pos/index', [
            'title'            => 'Terminal de paiement (TPE)',
            'user'             => $user,
            'merchantAccounts' => $merchantAccounts,
            'form'             => [],
            'receipt'          => $_SESSION['pos_receipt'] ?? null,
        ]);

        unset($_SESSION['pos_receipt']);
    }

    /** Traite un encaissement par carte. */
    public function charge(): void
    {
        $user = $this->requirePosAccess();
        $this->validateCSRF();

        $merchantAccounts = $this->getMerchantAccounts((int) $user['id']);

        $cardNumber  = (string) ($_POST['card_number'] ?? '');
        $amountInput = (string) ($_POST['amount']      ?? '');
        $label       = trim((string) ($_POST['label']    ?? ''));
        $merchant    = trim((string) ($_POST['merchant'] ?? ''));
        $accountId   = (int)   ($_POST['account_id']     ?? 0);

        $amount = (float) str_replace(',', '.', $amountInput);

        $form = [
            'card_number' => $cardNumber,
            'amount'      => $amountInput,
            'label'       => $label,
            'merchant'    => $merchant,
            'account_id'  => $accountId,
        ];

        $errors = [];
        if ($cardNumber === '')                    { $errors[] = 'Le numéro de carte est obligatoire.'; }
        if ($amount <= 0)                          { $errors[] = 'Le montant doit être strictement positif.'; }
        if ($label === '')                         { $errors[] = 'L\'intitulé de l\'opération est obligatoire.'; }
        if ($merchant === '')                      { $errors[] = 'Le nom du commerçant est obligatoire.'; }
        if ($accountId <= 0)                       { $errors[] = 'Sélectionnez le compte d\'encaissement.'; }
        if (mb_strlen($label) > 120)               { $errors[] = 'L\'intitulé est trop long (120 caractères max).'; }
        if (mb_strlen($merchant) > 120)            { $errors[] = 'Le nom du commerçant est trop long (120 caractères max).'; }

        // Le compte d'encaissement doit appartenir au commerçant et être professionnel
        $merchantAccount = null;
        foreach ($merchantAccounts as $a) {
            if ((int) $a['id'] === $accountId) {
                $merchantAccount = $a;
                break;
            }
        }
        if ($accountId > 0 && !$merchantAccount) {
            $errors[] = 'Compte d\'encaissement invalide.';
        }

        if (!empty($errors)) {
            $this->renderForm($user, $merchantAccounts, $form, $errors);
            return;
        }

        // Recherche de la carte (Luhn + existence)
        $normalized = PaymentCard::normalize($cardNumber);
        $card = PaymentCard::isValidLuhn($normalized) ? $this->cardModel->findByNumber($normalized) : null;
        if (!$card) {
            $this->logFailure($user, $merchantAccount, $amount, $label, $merchant, 'card_not_found');
            $errors[] = 'Carte inconnue ou numéro invalide.';
            $this->renderForm($user, $merchantAccounts, $form, $errors);
            return;
        }

        if (($card['status'] ?? '') !== 'active') {
            $this->logFailure($user, $merchantAccount, $amount, $label, $merchant, 'card_blocked', $card);
            $errors[] = 'Cette carte est bloquée.';
            $this->renderForm($user, $merchantAccounts, $form, $errors);
            return;
        }

        // On autorise les commerçants/modérateurs à débiter une carte leur
        // appartenant : on bloque uniquement si le compte associé à la carte
        // est le même que le compte d'encaissement (transfert vers soi-même
        // sur le même compte = absurde).
        if ((int) $card['account_id'] === (int) $merchantAccount['id']) {
            $this->logFailure($user, $merchantAccount, $amount, $label, $merchant, 'same_account', $card);
            $errors[] = 'Le compte associé à la carte est identique au compte d\'encaissement.';
            $this->renderForm($user, $merchantAccounts, $form, $errors);
            return;
        }

        $customerAccount = $this->accountModel->find((int) $card['account_id']);
        if (!$customerAccount) {
            $this->logFailure($user, $merchantAccount, $amount, $label, $merchant, 'customer_account_missing', $card);
            $errors[] = 'Compte associé à la carte introuvable.';
            $this->renderForm($user, $merchantAccounts, $form, $errors);
            return;
        }

        if (!Account::typeAllowsCard($customerAccount['type'] ?? '')) {
            $this->logFailure($user, $merchantAccount, $amount, $label, $merchant, 'savings_account', $card);
            $errors[] = 'Ce compte ne peut pas être débité par carte.';
            $this->renderForm($user, $merchantAccounts, $form, $errors);
            return;
        }

        if (!empty($customerAccount['disabled_at'])) {
            $this->logFailure($user, $merchantAccount, $amount, $label, $merchant, 'customer_account_disabled', $card);
            $errors[] = 'Compte client désactivé.';
            $this->renderForm($user, $merchantAccounts, $form, $errors);
            return;
        }

        if (!empty($customerAccount['frozen'])) {
            $this->logFailure($user, $merchantAccount, $amount, $label, $merchant, 'customer_account_frozen', $card);
            $errors[] = 'Compte client gelé.';
            $this->renderForm($user, $merchantAccounts, $form, $errors);
            return;
        }

        // Conversion de devise éventuelle (montant saisi = devise du commerçant)
        $merchantCurrency = $merchantAccount['currency'] ?? 'EUR';
        $customerCurrency = $customerAccount['currency'] ?? 'EUR';
        $merchantAmount   = round($amount, 2);
        $customerAmount   = $merchantAmount;
        $exchangeRate     = 1.0;

        if ($customerCurrency !== $merchantCurrency) {
            try {
                $converter = new CurrencyConverter();
                $converted = $converter->convert($merchantAmount, $merchantCurrency, $customerCurrency);
                $customerAmount = round((float) $converted['amount'], 2);
                $exchangeRate   = (float) $converted['rate'];
            } catch (\Throwable $e) {
                $this->logFailure($user, $merchantAccount, $amount, $label, $merchant, 'currency_conversion_failed', $card);
                $errors[] = 'Conversion de devise impossible.';
                $this->renderForm($user, $merchantAccounts, $form, $errors);
                return;
            }
        }

        // Vérification du solde côté client (avec autorisation de découvert si applicable)
        $balance   = $this->accountModel->getFutureBalance((int) $customerAccount['id']);
        $overdraft = (float) ($customerAccount['overdraft'] ?? 0);
        if (!Account::typeAllowsOverdraft($customerAccount['type'] ?? 'standard')) {
            $overdraft = 0.0;
        }
        if (($balance - $customerAmount) < -$overdraft) {
            $this->logFailure($user, $merchantAccount, $amount, $label, $merchant, 'insufficient_funds', $card);
            $errors[] = 'Solde insuffisant sur le compte du client.';
            $this->renderForm($user, $merchantAccounts, $form, $errors);
            return;
        }

        $masked   = PaymentCard::mask($card['card_number']);
        $debitCmt = sprintf('[TPE • %s] %s — carte %s', $merchant, $label, $masked);
        $credCmt  = sprintf('[TPE • %s] %s — carte %s', $merchant, $label, $masked);

        try {
            $debitTxId = $this->transactionModel->addTransaction(
                (int) $customerAccount['id'],
                'expense',
                $customerAmount,
                'Achats',
                $debitCmt,
                (int) $customerAccount['user_id'],
                null
            );
            $creditTxId = $this->transactionModel->addTransaction(
                (int) $merchantAccount['id'],
                'income',
                $merchantAmount,
                'Encaissement',
                $credCmt,
                (int) $merchantAccount['user_id'],
                null
            );
        } catch (\Throwable $e) {
            $this->logFailure($user, $merchantAccount, $amount, $label, $merchant, 'transaction_failed', $card);
            $errors[] = 'Erreur interne lors de l\'enregistrement de la transaction.';
            $this->renderForm($user, $merchantAccounts, $form, $errors);
            return;
        }

        // Journal api_payments via le client API système (lazy-créé)
        $apiClientId = $this->getSystemApiClientId();
        $this->logApiPayment([
            'api_client_id'  => $apiClientId,
            'card_id'        => (int) $card['id'],
            'account_id'     => (int) $customerAccount['id'],
            'transaction_id' => $debitTxId,
            'operation'      => 'debit',
            'amount'         => $customerAmount,
            'currency'       => $customerCurrency,
            'status'         => 'success',
            'reason'         => '',
            'comment'        => mb_substr($merchant . ' • ' . $label, 0, 255),
        ]);

        // Audit
        AuditLog::log(
            (int) $user['id'],
            AuditLog::ACTION_POS_CHARGE,
            [
                'merchant'           => $merchant,
                'label'              => $label,
                'amount'             => $merchantAmount,
                'currency'           => $merchantCurrency,
                'card_last4'         => $card['last4'] ?? '',
                'merchant_account'   => (int) $merchantAccount['id'],
                'customer_account'   => (int) $customerAccount['id'],
                'debit_transaction'  => $debitTxId,
                'credit_transaction' => $creditTxId,
                'exchange_rate'      => $exchangeRate,
            ],
            targetUserId: (int) $card['user_id'],
            targetAccountId: (int) $customerAccount['id']
        );

        // Notifications
        try {
            $notif = new Notification();
            $notif->notify(
                (int) $card['user_id'],
                'card',
                'Paiement par carte',
                sprintf(
                    'Débit de %.2f %s chez %s (%s) — carte %s.',
                    $customerAmount, $customerCurrency, $merchant, $label, $masked
                ),
                '/accounts/' . (int) $customerAccount['id']
            );
            $notif->notify(
                (int) $user['id'],
                'card',
                'Encaissement TPE',
                sprintf(
                    'Encaissement de %.2f %s — %s (carte %s).',
                    $merchantAmount, $merchantCurrency, $label, $masked
                ),
                '/accounts/' . (int) $merchantAccount['id']
            );
        } catch (\Throwable $e) {
            // Notifications best-effort
        }

        // Reçu pour la prochaine page
        $_SESSION['pos_receipt'] = [
            'merchant'         => $merchant,
            'label'            => $label,
            'amount'           => $merchantAmount,
            'currency'         => $merchantCurrency,
            'card_masked'      => $masked,
            'merchant_account' => $merchantAccount['name'] ?? '',
            'datetime'         => date('d/m/Y H:i:s'),
            'reference'        => 'TX-' . $debitTxId,
        ];

        $this->setFlash('success', 'Paiement accepté.');
        $this->redirect('/pos');
    }

    private function renderForm(array $user, array $merchantAccounts, array $form, array $errors): void
    {
        $this->render('pos/index', [
            'title'            => 'Terminal de paiement (TPE)',
            'user'             => $user,
            'merchantAccounts' => $merchantAccounts,
            'form'             => $form,
            'errors'           => $errors,
            'receipt'          => null,
        ]);
    }

    /** Récupère (ou crée) le client API système associé au TPE interne. */
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
                (api_client_id, card_id, account_id, transaction_id, operation, amount, currency, status, reason, comment)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['api_client_id'],
            $data['card_id']        ?? null,
            $data['account_id']     ?? null,
            $data['transaction_id'] ?? null,
            $data['operation'],
            $data['amount'],
            $data['currency'],
            $data['status'],
            $data['reason']  ?? '',
            $data['comment'] ?? '',
        ]);
    }

    private function logFailure(
        array $user,
        ?array $merchantAccount,
        float $amount,
        string $label,
        string $merchant,
        string $reason,
        ?array $card = null
    ): void {
        try {
            $apiClientId = $this->getSystemApiClientId();
            $this->logApiPayment([
                'api_client_id'  => $apiClientId,
                'card_id'        => $card ? (int) $card['id']        : null,
                'account_id'     => $card ? (int) $card['account_id'] : null,
                'transaction_id' => null,
                'operation'      => 'debit',
                'amount'         => $amount,
                'currency'       => $merchantAccount['currency'] ?? 'EUR',
                'status'         => 'failed',
                'reason'         => $reason,
                'comment'        => mb_substr($merchant . ' • ' . $label, 0, 255),
            ]);
        } catch (\Throwable $e) {
            // best-effort
        }

        AuditLog::log(
            (int) $user['id'],
            AuditLog::ACTION_POS_CHARGE_FAIL,
            [
                'merchant' => $merchant,
                'label'    => $label,
                'amount'   => $amount,
                'reason'   => $reason,
                'card_last4' => $card['last4'] ?? null,
            ]
        );
    }
}
