<?php

/**
 * Bootstrap PHPUnit : charge l'autoloader Composer
 * et prépare l'environnement minimal pour les tests.
 * Les modèles utilisent une base SQLite en mémoire (via TestDatabase::make()).
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Variables d'environnement pour les tests
putenv('APP_KEY=test_secret_key_for_jwt_at_least_32_characters_long');
putenv('APP_URL=http://localhost:8080');
putenv('APP_DEBUG=true');
