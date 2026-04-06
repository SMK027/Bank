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
use App\Controllers\AccessController;
use App\Controllers\TransferController;
use App\Controllers\ModerationController;
use App\Controllers\ProfileController;
use App\Controllers\TicketController;
use App\Controllers\NotificationController;

// Démarrer la session
Session::start();

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
$router->get('/register', AuthController::class, 'registerForm');
$router->post('/register', AuthController::class, 'register');
$router->get('/logout', AuthController::class, 'logout');

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

// --- Comptes bancaires ---
$router->get('/accounts/create', AccountController::class, 'createForm');
$router->post('/accounts/create', AccountController::class, 'create');
$router->get('/accounts/{id}', AccountController::class, 'show');
$router->get('/accounts/{id}/edit', AccountController::class, 'editForm');
$router->post('/accounts/{id}/edit', AccountController::class, 'edit');
$router->post('/accounts/{id}/delete', AccountController::class, 'deleteAccount');

// --- Transactions ---
$router->post('/accounts/{accountId}/transactions', TransactionController::class, 'create');
$router->post('/accounts/{accountId}/transactions/{transactionId}/delete', TransactionController::class, 'deleteTransaction');

// --- Virements ---
$router->get('/transfers/create', TransferController::class, 'createForm');
$router->post('/transfers/create', TransferController::class, 'create');
$router->get('/transfers/recurring', TransferController::class, 'listRecurring');
$router->post('/transfers/recurring/{id}/cancel', TransferController::class, 'cancelRecurring');

// --- Modération ---
$router->get('/moderation', ModerationController::class, 'index');
$router->get('/moderation/transfers', ModerationController::class, 'transfers');
$router->post('/moderation/transfers/{id}/cancel', ModerationController::class, 'cancelTransfer');
$router->get('/moderation/users', ModerationController::class, 'users');
$router->post('/moderation/users/{id}/role', ModerationController::class, 'setRole');
$router->post('/moderation/users/{id}/suspend', ModerationController::class, 'suspendUser');
$router->post('/moderation/users/{id}/ban', ModerationController::class, 'banUser');
$router->post('/moderation/users/{id}/activate', ModerationController::class, 'activateUser');
$router->post('/moderation/accounts/{id}/freeze', ModerationController::class, 'freeze');
$router->post('/moderation/accounts/{id}/unfreeze', ModerationController::class, 'unfreeze');
// Prélèvements
$router->get('/moderation/direct-debits', ModerationController::class, 'directDebits');
$router->get('/moderation/direct-debits/create', ModerationController::class, 'createDirectDebitForm');
$router->post('/moderation/direct-debits', ModerationController::class, 'createDirectDebit');
$router->post('/moderation/direct-debits/{id}/cancel', ModerationController::class, 'cancelDirectDebit');
$router->post('/moderation/direct-debits/{id}/reject', ModerationController::class, 'rejectDirectDebit');
$router->post('/moderation/direct-debits/{id}/retry', ModerationController::class, 'retryDirectDebit');
$router->get('/moderation/direct-debits/accounts/search', ModerationController::class, 'searchAccounts');
$router->get('/moderation/users/search', ModerationController::class, 'searchUsers');
// Mandats professionnels
$router->get('/moderation/mandates', ModerationController::class, 'mandates');
$router->get('/moderation/mandates/create', ModerationController::class, 'createMandateForm');
$router->post('/moderation/mandates', ModerationController::class, 'createMandate');
$router->post('/moderation/mandates/{id}/revoke', ModerationController::class, 'revokeMandate');
// Comptes mineurs & tutelles légales
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

// --- Notifications ---
$router->get('/notifications', NotificationController::class, 'index');
$router->get('/notifications/count', NotificationController::class, 'unreadCount');
$router->post('/notifications/read-all', NotificationController::class, 'markAllRead');
$router->post('/notifications/delete-read', NotificationController::class, 'deleteRead');
$router->post('/notifications/{id}/read', NotificationController::class, 'markRead');
$router->post('/notifications/{id}/delete', NotificationController::class, 'delete');

// Dispatcher la requête
$router->dispatch();
