<?php

declare(strict_types=1);

/**
 * Script cron : calcul des intérêts annuels sur les comptes épargne.
 * Planification recommandée : 0 0 1 1 * (1er janvier à minuit)
 *
 * Usage : php /var/www/html/database/process_interests.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Europe/Paris');

use App\Models\Account;
use App\Models\Guardianship;
use App\Models\Notification;
use App\Models\SavingsInterest;
use App\Models\SavingsRate;
use App\Models\Transaction;

$accountModel      = new Account();
$rateModel         = new SavingsRate();
$interestModel     = new SavingsInterest();
$txModel           = new Transaction();
$notifModel        = new Notification();
$guardianshipModel = new Guardianship();

// Année dont on calcule les intérêts (celle qui vient de se terminer)
$year = (int) date('Y') - 1;

// Construire la map des taux par type : ['savings' => 0.03, 'online' => 0.015, …]
$eligibleTypes = Account::getInterestEligibleTypes();
$ratesByType   = [];
foreach ($eligibleTypes as $t) {
    $r = $rateModel->getCurrentRate($t);
    if ($r !== null) {
        $ratesByType[$t] = $r;
    }
}

if (empty($ratesByType)) {
    echo sprintf("[%s] Aucun taux d'intérêt configuré pour aucun type éligible. Arrêt.\n", date('Y-m-d H:i:s'));
    exit(0);
}

echo sprintf("[%s] Calcul des intérêts %d — types configurés : %s\n",
    date('Y-m-d H:i:s'),
    $year,
    implode(', ', array_map(
        fn(string $t, float $r) => sprintf('%s=%.2f%%', $t, $r * 100),
        array_keys($ratesByType),
        $ratesByType
    ))
);

$savings   = [];
foreach (array_keys($ratesByType) as $accountType) {
    foreach ($accountModel->findBy(['type' => $accountType]) as $acc) {
        $acc['_type_rate'] = $ratesByType[$accountType];
        $savings[] = $acc;
    }
}
$processed = 0;
$skipped   = 0;
$errors    = 0;

foreach ($savings as $account) {
    $accountId   = (int) $account['id'];
    $currentRate = (float) $account['_type_rate'];
    $accountType = $account['type'];

    if ($interestModel->existsForAccountYear($accountId, $year)) {
        echo sprintf("[%s] Compte #%d (%s) : intérêts %d déjà enregistrés, ignoré.\n",
            date('Y-m-d H:i:s'), $accountId, $account['name'], $year);
        $skipped++;
        continue;
    }

    try {
        // Calcul au prorata temporis (TWAB)
        $calculatedAmount = SavingsInterest::calculateProrata($accountId, $year, $currentRate, $txModel);

        // Solde au moment du calcul (avant versement)
        $balanceBefore = $accountModel->getBalance($accountId);

        // Maximum théorique (borné par le plafond si présent)
        $cap = Account::typeHasCap($accountType) && ($account['cap'] ?? 0) > 0
            ? (float) $account['cap']
            : null;
        $maxAmount = SavingsInterest::computeMaxAmount($balanceBefore, $currentRate, $cap);

        // On ne dépasse pas le maximum théorique
        $calculatedAmount = min($calculatedAmount, $maxAmount);

        if ($calculatedAmount <= 0) {
            echo sprintf("[%s] Compte #%d (%s) : montant nul ou négatif, ignoré.\n",
                date('Y-m-d H:i:s'), $accountId, $account['name']);
            $skipped++;
            continue;
        }

        // Créer l'entrée en attente de confirmation utilisateur
        $interestModel->create([
            'account_id'        => $accountId,
            'account_type'      => $accountType,
            'year'              => $year,
            'rate'              => $currentRate,
            'calculated_amount' => $calculatedAmount,
            'max_amount'        => $maxAmount,
            'status'            => SavingsInterest::STATUS_PENDING,
        ]);

        // Notifier le propriétaire du compte (et ses tuteurs légaux si mineur)
        $userId = (int) $account['user_id'];
        $title  = sprintf('Intérêts %d — %s', $year, $account['name']);
        $body   = sprintf(
            'Vos intérêts pour %d sur le compte « %s » ont été calculés : %s %s. '
            . 'Rendez-vous dans « Mes intérêts » pour les confirmer.',
            $year,
            $account['name'],
            number_format($calculatedAmount, 2, ',', ' '),
            $account['currency']
        );
        $link = '/interests';

        $notifModel->notify($userId, 'interest_pending', $title, $body, $link);
        foreach ($guardianshipModel->getGuardiansOf($userId) as $g) {
            $notifModel->notify((int) $g['guardian_user_id'], 'interest_pending', $title, $body, $link);
        }

        echo sprintf("[%s] Compte #%d (%s) : %s %s intérêts %d en attente.\n",
            date('Y-m-d H:i:s'),
            $accountId,
            $account['name'],
            number_format($calculatedAmount, 2, ',', ' '),
            $account['currency'],
            $year
        );
        $processed++;

    } catch (\Throwable $e) {
        echo sprintf("[%s] ERREUR compte #%d (%s) : %s\n",
            date('Y-m-d H:i:s'), $accountId, $account['name'], $e->getMessage());
        $errors++;
    }
}

echo sprintf(
    "\n[%s] Terminé — %d traité(s), %d ignoré(s), %d erreur(s).\n",
    date('Y-m-d H:i:s'),
    $processed,
    $skipped,
    $errors
);
