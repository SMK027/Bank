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

// --- Modération ---
$router->get('/moderation', ModerationController::class, 'index');
$router->get('/moderation/transfers', ModerationController::class, 'transfers');
$router->post('/moderation/transfers/{id}/cancel', ModerationController::class, 'cancelTransfer');
$router->get('/moderation/users', ModerationController::class, 'users');
$router->post('/moderation/users/{id}/role', ModerationController::class, 'setRole');
$router->post('/moderation/accounts/{id}/freeze', ModerationController::class, 'freeze');
$router->post('/moderation/accounts/{id}/unfreeze', ModerationController::class, 'unfreeze');
// Prélèvements
$router->get('/moderation/direct-debits', ModerationController::class, 'directDebits');
$router->get('/moderation/direct-debits/create', ModerationController::class, 'createDirectDebitForm');
$router->post('/moderation/direct-debits', ModerationController::class, 'createDirectDebit');
$router->post('/moderation/direct-debits/{id}/cancel', ModerationController::class, 'cancelDirectDebit');
$router->post('/moderation/direct-debits/{id}/reject', ModerationController::class, 'rejectDirectDebit');
$router->get('/moderation/direct-debits/accounts/search', ModerationController::class, 'searchAccounts');

// --- Partage d'accès ---
$router->post('/accounts/{accountId}/access', AccessController::class, 'grant');
$router->post('/accounts/{accountId}/access/{userId}/revoke', AccessController::class, 'revoke');

// Dispatcher la requête
$router->dispatch();
