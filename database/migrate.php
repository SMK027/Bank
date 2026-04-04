<?php

declare(strict_types=1);

/**
 * Script de migration automatique.
 * Scanne les fichiers SQL dans database/migrations/ et les exécute dans l'ordre.
 * Garde une trace des migrations déjà appliquées dans la table `migrations`.
 */

// Charger les variables d'environnement
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'app_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

// Connexion avec retry (utile au démarrage Docker)
$maxRetries = 30;
$pdo = null;

for ($i = 1; $i <= $maxRetries; $i++) {
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "[migrate] Connexion à la base de données réussie.\n";
        break;
    } catch (PDOException $e) {
        echo "[migrate] Tentative {$i}/{$maxRetries} — Attente de la BDD...\n";
        if ($i === $maxRetries) {
            echo "[migrate] ERREUR : Impossible de se connecter à la base de données.\n";
            echo "[migrate] " . $e->getMessage() . "\n";
            exit(1);
        }
        sleep(2);
    }
}

// Créer la table de suivi des migrations
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Récupérer les migrations déjà appliquées
$applied = $pdo->query("SELECT filename FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

// Scanner les fichiers de migration
$migrationsDir = __DIR__ . '/migrations';
if (!is_dir($migrationsDir)) {
    echo "[migrate] Aucun dossier de migrations trouvé.\n";
    exit(0);
}

$files = glob($migrationsDir . '/*.sql');
sort($files);

$count = 0;

foreach ($files as $file) {
    $filename = basename($file);

    if (in_array($filename, $applied, true)) {
        continue;
    }

    echo "[migrate] Application de {$filename}...\n";
    $sql = file_get_contents($file);

    try {
        $pdo->exec($sql);
        $pdo->prepare("INSERT INTO migrations (filename) VALUES (:f)")->execute(['f' => $filename]);
        echo "[migrate] ✓ {$filename} appliquée.\n";
        $count++;
    } catch (PDOException $e) {
        $code = (int) $e->getCode();
        // Erreurs idempotentes MySQL (table/colonne/index déjà existant)
        if (in_array($code, [1050, 1060, 1061, 1062, 1068], true)) {
            echo "[migrate] ⚠ {$filename} — déjà appliquée (erreur idempotente {$code}).\n";
            $pdo->prepare("INSERT IGNORE INTO migrations (filename) VALUES (:f)")->execute(['f' => $filename]);
        } else {
            echo "[migrate] ✗ ERREUR sur {$filename} : " . $e->getMessage() . "\n";
            exit(1);
        }
    }
}

echo "[migrate] Terminé — {$count} migration(s) appliquée(s).\n";
