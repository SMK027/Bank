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
$router->get('/legal', HomeController::class, 'legal');

// --- Routes d'authentification ---
$router->get('/login', AuthController::class, 'loginForm');
$router->post('/login', AuthController::class, 'login');
$router->get('/register', AuthController::class, 'registerForm');
$router->post('/register', AuthController::class, 'register');
$router->get('/logout', AuthController::class, 'logout');

// ============================================================
// Ajoutez vos routes ici
// ============================================================
// $router->get('/dashboard', DashboardController::class, 'index');
// $router->get('/items', ItemController::class, 'index');
// $router->get('/items/{id}', ItemController::class, 'show');
// $router->post('/items/create', ItemController::class, 'create');
// $router->post('/items/{id}/edit', ItemController::class, 'update');
// $router->post('/items/{id}/delete', ItemController::class, 'delete');

// --- Routes API (exemple) ---
// $router->post('/api/login', AuthApiController::class, 'login');
// $router->get('/api/items', ItemApiController::class, 'index');

// Dispatcher la requête
$router->dispatch();
