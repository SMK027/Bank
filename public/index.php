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

// --- Partage d'accès ---
$router->post('/accounts/{accountId}/access', AccessController::class, 'grant');
$router->post('/accounts/{accountId}/access/{userId}/revoke', AccessController::class, 'revoke');

// Dispatcher la requête
$router->dispatch();
