<?php

declare(strict_types=1);

/**
 * Script CLI : création de comptes de connexion par défaut pour le développement.
 *
 * Usage : php database/seed_dev.php
 * (ou : docker compose -f docker-compose.dev.yml exec app php database/seed_dev.php)
 *
 * N'insère rien si les comptes existent déjà (ré-exécution sans danger).
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Models\Supervisor;
use App\Models\User;

$userModel       = new User();
$supervisorModel = new Supervisor();

$defaults = [
    'admin' => [
        'username'   => 'admin',
        'email'      => 'admin@bankapp.local',
        'password'   => 'Admin1234!',
        'birth_date' => '1990-01-01',
        'role'       => 'moderator',
        'pin'        => '000000',
    ],
    'user' => [
        'username'   => 'demo',
        'email'      => 'demo@bankapp.local',
        'password'   => 'Demo1234!',
        'birth_date' => '1995-06-15',
        'role'       => 'user',
        'pin'        => '123456',
    ],
];

$createdUserIds = [];

foreach ($defaults as $key => $def) {
    $existing = $userModel->findByEmail($def['email']);
    if ($existing) {
        echo "- Utilisateur « {$def['username']} » existe déjà (id {$existing['id']}), inchangé.\n";
        $createdUserIds[$key] = (int) $existing['id'];
        continue;
    }

    $userId = $userModel->register($def['username'], $def['email'], $def['password'], $def['birth_date']);
    $userModel->setPin($userId, $def['pin']);
    if ($def['role'] !== 'user') {
        $userModel->updateRole($userId, $def['role']);
    }
    $createdUserIds[$key] = $userId;
    echo "+ Utilisateur « {$def['username']} » créé (id {$userId}, rôle {$def['role']}).\n";
}

$supervisorDef = [
    'first_name'    => 'Super',
    'last_name'     => 'Viseur',
    'supervisor_id' => 'SUPERVISOR01',
    'pin'           => '135790',
];

if ($supervisorModel->findBySupervisorId($supervisorDef['supervisor_id']) === null) {
    $supervisorModel->createSupervisor(
        $supervisorDef['first_name'],
        $supervisorDef['last_name'],
        $supervisorDef['supervisor_id'],
        $supervisorDef['pin'],
        $createdUserIds['admin']
    );
    echo "+ Superviseur « {$supervisorDef['supervisor_id']} » créé.\n";
} else {
    echo "- Superviseur « {$supervisorDef['supervisor_id']} » existe déjà, inchangé.\n";
}

echo "\n=== Identifiants de connexion par défaut (dev uniquement) ===\n";
echo "Admin (modérateur)   : {$defaults['admin']['email']} / {$defaults['admin']['password']}  (PIN {$defaults['admin']['pin']})\n";
echo "Utilisateur standard : {$defaults['user']['email']} / {$defaults['user']['password']}  (PIN {$defaults['user']['pin']})\n";
echo "Superviseur          : {$supervisorDef['supervisor_id']} / PIN {$supervisorDef['pin']}\n";
