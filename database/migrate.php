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

    try {
        // Exécuter chaque instruction séparément (PDO::exec ne supporte pas
        // plusieurs requêtes en mode mysql strict)
        $pdo->exec($sql);
        echo "OK\n";
    } catch (\PDOException $e) {
        // Colonne / index / table déjà existant(e) : on passe sans bloquer
        $code = (int) $e->getCode();
        if (in_array($code, [1060, 1061, 1050], true)) {
            echo "SKIP (déjà appliquée : " . $e->getMessage() . ")\n";
        } else {
            echo "ERREUR\n";
            throw $e;
        }
    }
}

echo "\nToutes les migrations ont été appliquées.\n";
