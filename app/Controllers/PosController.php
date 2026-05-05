<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Account;
use App\Models\ApiPayment;
use App\Models\AuditLog;
use App\Models\DeferredDebit;
use App\Models\Notification;
use App\Models\PaymentCard;
use App\Models\PosStatus;
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
    private DeferredDebit $deferredDebitModel;
    private ApiPayment $paymentModel;

    public function __construct()
    {
        $this->cardModel          = new PaymentCard();
        $this->accountModel       = new Account();
        $this->transactionModel   = new Transaction();
        $this->userModel          = new User();
        $this->deferredDebitModel = new DeferredDebit();
        $this->paymentModel       = new ApiPayment();
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
            return ($a['type'] ?? '') === 'pro'
                && empty($a['disabled_at'])
                && !Account::isPosSuspended($a);
        }));
    }

    /** Affiche le TPE. */
    public function index(): void
    {
        $user             = $this->requirePosAccess();
        $allProAccounts   = array_values(array_filter(
            $this->accountModel->getByUser((int) $user['id']),
            fn(array $a) => ($a['type'] ?? '') === 'pro' && empty($a['disabled_at'])
        ));
        $merchantAccounts = array_values(array_filter(
            $allProAccounts,
            fn(array $a) => !Account::isPosSuspended($a)
        ));
        $posStatus        = PosStatus::current();

        $this->render('pos/index', [
            'title'                => 'Terminal de paiement (TPE)',
            'user'                 => $user,
            'merchantAccounts'     => $merchantAccounts,
            'merchantAccountsRaw'  => $allProAccounts,
            'form'                 => [],
            'receipt'              => $_SESSION['pos_receipt'] ?? null,
            'posStatus'            => $posStatus,
        ]);

        unset($_SESSION['pos_receipt']);
    }

    /** Traite un encaissement par carte. */
    public function charge(): void
    {
        $user = $this->requirePosAccess();
        $this->validateCSRF();

        $merchantAccounts = $this->getMerchantAccounts((int) $user['id']);

        // TPE désactivé par la modération : on bloque tout encaissement.
        $posStatus = PosStatus::current();
        if ($posStatus['is_disabled']) {
            $msg = 'Le TPE est actuellement désactivé par la modération';
            if (!empty($posStatus['reason'])) {
                $msg .= ' (motif : ' . $posStatus['reason'] . ')';
            }
            $msg .= '.';
            $this->renderForm($user, $merchantAccounts, [
                'card_number' => (string) ($_POST['card_number'] ?? ''),
                'amount'      => (string) ($_POST['amount']      ?? ''),
                'label'       => trim((string) ($_POST['label']    ?? '')),
                'merchant'    => trim((string) ($_POST['merchant'] ?? '')),
                'account_id'  => (int)   ($_POST['account_id']     ?? 0),
            ], [$msg]);
            return;
        }

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
        if (mb_strlen($label) > 120)               { $errors[] = 'L\'intitulé est trop long (120 caractères max).'; }
        if (mb_strlen($merchant) > 120)            { $errors[] = 'Le nom du commerçant est trop long (120 caractères max).'; }

        // Compte d'encaissement : facultatif. S'il est fourni, il doit
        // appartenir au commerçant et être professionnel.
        $merchantAccount = null;
        if ($accountId > 0) {
            foreach ($merchantAccounts as $a) {
                if ((int) $a['id'] === $accountId) {
                    $merchantAccount = $a;
                    break;
                }
            }
            if (!$merchantAccount) {
                $errors[] = 'Compte d\'encaissement invalide.';
            } elseif (Account::isPosSuspended($merchantAccount)) {
                // Compte suspendu du TPE par la modération.
                $msg = 'Ce compte est suspendu du TPE';
                if (!empty($merchantAccount['pos_suspend_reason'])) {
                    $msg .= ' (motif : ' . $merchantAccount['pos_suspend_reason'] . ')';
                }
                $errors[] = $msg . '.';
                $merchantAccount = null; // empêche tout traitement ultérieur
            }
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

        if (PaymentCard::isExpired($card)) {
            $expiryFmt = !empty($card['expires_at']) ? date('m/Y', strtotime($card['expires_at'])) : null;
            $this->logFailure($user, $merchantAccount, $amount, $label, $merchant, 'card_expired', $card);
            $errors[] = 'Cette carte est expirée' . ($expiryFmt ? ' (depuis ' . $expiryFmt . ')' : '') . '.';
            $this->renderForm($user, $merchantAccounts, $form, $errors);
            return;
        }

        // On autorise les commerçants/modérateurs à débiter une carte leur
        // appartenant : on bloque uniquement si le compte associé à la carte
        // est le même que le compte d'encaissement (transfert vers soi-même
        // sur le même compte = absurde).
        if ($merchantAccount && (int) $card['account_id'] === (int) $merchantAccount['id']) {
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

        // Conversion de devise éventuelle (montant saisi = devise du commerçant
        // si fourni, sinon devise du compte client par défaut).
        $customerCurrency = $customerAccount['currency'] ?? 'EUR';
        $merchantCurrency = $merchantAccount['currency'] ?? $customerCurrency;
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

        // Vérification du plafond mensuel de la carte
        $monthlyLimit = isset($card['monthly_limit']) && $card['monthly_limit'] !== null
            ? (float) $card['monthly_limit']
            : null;
        if ($monthlyLimit !== null) {
            $monthlySpent = $this->cardModel->getMonthlySpent((int) $card['id']);
            $remaining    = round($monthlyLimit - $monthlySpent, 2);
            if ($customerAmount > $remaining) {
                $this->logFailure($user, $merchantAccount, $amount, $label, $merchant, 'card_limit_exceeded', $card);
                $errors[] = sprintf(
                    'Plafond mensuel de la carte dépassé. Restant disponible ce mois-ci : %.2f %s.',
                    max(0, $remaining),
                    $customerCurrency
                );
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

        // Débit différé : si le compte du client l'utilise, on enregistre une
        // opération en attente plutôt qu'un débit immédiat. Le commerçant est
        // crédité immédiatement (cf. fonctionnement réel des cartes à débit
        // différé).
        $deferred       = !empty($customerAccount['deferred_debit_enabled'])
                       && !empty($customerAccount['deferred_debit_day']);
        $debitTxId      = null;
        $deferredId     = null;
        $deferredDate   = null;

        try {
            if ($deferred) {
                $deferredDate = $this->computeNextDeferredDate(
                    (int) $customerAccount['deferred_debit_day']
                );
                $deferredId = $this->deferredDebitModel->createDeferredDebit(
                    (int) $customerAccount['id'],
                    (int) $customerAccount['user_id'],
                    $customerAmount,
                    'Achats',
                    $debitCmt,
                    date('Y-m-d H:i:s'),
                    $deferredDate
                );
            } else {
                $debitTxId = $this->transactionModel->addTransaction(
                    (int) $customerAccount['id'],
                    'expense',
                    $customerAmount,
                    'Achats',
                    $debitCmt,
                    (int) $customerAccount['user_id'],
                    null
                );
            }
            $creditTxId = null;
            if ($merchantAccount) {
                $creditTxId = $this->transactionModel->addTransaction(
                    (int) $merchantAccount['id'],
                    'income',
                    $merchantAmount,
                    'Encaissement',
                    $credCmt,
                    (int) $merchantAccount['user_id'],
                    null
                );
            }
        } catch (\Throwable $e) {
            $this->logFailure($user, $merchantAccount, $amount, $label, $merchant, 'transaction_failed', $card);
            $errors[] = 'Erreur interne lors de l\'enregistrement de la transaction.';
            $this->renderForm($user, $merchantAccounts, $form, $errors);
            return;
        }

        // Journal api_payments via le client API système (lazy-créé)
        $apiClientId = $this->getSystemApiClientId();
        $this->logApiPayment([
            'api_client_id'         => $apiClientId,
            'card_id'               => (int) $card['id'],
            'account_id'            => (int) $customerAccount['id'],
            'transaction_id'        => $debitTxId,
            'credit_transaction_id' => $creditTxId,
            'deferred_debit_id'     => $deferredId,
            'operation'             => 'debit',
            'amount'                => $customerAmount,
            'currency'              => $customerCurrency,
            'status'                => 'success',
            'reason'                => $deferred ? 'deferred' : '',
            'comment'               => mb_substr($merchant . ' • ' . $label, 0, 255),
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
                'merchant_account'   => $merchantAccount ? (int) $merchantAccount['id'] : null,
                'customer_account'   => (int) $customerAccount['id'],
                'debit_transaction'  => $debitTxId,
                'credit_transaction' => $creditTxId,
                'exchange_rate'      => $exchangeRate,
                'deferred'           => $deferred,
                'deferred_id'        => $deferredId,
                'deferred_date'      => $deferredDate,
            ],
            targetUserId: (int) $card['user_id'],
            targetAccountId: (int) $customerAccount['id']
        );

        // Notifications
        try {
            $notif = new Notification();
            $deferredFr = $deferred && $deferredDate
                ? ' (débit différé prévu le ' . date('d/m/Y', strtotime($deferredDate)) . ')'
                : '';
            $notif->notify(
                (int) $card['user_id'],
                'card',
                $deferred ? 'Paiement par carte (différé)' : 'Paiement par carte',
                sprintf(
                    'Débit de %.2f %s chez %s (%s) — carte %s%s.',
                    $customerAmount, $customerCurrency, $merchant, $label, $masked, $deferredFr
                ),
                '/accounts/' . (int) $customerAccount['id']
            );
            if ($merchantAccount) {
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
            }
        } catch (\Throwable $e) {
            // Notifications best-effort
        }

        // Reçu pour la prochaine page
        $receipt = [
            'merchant'         => $merchant,
            'label'            => $label,
            'amount'           => $merchantAmount,
            'currency'         => $merchantCurrency,
            'card_masked'      => $masked,
            'merchant_account' => $merchantAccount['name'] ?? null,
            'datetime'         => date('d/m/Y H:i:s'),
            'reference'        => $debitTxId !== null
                ? 'TX-' . $debitTxId
                : 'DD-' . $deferredId,
            'deferred'         => $deferred,
            'deferred_date'    => $deferred && $deferredDate
                ? date('d/m/Y', strtotime($deferredDate))
                : null,
        ];

        $message = $deferred
            ? 'Paiement accepté — débit différé enregistré.'
            : 'Paiement accepté.';

        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => true,
                'message' => $message,
                'receipt' => $receipt,
            ]);
            return;
        }

        $_SESSION['pos_receipt'] = $receipt;
        $this->setFlash('success', $message);
        $this->redirect('/pos');
    }

    /**
     * Calcule la prochaine date de fin de période pour un débit différé,
     * d'après le jour préféré (1-31) configuré sur le compte.
     */
    private function computeNextDeferredDate(int $day): string
    {
        $day   = max(1, min(31, $day));
        $today = new \DateTimeImmutable('today');

        $candidate = $this->buildMonthlyDate($today, $day);
        if ($candidate <= $today) {
            $candidate = $this->buildMonthlyDate($today->modify('first day of next month'), $day);
        }
        return $candidate->format('Y-m-d');
    }

    /** Construit une date au jour `$day` du mois donné, écrêtée au dernier jour. */
    private function buildMonthlyDate(\DateTimeImmutable $monthAnchor, int $day): \DateTimeImmutable
    {
        $lastDay = (int) $monthAnchor->format('t');
        $clamped = min($day, $lastDay);
        return $monthAnchor->setDate(
            (int) $monthAnchor->format('Y'),
            (int) $monthAnchor->format('n'),
            $clamped
        );
    }

    private function renderForm(array $user, array $merchantAccounts, array $form, array $errors): void
    {
        if ($this->isAjax()) {
            $this->jsonResponse([
                'success' => false,
                'errors'  => $errors,
                'message' => $errors[0] ?? 'Paiement refusé.',
            ], 422);
            return;
        }

        $this->render('pos/index', [
            'title'                => 'Terminal de paiement (TPE)',
            'user'                 => $user,
            'merchantAccounts'     => $merchantAccounts,
            'merchantAccountsRaw'  => $merchantAccounts, // déjà filtrés dans ce contexte
            'form'                 => $form,
            'errors'               => $errors,
            'receipt'              => null,
            'posStatus'            => PosStatus::current(),
        ]);
    }

    /**
     * Vérification temps réel d'un numéro de carte (JSON).
     * Retourne le statut sans révéler d'informations sensibles.
     *
     * Si le paramètre `amount` est fourni (>0), un contrôle de solde et
     * de plafond mensuel est également effectué en temps réel.
     *
     * États retournés :
     *  invalid            — numéro invalide (format / Luhn)
     *  unknown            — carte inconnue
     *  blocked            — carte bloquée manuellement
     *  disabled           — carte désactivée (compte désactivé/gelé/inéligible)
     *  expired            — carte expirée
     *  insufficient_funds — solde insuffisant pour ce montant
     *  limit_exceeded     — plafond mensuel dépassé pour ce montant
     *  offline            — TPE désactivé par la modération
     *  ok                 — carte valide et utilisable
     */
    public function verifyCard(): void
    {
        $this->requirePosAccess();

        $raw        = (string) ($_GET['number'] ?? $_POST['number'] ?? '');
        $amountRaw  = (string) ($_GET['amount']  ?? $_POST['amount']  ?? '');
        $normalized = PaymentCard::normalize($raw);
        $amount     = $amountRaw !== '' ? (float) str_replace(',', '.', $amountRaw) : null;

        $payload = [
            'success'      => false,
            'state'        => 'invalid',
            'message'      => '',
            'detail'       => null,   // info complémentaire non sensible
            'masked'       => null,
            'currency'     => null,
            'last4'        => null,
            'expires_at'   => null,   // MM/YY affiché, ou null
            'monthly_limit'=> null,
            'monthly_spent'=> null,
        ];

        // TPE désactivé par la modération
        $posStatus = PosStatus::current();
        if ($posStatus['is_disabled']) {
            $payload['state']   = 'offline';
            $payload['message'] = trim('TPE désactivé par la modération. ' . (string) ($posStatus['reason'] ?? ''));
            $this->jsonResponse($payload);
            return;
        }

        if ($normalized === '' || strlen($normalized) < 13) {
            $payload['message'] = 'Numéro incomplet.';
            $this->jsonResponse($payload);
            return;
        }

        if (!PaymentCard::isValidLuhn($normalized)) {
            $payload['message'] = 'Numéro invalide (Luhn).';
            $this->jsonResponse($payload);
            return;
        }

        $card = $this->cardModel->findByNumber($normalized);
        if (!$card) {
            $payload['state']   = 'unknown';
            $payload['message'] = 'Carte inconnue.';
            $this->jsonResponse($payload);
            return;
        }

        // Carte expirée
        if (PaymentCard::isExpired($card)) {
            $expiryFmt = $card['expires_at']
                ? date('m/Y', strtotime($card['expires_at']))
                : null;
            $payload['state']      = 'expired';
            $payload['masked']     = PaymentCard::mask($card['card_number']);
            $payload['last4']      = $card['last4'] ?? null;
            $payload['expires_at'] = $expiryFmt;
            $payload['message']    = 'Carte expirée' . ($expiryFmt ? ' depuis ' . $expiryFmt : '') . '.';
            $this->jsonResponse($payload);
            return;
        }

        // Carte bloquée
        if (($card['status'] ?? '') !== 'active') {
            $payload['state']   = 'blocked';
            $payload['masked']  = PaymentCard::mask($card['card_number']);
            $payload['last4']   = $card['last4'] ?? null;
            $payload['message'] = 'Carte bloquée.';
            $this->jsonResponse($payload);
            return;
        }

        $account = $this->accountModel->find((int) $card['account_id']);

        // Compte inéligible (type)
        if (!$account || !Account::typeAllowsCard($account['type'] ?? '')) {
            $payload['state']   = 'disabled';
            $payload['masked']  = PaymentCard::mask($card['card_number']);
            $payload['last4']   = $card['last4'] ?? null;
            $payload['message'] = 'Ce type de compte ne peut pas être débité par carte.';
            $this->jsonResponse($payload);
            return;
        }

        // Compte désactivé
        if (!empty($account['disabled_at'])) {
            $payload['state']   = 'disabled';
            $payload['masked']  = PaymentCard::mask($card['card_number']);
            $payload['last4']   = $card['last4'] ?? null;
            $payload['message'] = 'Compte associé à la carte désactivé.';
            $this->jsonResponse($payload);
            return;
        }

        // Compte gelé
        if (!empty($account['frozen'])) {
            $payload['state']   = 'disabled';
            $payload['masked']  = PaymentCard::mask($card['card_number']);
            $payload['last4']   = $card['last4'] ?? null;
            $payload['message'] = 'Compte associé à la carte gelé.';
            $this->jsonResponse($payload);
            return;
        }

        // Carte valide — vérifications montant si fourni
        $masked      = PaymentCard::mask($card['card_number']);
        $currency    = $account['currency'] ?? 'EUR';
        $expiryFmt   = !empty($card['expires_at']) ? date('m/Y', strtotime($card['expires_at'])) : null;
        $monthlyLimit = isset($card['monthly_limit']) && $card['monthly_limit'] !== null
            ? (float) $card['monthly_limit']
            : null;
        $monthlySpent = $monthlyLimit !== null ? $this->cardModel->getMonthlySpent((int) $card['id']) : null;

        if ($amount !== null && $amount > 0) {
            // Vérification solde
            $balance   = $this->accountModel->getFutureBalance((int) $account['id']);
            $overdraft = (float) ($account['overdraft'] ?? 0);
            if (!Account::typeAllowsOverdraft($account['type'] ?? 'standard')) {
                $overdraft = 0.0;
            }
            if (($balance - $amount) < -$overdraft) {
                $payload['state']   = 'insufficient_funds';
                $payload['masked']  = $masked;
                $payload['last4']   = $card['last4'] ?? null;
                $payload['currency']= $currency;
                $payload['message'] = 'Solde insuffisant.';
                $payload['detail']  = 'Solde disponible : ' . number_format(max(0, $balance + $overdraft), 2, ',', ' ') . ' ' . $currency;
                $this->jsonResponse($payload);
                return;
            }

            // Vérification plafond mensuel
            if ($monthlyLimit !== null) {
                $monthlySpent = $this->cardModel->getMonthlySpent((int) $card['id']);
                $remaining    = round($monthlyLimit - $monthlySpent, 2);
                if ($amount > $remaining) {
                    $payload['state']        = 'limit_exceeded';
                    $payload['masked']       = $masked;
                    $payload['last4']        = $card['last4'] ?? null;
                    $payload['currency']     = $currency;
                    $payload['monthly_limit']= $monthlyLimit;
                    $payload['monthly_spent']= $monthlySpent;
                    $payload['message']      = 'Plafond mensuel dépassé.';
                    $payload['detail']       = 'Restant disponible ce mois-ci : ' . number_format(max(0, $remaining), 2, ',', ' ') . ' ' . $currency;
                    $this->jsonResponse($payload);
                    return;
                }
            }
        }

        $payload['success']       = true;
        $payload['state']         = 'ok';
        $payload['masked']        = $masked;
        $payload['last4']         = $card['last4'] ?? null;
        $payload['currency']      = $currency;
        $payload['expires_at']    = $expiryFmt;
        $payload['monthly_limit'] = $monthlyLimit;
        $payload['monthly_spent'] = $monthlySpent;
        $msg = 'Carte valide' . ($masked ? ' (' . $masked . ')' : '');
        if ($currency) {
            $msg .= ' — devise ' . $currency;
        }
        if ($expiryFmt) {
            $msg .= ' — expire ' . $expiryFmt;
        }
        $payload['message'] = $msg . '.';
        $this->jsonResponse($payload);
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

    // ── Modération ─────────────────────────────────────────────────────────

    /** Liste des paiements TPE (modération uniquement). */
    public function moderationIndex(): void
    {
        $this->requireModerator();

        $apiClientId = $this->getSystemApiClientId();

        $filters = [
            'api_client_id' => $apiClientId,
            'client'        => trim((string) ($_GET['client']     ?? '')),
            'merchant'      => trim((string) ($_GET['merchant']   ?? '')),
            'amount_min'    => trim((string) ($_GET['amount_min'] ?? '')),
            'amount_max'    => trim((string) ($_GET['amount_max'] ?? '')),
            'date_from'     => trim((string) ($_GET['date_from']  ?? '')),
            'date_to'       => trim((string) ($_GET['date_to']    ?? '')),
            'status'        => trim((string) ($_GET['status']     ?? '')),
        ];

        $hasFilters = (bool) array_filter([
            $filters['client'], $filters['merchant'],
            $filters['amount_min'], $filters['amount_max'],
            $filters['date_from'], $filters['date_to'], $filters['status'],
        ]);

        $limit    = $hasFilters ? 500 : 200;
        $payments = $this->paymentModel->searchForModeration($filters, $limit);

        // Charger les cartes pour afficher les last4
        $cardIds = array_filter(array_column($payments, 'card_id'));
        $cardsById = [];
        if (!empty($cardIds)) {
            $ph   = implode(',', array_fill(0, count($cardIds), '?'));
            $stmt = Database::getInstance()->prepare("SELECT id, last4 FROM payment_cards WHERE id IN ($ph)");
            $stmt->execute(array_values($cardIds));
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $cardsById[(int) $row['id']] = $row;
            }
        }

        // Montants déjà remboursés par paiement (évite N+1)
        $paymentIds = array_map('intval', array_column($payments, 'id'));
        $refundsMap = $this->paymentModel->getRefundsMap($paymentIds);

        $this->render('moderation/pos_payments', [
            'title'             => 'Paiements TPE',
            'payments'          => $payments,
            'cardsById'         => $cardsById,
            'filters'           => $filters,
            'hasFilters'        => $hasFilters,
            'posStatus'         => PosStatus::current(),
            'suspendedAccounts' => $this->accountModel->getPosSuspendedAccounts(),
            'refundsMap'        => $refundsMap,
        ]);
    }

    /**
     * Désactive le TPE en temps réel pour toute la plateforme.
     * Motif obligatoire ; durée optionnelle (date+heure de réactivation
     * automatique). Modération uniquement.
     */
    public function moderationDisable(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $reason = trim((string) ($_POST['reason'] ?? ''));
        $until  = trim((string) ($_POST['disabled_until'] ?? ''));

        if ($reason === '') {
            $this->setFlash('danger', 'Le motif de désactivation du TPE est obligatoire.');
            $this->redirect('/moderation/pos-payments');
            return;
        }
        if (mb_strlen($reason) > 500) {
            $this->setFlash('danger', 'Le motif est trop long (500 caractères max).');
            $this->redirect('/moderation/pos-payments');
            return;
        }

        $untilSql = null;
        if ($until !== '') {
            // Accepte le format datetime-local (Y-m-dTH:i) ou Y-m-d H:i.
            $ts = strtotime(str_replace('T', ' ', $until));
            if ($ts === false || $ts <= time()) {
                $this->setFlash('danger', 'La date de réactivation doit être dans le futur.');
                $this->redirect('/moderation/pos-payments');
                return;
            }
            $untilSql = date('Y-m-d H:i:s', $ts);
        }

        $modId = (int) $this->getCurrentUserId();
        PosStatus::disable($modId, $reason, $untilSql);

        AuditLog::log($modId, 'pos.disable', [
            'reason'         => $reason,
            'disabled_until' => $untilSql,
        ]);

        $msg = 'TPE désactivé';
        if ($untilSql) {
            $msg .= ' jusqu\'au ' . date('d/m/Y H:i', strtotime($untilSql));
        }
        $msg .= '.';
        $this->setFlash('success', $msg);
        $this->redirect('/moderation/pos-payments');
    }

    /** Réactive le TPE. Modération uniquement. */
    public function moderationEnable(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $modId = (int) $this->getCurrentUserId();
        PosStatus::enable($modId);

        AuditLog::log($modId, 'pos.enable', []);

        $this->setFlash('success', 'TPE réactivé.');
        $this->redirect('/moderation/pos-payments');
    }

    /**
     * Suspend l'accès au TPE pour un compte professionnel précis.
     * Motif obligatoire ; durée optionnelle. Modération uniquement.
     */
    public function moderationMerchantSuspend(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $accountId = (int) $id;
        $account   = $this->accountModel->find($accountId);

        if (!$account || ($account['type'] ?? '') !== 'pro') {
            $this->setFlash('danger', 'Compte professionnel introuvable.');
            $this->redirect('/moderation/pos-payments');
            return;
        }

        $reason = trim((string) ($_POST['reason'] ?? ''));
        $until  = trim((string) ($_POST['suspended_until'] ?? ''));

        if ($reason === '') {
            $this->setFlash('danger', 'Le motif de suspension est obligatoire.');
            $this->redirect('/moderation/pos-payments');
            return;
        }
        if (mb_strlen($reason) > 500) {
            $this->setFlash('danger', 'Le motif est trop long (500 caractères max).');
            $this->redirect('/moderation/pos-payments');
            return;
        }

        $untilSql = null;
        if ($until !== '') {
            $ts = strtotime(str_replace('T', ' ', $until));
            if ($ts === false || $ts <= time()) {
                $this->setFlash('danger', 'La date de réactivation doit être dans le futur.');
                $this->redirect('/moderation/pos-payments');
                return;
            }
            $untilSql = date('Y-m-d H:i:s', $ts);
        }

        $modId = (int) $this->getCurrentUserId();
        $this->accountModel->suspendPos($accountId, $modId, $reason, $untilSql);

        AuditLog::log($modId, AuditLog::ACTION_POS_MERCHANT_SUSPEND, [
            'account_id'       => $accountId,
            'reason'           => $reason,
            'suspended_until'  => $untilSql,
        ], targetAccountId: $accountId);

        $msg = 'Compte #' . $accountId . ' suspendu du TPE';
        if ($untilSql) {
            $msg .= ' jusqu\'au ' . date('d/m/Y H:i', strtotime($untilSql));
        }
        $this->setFlash('success', $msg . '.');
        $this->redirect('/moderation/pos-payments');
    }

    /** Réactive l'accès au TPE pour un compte professionnel. Modération uniquement. */
    public function moderationMerchantResume(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $accountId = (int) $id;
        $account   = $this->accountModel->find($accountId);

        if (!$account || ($account['type'] ?? '') !== 'pro') {
            $this->setFlash('danger', 'Compte professionnel introuvable.');
            $this->redirect('/moderation/pos-payments');
            return;
        }

        $modId = (int) $this->getCurrentUserId();
        $this->accountModel->resumePos($accountId, $modId);

        AuditLog::log($modId, AuditLog::ACTION_POS_MERCHANT_RESUME, [
            'account_id' => $accountId,
        ], targetAccountId: $accountId);

        $this->setFlash('success', 'Accès TPE du compte #' . $accountId . ' réactivé.');
        $this->redirect('/moderation/pos-payments');
    }

    /**
     * Annule un paiement TPE (modération uniquement).
     *
     * - Débit immédiat : crée 2 transactions de contre-passation
     *   (remboursement client + récupération sur le compte commerçant).
     * - Débit différé encore en attente : marque le différé comme annulé
     *   et contre-passe uniquement le crédit du commerçant.
     */
    public function moderationCancel(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $paymentId = (int) $id;
        $payment   = $this->paymentModel->find($paymentId);

        if (!$payment || ($payment['status'] ?? '') !== ApiPayment::STATUS_SUCCESS) {
            $this->setFlash('danger', 'Paiement introuvable ou déjà invalidé.');
            $this->redirect('/moderation/pos-payments');
            return;
        }
        if ($this->paymentModel->isCancelled($payment)) {
            $this->setFlash('danger', 'Ce paiement a déjà été annulé.');
            $this->redirect('/moderation/pos-payments');
            return;
        }

        $reason      = trim((string) ($_POST['reason'] ?? ''));
        $moderatorId = $this->getCurrentUserId();
        $amount      = (float) $payment['amount'];
        $currency    = (string) ($payment['currency'] ?? 'EUR');
        $motif       = sprintf('Annulation paiement TPE #%d', $paymentId);

        // Déduire les remboursements partiels déjà effectués :
        // seul le solde restant est à contre-passer côté client et commerçant.
        $alreadyRefunded   = $this->paymentModel->getTotalRefunded($paymentId);
        $remainingAmount   = round($amount - $alreadyRefunded, 2);

        $customerAccountId = (int) ($payment['account_id'] ?? 0);
        $customerAccount   = $customerAccountId ? $this->accountModel->find($customerAccountId) : null;

        // 1) Côté client
        $deferredId = (int) ($payment['deferred_debit_id'] ?? 0);
        $reverseDebitTxId = null;
        if ($deferredId > 0) {
            $dd = $this->deferredDebitModel->find($deferredId);
            if ($dd && $dd['status'] === DeferredDebit::STATUS_PENDING) {
                $this->deferredDebitModel->cancel($deferredId);
            } elseif ($dd && $dd['status'] === DeferredDebit::STATUS_EXECUTED) {
                // Le différé a déjà été exécuté → contre-passation du solde restant
                if ($remainingAmount > 0.001 && $customerAccountId > 0) {
                    $reverseDebitTxId = $this->transactionModel->addTransaction(
                        $customerAccountId,
                        'income',
                        $remainingAmount,
                        'Achats',
                        $motif,
                        0
                    );
                }
            }
        } else {
            // Débit immédiat → remboursement du solde restant uniquement
            if ($remainingAmount > 0.001 && $customerAccountId > 0) {
                $reverseDebitTxId = $this->transactionModel->addTransaction(
                    $customerAccountId,
                    'income',
                    $remainingAmount,
                    'Achats',
                    $motif,
                    0
                );
            }
        }

        // 2) Côté commerçant : récupération du crédit résiduel
        // Le commerçant a été crédité du montant original, puis débité à chaque
        // remboursement partiel. On ne récupère donc que le solde restant.
        $merchantAccountId = null;
        $reverseCreditTxId = null;
        $creditTxId = (int) ($payment['credit_transaction_id'] ?? 0);
        if ($creditTxId > 0 && $remainingAmount > 0.001) {
            $creditTx = $this->transactionModel->find($creditTxId);
            if ($creditTx) {
                $merchantAccountId = (int) $creditTx['account_id'];
                $reverseCreditTxId = $this->transactionModel->addTransaction(
                    $merchantAccountId,
                    'expense',
                    $remainingAmount,
                    'Encaissement',
                    $motif,
                    0
                );
            }
        }

        // 3) Marquer le paiement comme annulé
        $this->paymentModel->markCancelled($paymentId, $moderatorId, $reason);

        // 4) Audit + notifications
        AuditLog::log(
            $moderatorId,
            AuditLog::ACTION_POS_CANCEL,
            [
                'payment_id'           => $paymentId,
                'amount'               => $amount,
                'already_refunded'     => $alreadyRefunded,
                'remaining_cancelled'  => $remainingAmount,
                'currency'             => $currency,
                'reason'               => $reason,
                'reverse_debit_tx'     => $reverseDebitTxId,
                'reverse_credit_tx'    => $reverseCreditTxId,
                'cancelled_deferred'   => $deferredId > 0,
            ],
            targetAccountId: $customerAccountId ?: null
        );

        try {
            $notif = new Notification();
            if ($customerAccount) {
                // Indiquer dans la notif si un remboursement partiel avait déjà eu lieu
                $notifAmount = $remainingAmount > 0.001 ? $remainingAmount : 0.0;
                $notifMsg = $notifAmount > 0
                    ? sprintf(
                        'Le paiement de %.2f %s a été annulé par la modération. %.2f %s vous ont été recrédités%s.',
                        $amount, $currency,
                        $notifAmount, $currency,
                        $reason !== '' ? ' (motif : ' . $reason . ')' : ''
                    )
                    : sprintf(
                        'Le paiement de %.2f %s a été annulé par la modération%s. Le montant total avait déjà été remboursé.',
                        $amount, $currency,
                        $reason !== '' ? ' (motif : ' . $reason . ')' : ''
                    );
                $notif->notify(
                    (int) $customerAccount['user_id'],
                    'card',
                    'Paiement TPE annulé',
                    $notifMsg,
                    '/accounts/' . $customerAccountId
                );
            }
            if ($merchantAccountId) {
                $merchantAccount = $this->accountModel->find($merchantAccountId);
                if ($merchantAccount) {
                    $notif->notify(
                        (int) $merchantAccount['user_id'],
                        'card',
                        'Encaissement TPE annulé',
                        sprintf(
                            'Un encaissement TPE de %.2f %s a été annulé par la modération.',
                            $amount, $currency
                        ),
                        '/accounts/' . $merchantAccountId
                    );
                }
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        $this->setFlash('success', sprintf('Paiement TPE #%d annulé.', $paymentId));
        $this->redirect('/moderation/pos-payments');
    }

    /**
     * Effectue un remboursement partiel (ou total) d'un paiement TPE.
     *
     * - Crédite le compte client du montant demandé.
     * - Débite le compte commerçant du même montant (si un crédit existait).
     * - Enregistre un ligne dans api_payment_refunds.
     * - Le cumul des remboursements ne peut pas dépasser le montant original.
     * - Impossible si le paiement est annulé, échoué ou encore en débit différé
     *   en attente (utiliser l'annulation complète dans ce cas).
     */
    public function moderationRefund(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $paymentId = (int) $id;
        $payment   = $this->paymentModel->find($paymentId);

        if (!$payment || ($payment['status'] ?? '') !== ApiPayment::STATUS_SUCCESS) {
            $this->setFlash('danger', 'Paiement introuvable ou non valide.');
            $this->redirect('/moderation/pos-payments');
            return;
        }
        if ($this->paymentModel->isCancelled($payment)) {
            $this->setFlash('danger', 'Ce paiement a déjà été annulé ; le remboursement partiel n\'est pas applicable.');
            $this->redirect('/moderation/pos-payments');
            return;
        }

        // Débit différé encore en attente → impossible de rembourser partiellement
        $deferredId = (int) ($payment['deferred_debit_id'] ?? 0);
        if ($deferredId > 0) {
            $dd = $this->deferredDebitModel->find($deferredId);
            if ($dd && ($dd['status'] ?? '') === DeferredDebit::STATUS_PENDING) {
                $this->setFlash('danger', 'Impossible de rembourser partiellement un débit différé en attente. Utilisez l\'annulation complète.');
                $this->redirect('/moderation/pos-payments');
                return;
            }
        }

        $rawAmount    = str_replace(',', '.', (string) ($_POST['refund_amount'] ?? ''));
        $refundAmount = round((float) $rawAmount, 2);
        $reason       = mb_substr(trim((string) ($_POST['reason'] ?? '')), 0, 255);

        if ($refundAmount <= 0) {
            $this->setFlash('danger', 'Le montant du remboursement doit être positif.');
            $this->redirect('/moderation/pos-payments');
            return;
        }

        $originalAmount  = (float) $payment['amount'];
        $alreadyRefunded = $this->paymentModel->getTotalRefunded($paymentId);
        $refundable      = round($originalAmount - $alreadyRefunded, 2);
        $currency        = (string) ($payment['currency'] ?? 'EUR');

        if ($refundAmount > $refundable + 0.001) {
            $this->setFlash('danger', sprintf(
                'Le montant dépasse le plafond remboursable (%.2f %s).',
                $refundable,
                $currency
            ));
            $this->redirect('/moderation/pos-payments');
            return;
        }

        $moderatorId       = $this->getCurrentUserId();
        $motif             = sprintf('[TPE] Remboursement partiel paiement #%d', $paymentId);
        $customerAccountId = (int) ($payment['account_id'] ?? 0);

        // 1) Crédit côté client
        $customerTxId = null;
        if ($customerAccountId > 0) {
            $customerTxId = $this->transactionModel->addTransaction(
                $customerAccountId,
                'income',
                $refundAmount,
                'Achats',
                $motif,
                0
            );
        }

        // 2) Débit côté commerçant
        $merchantTxId = null;
        $creditTxId   = (int) ($payment['credit_transaction_id'] ?? 0);
        if ($creditTxId > 0) {
            $creditTx = $this->transactionModel->find($creditTxId);
            if ($creditTx) {
                $merchantTxId = $this->transactionModel->addTransaction(
                    (int) $creditTx['account_id'],
                    'expense',
                    $refundAmount,
                    'Encaissement',
                    $motif,
                    0
                );
            }
        }

        // 3) Enregistrement du remboursement
        $this->paymentModel->addRefund(
            $paymentId,
            $refundAmount,
            $moderatorId,
            $reason,
            $customerTxId,
            $merchantTxId
        );

        // 4) Audit
        AuditLog::log(
            $moderatorId,
            AuditLog::ACTION_POS_REFUND,
            [
                'payment_id'     => $paymentId,
                'amount'         => $refundAmount,
                'currency'       => $currency,
                'reason'         => $reason,
                'customer_tx'    => $customerTxId,
                'merchant_tx'    => $merchantTxId,
                'total_refunded' => round($alreadyRefunded + $refundAmount, 2),
                'original'       => $originalAmount,
            ],
            targetAccountId: $customerAccountId ?: null
        );

        // 5) Notification client
        try {
            $customerAccount = $customerAccountId ? $this->accountModel->find($customerAccountId) : null;
            if ($customerAccount) {
                $notif = new Notification();
                $notif->notify(
                    (int) $customerAccount['user_id'],
                    'card',
                    'Remboursement TPE',
                    sprintf(
                        'Un remboursement de %.2f %s a été effectué par la modération%s.',
                        $refundAmount,
                        $currency,
                        $reason !== '' ? ' (motif : ' . $reason . ')' : ''
                    ),
                    '/accounts/' . $customerAccountId
                );
            }
        } catch (\Throwable) {
            // best-effort
        }

        $this->setFlash('success', sprintf(
            'Remboursement de %.2f %s effectué pour le paiement TPE #%d.',
            $refundAmount,
            $currency,
            $paymentId
        ));
        $this->redirect('/moderation/pos-payments');
    }
}
