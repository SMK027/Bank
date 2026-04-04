<?php

declare(strict_types=1);

/**
 * Runner de migrations SQL.
 * Lance toutes les migrations du dossier database/migrations/ par ordre alphabétique.
 *
 * Usage : php database/migrate.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;

$pdo = Database::getInstance();

$migrationDir = __DIR__ . '/migrations';
$files        = glob($migrationDir . '/*.sql');
sort($files);

if (empty($files)) {
    echo "Aucune migration trouvée dans {$migrationDir}\n";
    exit(0);
}

foreach ($files as $file) {
    $name = basename($file);
    echo "→ Migration : {$name} … ";

    $sql = file_get_contents($file);

    // Exécuter chaque instruction séparément (PDO::exec ne supporte pas
    // plusieurs requêtes en mode mysql strict)
    $pdo->exec($sql);

    echo "OK\n";
}

echo "\nToutes les migrations ont été appliquées.\n";
