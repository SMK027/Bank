<?php

declare(strict_types=1);

/**
 * Point d'entrée de l'application (Front Controller).
 * Toutes les requêtes HTTP passent par ce fichier.
 */

// Charger l'autoloader Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Fuseau horaire par défaut
date_default_timezone_set('Europe/Paris');

use App\Core\Router;
use App\Core\Session;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\AccountController;
use App\Controllers\TransactionController;
use App\Controllers\VaultController;
use App\Controllers\AccessController;
use App\Controllers\TransferController;
use App\Controllers\ModerationController;
use App\Controllers\ProfileController;
use App\Controllers\TicketController;
use App\Controllers\NotificationController;
use App\Controllers\AuditLogController;
use App\Controllers\SavingsInterestController;
use App\Controllers\MessageController;
use App\Controllers\LoanController;
use App\Controllers\ModerationLoanController;
use App\Controllers\CardController;
use App\Controllers\PosController;
use App\Controllers\ApiClientController;
use App\Controllers\Api\PaymentApiController;
use App\Controllers\Api\MobileApiController;
use App\Controllers\BudgetController;
use App\Controllers\PaymentRequestController;
use App\Controllers\FriendController;
use App\Controllers\ExpenseSplitController;
use App\Controllers\CheckbookController;
use App\Controllers\ModerationOverdraftController;
use App\Controllers\SupervisorController;

// Démarrer la session
Session::start();

// ============================================================
// En-têtes de sécurité HTTP
// ============================================================
// Empêche le rendu de la page dans une iframe (clickjacking).
header('X-Frame-Options: DENY');
// Empêche le navigateur de "deviner" le type MIME.
header('X-Content-Type-Options: nosniff');
// Limite les informations envoyées dans le header Referer.
header('Referrer-Policy: strict-origin-when-cross-origin');
// Restreint les permissions navigateur sensibles.
// camera=(self) : autorise la caméra depuis cette origine uniquement (nécessaire pour le scanner QR du TPE).
header('Permissions-Policy: geolocation=(), microphone=(), camera=(self), payment=()');
// Politique de contenu : autorise self + CDN jsDelivr utilisé pour les icônes.
header(
    "Content-Security-Policy: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
    . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
    . "img-src 'self' data:; "
    . "font-src 'self' https://cdn.jsdelivr.net data:; "
    . "connect-src 'self'; "
    . "frame-ancestors 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self'"
);
// HSTS : forcer HTTPS si l'application est servie en HTTPS.
$isHttps = ($_SERVER['HTTPS'] ?? '') === 'on'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// ============================================================
// En-têtes CORS (à adapter selon les besoins)
// ============================================================
$allowedOrigin = getenv('APP_URL') ?: 'http://localhost:8080';
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ============================================================
// Initialiser le routeur
// ============================================================
$router = new Router();

// --- Routes publiques ---
$router->get('/', HomeController::class, 'index');

// --- Routes d'authentification ---
$router->get('/login', AuthController::class, 'loginForm');
$router->post('/login', AuthController::class, 'login');
$router->get('/login/pin', AuthController::class, 'loginPinForm');
$router->post('/login/pin', AuthController::class, 'loginPin');
$router->get('/register', AuthController::class, 'registerForm');
$router->post('/register', AuthController::class, 'register');
$router->get('/logout', AuthController::class, 'logout');
$router->get('/forgot-password', AuthController::class, 'forgotPasswordForm');
$router->post('/forgot-password', AuthController::class, 'forgotPassword');
$router->get('/reset-password/{token}', AuthController::class, 'resetPasswordForm');
$router->post('/reset-password/{token}', AuthController::class, 'resetPassword');

// --- Dashboard ---
$router->get('/dashboard', DashboardController::class, 'index');

// --- Profil ---
$router->get('/profile', ProfileController::class, 'index');
$router->post('/profile/password', ProfileController::class, 'updatePassword');
$router->get('/profile/birth-date', ProfileController::class, 'birthDateForm');
$router->post('/profile/birth-date', ProfileController::class, 'saveBirthDate');
$router->get('/profile/professional', ProfileController::class, 'professionalForm');
$router->post('/profile/professional', ProfileController::class, 'saveProfessional');
$router->post('/profile/professional/remove', ProfileController::class, 'removeProfessional');
$router->get('/profile/verify-siret', ProfileController::class, 'verifySiret');
$router->post('/profile/pin', ProfileController::class, 'savePin');
$router->get('/profile/pin/change',  ProfileController::class, 'pinChangeForm');
$router->post('/profile/pin/change', ProfileController::class, 'pinChange');
$router->post('/profile/account-number/reset', ProfileController::class, 'resetAccountNumber');
$router->get('/profile/qrcode', ProfileController::class, 'qrCode');

// --- Comptes bancaires ---
$router->get('/accounts/create', AccountController::class, 'createForm');
$router->post('/accounts/create', AccountController::class, 'create');
$router->get('/accounts/{id}', AccountController::class, 'show');
$router->get('/accounts/{id}/statement', AccountController::class, 'statementForm');
$router->get('/accounts/{id}/statement/pdf', AccountController::class, 'generateStatement');
$router->get('/accounts/{id}/edit', AccountController::class, 'editForm');
$router->post('/accounts/{id}/edit', AccountController::class, 'edit');
$router->post('/accounts/{id}/disable', AccountController::class, 'disableAccount');
$router->post('/accounts/{id}/enable', AccountController::class, 'enableAccount');
$router->post('/accounts/{id}/toggle-hidden', AccountController::class, 'toggleHidden');

// --- Transactions ---
$router->post('/accounts/{accountId}/transactions', TransactionController::class, 'create');
$router->post('/accounts/{accountId}/event-passive-income/pause', AccountController::class, 'pauseEventPassiveIncome');
$router->post('/accounts/{accountId}/event-passive-income/resume', AccountController::class, 'resumeEventPassiveIncome');
$router->post('/accounts/{accountId}/event-upgrades/buy', AccountController::class, 'buyEventUpgrade');
$router->post('/accounts/{accountId}/transactions/{transactionId}/edit', TransactionController::class, 'editTransaction');
$router->post('/accounts/{accountId}/transactions/{transactionId}/delete', TransactionController::class, 'deleteTransaction');
$router->post('/accounts/{accountId}/transactions/{transactionId}/toggle-budget-exclusion', TransactionController::class, 'toggleBudgetExclusion');

// --- Coffre d'entreprise (encaissements / décaissements) ---
$router->get('/accounts/{id}/vault',  VaultController::class, 'form');
$router->post('/accounts/{id}/vault', VaultController::class, 'operate');

// --- Débits différés ---
$router->post('/accounts/{accountId}/deferred-debits', TransactionController::class, 'createDeferredDebit');
$router->post('/accounts/{accountId}/deferred-debits/{debitId}/edit', TransactionController::class, 'editDeferredDebit');
$router->post('/accounts/{accountId}/deferred-debits/{debitId}/cancel', TransactionController::class, 'cancelDeferredDebit');

// --- Budgets mensuels ---
$router->get('/budget', BudgetController::class, 'index');
$router->post('/budget/save', BudgetController::class, 'save');

// --- Demandes d'argent ---
$router->get('/payment-requests', PaymentRequestController::class, 'index');
$router->post('/payment-requests/create', PaymentRequestController::class, 'create');
$router->post('/payment-requests/{id}/pay', PaymentRequestController::class, 'pay');
$router->post('/payment-requests/{id}/refuse', PaymentRequestController::class, 'refuse');
$router->post('/payment-requests/{id}/cancel', PaymentRequestController::class, 'cancel');
$router->get('/payment-requests/search-users', PaymentRequestController::class, 'searchUsers');

// --- Amis ---
$router->get('/friends', FriendController::class, 'index');
$router->post('/friends/send', FriendController::class, 'send');
$router->post('/friends/{id}/accept', FriendController::class, 'accept');
$router->post('/friends/{id}/refuse', FriendController::class, 'refuse');
$router->post('/friends/{id}/cancel', FriendController::class, 'cancel');
$router->post('/friends/{id}/remove', FriendController::class, 'remove');
$router->get('/friends/search-users', FriendController::class, 'searchUsers');
$router->get('/friends/list', FriendController::class, 'listFriends');

// --- Répartition de dépenses ---
$router->post('/accounts/{accountId}/transactions/{transactionId}/split', ExpenseSplitController::class, 'create');

// --- Virements ---
$router->get('/transfers/create', TransferController::class, 'createForm');
$router->post('/transfers/create', TransferController::class, 'create');
$router->get('/transfers/recurring', TransferController::class, 'listRecurring');
$router->post('/transfers/recurring/{id}/cancel', TransferController::class, 'cancelRecurring');

// --- Modération ---
$router->get('/moderation', ModerationController::class, 'index');
$router->get('/moderation/features', ModerationController::class, 'features');
$router->post('/moderation/features/{key}/toggle', ModerationController::class, 'toggleFeature');
$router->get('/moderation/events', ModerationController::class, 'events');
$router->post('/moderation/events', ModerationController::class, 'createEvent');
$router->post('/moderation/events/{id}/update', ModerationController::class, 'updateEvent');

// --- Superviseurs ---
$router->get('/moderation/supervisors',                 SupervisorController::class, 'index');
$router->get('/moderation/supervisors/create',          SupervisorController::class, 'create');
$router->post('/moderation/supervisors',                SupervisorController::class, 'store');
$router->post('/moderation/supervisors/{id}/toggle',    SupervisorController::class, 'toggleStatus');
$router->post('/moderation/supervisors/{id}/pin/reset', SupervisorController::class, 'resetPin');

// --- Bypass superviseur (accessible sans connexion) ---
$router->get('/supervisor/bypass',        SupervisorController::class, 'bypassForm');
$router->post('/supervisor/bypass',       SupervisorController::class, 'bypassAuthenticate');
$router->get('/supervisor/bypass/replay', SupervisorController::class, 'bypassReplay');
$router->get('/moderation/transfers', ModerationController::class, 'transfers');
$router->post('/moderation/transfers/{id}/cancel', ModerationController::class, 'cancelTransfer');
$router->get('/moderation/users', ModerationController::class, 'users');
$router->post('/moderation/users/{id}/role', ModerationController::class, 'setRole');
$router->post('/moderation/users/{id}/control/start', ModerationController::class, 'startUserControl');
$router->post('/moderation/users/control/stop', ModerationController::class, 'stopUserControl');
$router->post('/moderation/users/{id}/suspend', ModerationController::class, 'suspendUser');
$router->post('/moderation/users/{id}/ban', ModerationController::class, 'banUser');
$router->post('/moderation/users/{id}/activate', ModerationController::class, 'activateUser');
$router->post('/moderation/users/{id}/pin/reset', ModerationController::class, 'resetUserPin');
$router->post('/moderation/accounts/{id}/freeze', ModerationController::class, 'freeze');
$router->post('/moderation/accounts/{id}/unfreeze', ModerationController::class, 'unfreeze');
$router->post('/moderation/accounts/{id}/charge-agios', ModerationController::class, 'chargeAgios');
$router->post('/moderation/accounts/{id}/transactions', ModerationController::class, 'createModerationTransaction');
$router->get('/moderation/accounts/{id}/agios', ModerationController::class, 'agiosReport');
$router->post('/moderation/accounts/{id}/disable', ModerationController::class, 'disableAccount');
$router->post('/moderation/accounts/{id}/enable', ModerationController::class, 'enableAccount');
$router->post('/moderation/accounts/{id}/toggle-deferred-debit', ModerationController::class, 'toggleDeferredDebit');
$router->post('/moderation/accounts/force-closures', ModerationController::class, 'forceCloseAccounts');
$router->post('/moderation/deferred-debits/force-process', ModerationController::class, 'forceProcessDeferredDebits');
// Prélèvements
$router->get('/moderation/direct-debits', ModerationController::class, 'directDebits');
$router->get('/moderation/direct-debits/create', ModerationController::class, 'createDirectDebitForm');
$router->post('/moderation/direct-debits', ModerationController::class, 'createDirectDebit');
$router->post('/moderation/direct-debits/{id}/cancel', ModerationController::class, 'cancelDirectDebit');
$router->post('/moderation/direct-debits/{id}/reject', ModerationController::class, 'rejectDirectDebit');
$router->post('/moderation/direct-debits/{id}/retry', ModerationController::class, 'retryDirectDebit');
$router->post('/moderation/direct-debits/{id}/reactivate', ModerationController::class, 'reactivateDirectDebit');
$router->get('/moderation/direct-debits/accounts/search', ModerationController::class, 'searchAccounts');
$router->get('/moderation/users/search', ModerationController::class, 'searchUsers');
// Mandats professionnels
$router->get('/moderation/mandates', ModerationController::class, 'mandates');
$router->get('/moderation/mandates/create', ModerationController::class, 'createMandateForm');
$router->post('/moderation/mandates', ModerationController::class, 'createMandate');$router->post('/moderation/mandates/{id}/edit', ModerationController::class, 'editMandate');$router->post('/moderation/mandates/{id}/revoke', ModerationController::class, 'revokeMandate');
$router->post('/moderation/mandates/{id}/reschedule', ModerationController::class, 'rescheduleMandate');
$router->post('/moderation/direct-debits/{id}/reschedule', ModerationController::class, 'rescheduleDirectDebit');
$router->get('/moderation/recurring-transfers', ModerationController::class, 'recurringTransfers');
$router->post('/moderation/recurring-transfers/{id}/reschedule', ModerationController::class, 'rescheduleRecurringTransfer');
// Journal d'audit
$router->get('/moderation/audit-log', AuditLogController::class, 'index');
// Comptes mineurs & tutelles légales
$router->get('/moderation/accounts/create', ModerationController::class, 'createAccountForm');
$router->post('/moderation/accounts', ModerationController::class, 'createAccountForUser');
$router->get('/moderation/internal-accounts/create', ModerationController::class, 'createInternalAccountForm');
$router->post('/moderation/internal-accounts', ModerationController::class, 'createInternalAccount');
$router->post('/moderation/accounts/{id}/compute-interests', ModerationController::class, 'computeInternalInterests');
$router->get('/moderation/minor-accounts/create', ModerationController::class, 'createMinorAccountForm');
$router->post('/moderation/minor-accounts', ModerationController::class, 'createMinorAccount');
$router->get('/moderation/guardianships', ModerationController::class, 'guardianships');
$router->post('/moderation/guardianships/{minorId}/add', ModerationController::class, 'addGuardian');
$router->post('/moderation/guardianships/{id}/remove', ModerationController::class, 'removeGuardian');

// --- Partage d'accès ---
$router->post('/accounts/{accountId}/access', AccessController::class, 'grant');
$router->post('/accounts/{accountId}/access/{userId}/revoke', AccessController::class, 'revoke');

// --- Tickets (utilisateurs) ---
$router->get('/tickets', TicketController::class, 'index');
$router->get('/tickets/create', TicketController::class, 'createForm');
$router->post('/tickets', TicketController::class, 'store');
$router->get('/tickets/{id}', TicketController::class, 'show');
$router->post('/tickets/{id}/reply', TicketController::class, 'reply');
$router->post('/tickets/{id}/close', TicketController::class, 'close');

// --- Tickets (modération) ---
$router->get('/moderation/tickets', ModerationController::class, 'ticketIndex');
$router->get('/moderation/tickets/{id}', ModerationController::class, 'ticketShow');
$router->post('/moderation/tickets/{id}/reply', ModerationController::class, 'ticketReply');
$router->post('/moderation/tickets/{id}/status', ModerationController::class, 'ticketUpdateStatus');

// --- Messagerie interne ---
$router->get('/messages', MessageController::class, 'index');
$router->get('/messages/create', MessageController::class, 'createForm');
$router->post('/messages', MessageController::class, 'store');
$router->get('/messages/search-users', MessageController::class, 'searchUsers');
$router->get('/messages/{id}', MessageController::class, 'show');
$router->post('/messages/{id}/reply', MessageController::class, 'reply');
$router->post('/messages/{id}/close', MessageController::class, 'close');
$router->post('/messages/{id}/reopen', MessageController::class, 'reopen');
$router->post('/messages/{id}/add-participant', MessageController::class, 'addParticipant');

// --- Notifications ---
$router->get('/notifications', NotificationController::class, 'index');

// --- Intérêts épargne ---
$router->get('/interests', SavingsInterestController::class, 'index');
$router->get('/interests/{id}/confirm', SavingsInterestController::class, 'confirmForm');
$router->post('/interests/{id}/confirm', SavingsInterestController::class, 'confirm');

// --- Simulateur de crédits ---
$router->get('/loans/simulator', LoanController::class, 'simulatorForm');
$router->post('/loans/simulator', LoanController::class, 'simulate');

// --- Crédits utilisateur ---
$router->get('/loans', LoanController::class, 'myLoans');
$router->get('/loans/{id}', LoanController::class, 'show');
$router->post('/loans/{id}/accept', LoanController::class, 'accept');
$router->post('/loans/{id}/reject', LoanController::class, 'reject');

// --- Modération : crédits ---
$router->get('/moderation/loans', ModerationLoanController::class, 'index');
$router->get('/moderation/loans/create', ModerationLoanController::class, 'createForm');
$router->post('/moderation/loans', ModerationLoanController::class, 'create');
$router->get('/moderation/loans/{id}', ModerationLoanController::class, 'show');
$router->post('/moderation/loans/{id}/rate', ModerationLoanController::class, 'updateRate');
$router->post('/moderation/loans/{id}/installments', ModerationLoanController::class, 'addInstallment');
$router->post('/moderation/loans/{id}/installments/{iid}/cancel', ModerationLoanController::class, 'cancelInstallment');
$router->post('/moderation/loans/{id}/installments/{iid}/reschedule', ModerationLoanController::class, 'rescheduleInstallment');
$router->post('/moderation/loans/{id}/installments/{iid}/penalty', ModerationLoanController::class, 'updatePenalty');
$router->post('/moderation/loans/{id}/installments/{iid}/refund', ModerationLoanController::class, 'refundInstallment');
$router->post('/moderation/loans/{id}/cancel', ModerationLoanController::class, 'cancelLoan');
$router->post('/moderation/loans/{id}/reassign', ModerationLoanController::class, 'reassign');
$router->post('/moderation/loans/process-installments', ModerationLoanController::class, 'processInstallments');

// --- Modération : taux d'intérêt épargne ---
$router->get('/moderation/savings-rate', ModerationController::class, 'savingsRate');
$router->post('/moderation/savings-rate', ModerationController::class, 'setSavingsRate');
$router->get('/notifications/count', NotificationController::class, 'unreadCount');
$router->post('/notifications/read-all', NotificationController::class, 'markAllRead');
$router->post('/notifications/delete-read', NotificationController::class, 'deleteRead');
$router->post('/notifications/{id}/read', NotificationController::class, 'markRead');
$router->post('/notifications/{id}/delete', NotificationController::class, 'delete');

// --- Cartes bancaires (utilisateur) ---
$router->get('/cards', CardController::class, 'index');
$router->get('/cards/create', CardController::class, 'createForm');
$router->post('/cards', CardController::class, 'create');
$router->post('/cards/{id}/account',   CardController::class, 'updateAccount');
$router->post('/cards/{id}/settings',  CardController::class, 'updateSettings');
$router->post('/cards/{id}/delete',    CardController::class, 'delete');
$router->post('/cards/{id}/toggle',           CardController::class, 'toggleStatus');
$router->post('/cards/{id}/override-spent',   CardController::class, 'overrideMonthlySpent');
$router->post('/cards/{id}/reset-spent',      CardController::class, 'resetMonthlySpent');
$router->get('/cards/{id}/reveal', CardController::class, 'revealForm');
$router->post('/cards/{id}/reveal', CardController::class, 'reveal');
$router->post('/cards/{id}/reveal-qr', CardController::class, 'revealQr');

// --- Clients API (modération) ---
$router->get('/moderation/api-clients', ApiClientController::class, 'index');
$router->post('/moderation/api-clients', ApiClientController::class, 'create');
$router->post('/moderation/api-clients/{id}/revoke', ApiClientController::class, 'revoke');

// --- API REST de paiement par carte ---
$router->post('/api/v1/payments/debit',  PaymentApiController::class, 'debit');
$router->post('/api/v1/payments/credit', PaymentApiController::class, 'credit');
$router->post('/api/v1/cards/verify',    PaymentApiController::class, 'verify');

// --- API mobile (app Expo TPE) ---
$router->post('/api/v1/mobile/login',       MobileApiController::class, 'login');
$router->get( '/api/v1/mobile/status',      MobileApiController::class, 'status');
$router->post('/api/v1/mobile/transaction', MobileApiController::class, 'transaction');

// --- Terminal de paiement électronique (TPE) ---
$router->get('/pos',             PosController::class, 'index');
$router->post('/pos/charge',     PosController::class, 'charge');
$router->get('/pos/verify-card', PosController::class, 'verifyCard');
$router->get('/moderation/pos-payments',                      PosController::class, 'moderationIndex');
$router->post('/moderation/pos-payments/disable',             PosController::class, 'moderationDisable');
$router->post('/moderation/pos-payments/enable',              PosController::class, 'moderationEnable');
$router->get('/moderation/pos-payments/merchants/search',     PosController::class, 'moderationMerchantSearch');
$router->post('/moderation/pos-payments/user/{id}/suspend',   PosController::class, 'moderationMerchantSuspend');
$router->post('/moderation/pos-payments/user/{id}/resume',    PosController::class, 'moderationMerchantResume');
$router->post('/moderation/pos-payments/{id}/cancel',         PosController::class, 'moderationCancel');
$router->post('/moderation/pos-payments/{id}/refund',         PosController::class, 'moderationRefund');

// --- Autorisations de dépassement de découvert ---
$router->get('/moderation/overdraft-authorizations',                       ModerationOverdraftController::class, 'index');
$router->post('/moderation/overdraft-authorizations/create',               ModerationOverdraftController::class, 'create');
$router->post('/moderation/overdraft-authorizations/{id}/revoke',          ModerationOverdraftController::class, 'revoke');

// --- Chéquiers ---
$router->get('/checkbooks',                                                CheckbookController::class, 'index');
$router->get('/checkbooks/create',                                         CheckbookController::class, 'createForm');
$router->post('/checkbooks/create',                                        CheckbookController::class, 'create');
$router->get('/checkbooks/{id}',                                           CheckbookController::class, 'show');
$router->post('/checkbooks/{id}/oppose',                                   CheckbookController::class, 'oppose');
$router->post('/checkbooks/{checkbookId}/checks/{checkId}/confirm',        CheckbookController::class, 'confirmCheck');
$router->post('/checkbooks/{checkbookId}/checks/{checkId}/oppose',         CheckbookController::class, 'opposeCheck');
// Actions rapides depuis la page d'un compte
$router->post('/accounts/{accountId}/checks/{checkId}/confirm',            CheckbookController::class, 'confirmCheckFromAccount');
$router->post('/accounts/{accountId}/checks/{checkId}/oppose',             CheckbookController::class, 'opposeCheckFromAccount');

// Dispatcher la requête
$router->dispatch();
