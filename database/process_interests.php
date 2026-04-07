<?php

declare(strict_types=1);

/**
 * Script cron : calcul des intérêts annuels sur les comptes éligibles.
 * Planification recommandée : 0 0 1 1 * (1er janvier à minuit)
 *
 * Le taux utilisé est celui défini par l'utilisateur sur son compte (interest_rate).
 * Le taux de modération sert uniquement de plafond lors de la saisie.
 *
 * Usage : php /var/www/html/database/process_interests.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set('Europe/Paris');

use App\Models\Account;
use App\Models\Guardianship;
use App\Models\Notification;
use App\Models\SavingsInterest;
use App\Models\Transaction;

$accountModel      = new Account();
$interestModel     = new SavingsInterest();
$txModel           = new Transaction();
$notifModel        = new Notification();
$guardianshipModel = new Guardianship();

// Année dont on calcule les intérêts (celle qui vient de se terminer)
$year = (int) date('Y') - 1;

// Récupérer tous les comptes éligibles aux intérêts
$eligibleTypes = Account::getInterestEligibleTypes();
$savings       = [];
foreach ($eligibleTypes as $accountType) {
    foreach ($accountModel->findBy(['type' => $accountType]) as $acc) {
        $savings[] = $acc;
    }
}

echo sprintf("[%s] Calcul des intérêts %d — %d compte(s) éligible(s) trouvé(s).\n",
    date('Y-m-d H:i:s'), $year, count($savings));

$processed = 0;
$skipped   = 0;
$errors    = 0;

foreach ($savings as $account) {
    $accountId   = (int) $account['id'];
    $accountType = $account['type'];

    // Taux propre au compte — si non défini ou nul, on ignore ce compte
    $accountRate = isset($account['interest_rate']) && $account['interest_rate'] !== null
        ? (float) $account['interest_rate']
        : null;

    if ($accountRate === null || $accountRate <= 0) {
        echo sprintf("[%s] Compte #%d (%s) : aucun taux d'intérêt défini, ignoré.\n",
            date('Y-m-d H:i:s'), $accountId, $account['name']);
        $skipped++;
        continue;
    }

    if ($interestModel->existsForAccountYear($accountId, $year)) {
        echo sprintf("[%s] Compte #%d (%s) : intérêts %d déjà enregistrés, ignoré.\n",
            date('Y-m-d H:i:s'), $accountId, $account['name'], $year);
        $skipped++;
        continue;
    }

    try {
        // Calcul au prorata temporis (TWAB) avec le taux propre au compte
        $calculatedAmount = SavingsInterest::calculateProrata($accountId, $year, $accountRate, $txModel);

        // Solde au moment du calcul (avant versement)
        $balanceBefore = $accountModel->getBalance($accountId);

        // Maximum théorique (borné par le plafond si présent)
        $cap = Account::typeHasCap($accountType) && ($account['cap'] ?? 0) > 0
            ? (float) $account['cap']
            : null;
        $maxAmount = SavingsInterest::computeMaxAmount($balanceBefore, $accountRate, $cap);

        // On ne dépasse pas le maximum théorique
        $calculatedAmount = min($calculatedAmount, $maxAmount);

        if ($calculatedAmount <= 0) {
            echo sprintf("[%s] Compte #%d (%s) : montant nul ou négatif (solde : %s), ignoré.\n",
                date('Y-m-d H:i:s'), $accountId, $account['name'],
                number_format($balanceBefore, 2, ',', ' '));
            $skipped++;
            continue;
        }

        // Créer l'entrée en attente de confirmation utilisateur
        $interestModel->create([
            'account_id'        => $accountId,
            'account_type'      => $accountType,
            'year'              => $year,
            'rate'              => $accountRate,
            'calculated_amount' => $calculatedAmount,
            'max_amount'        => $maxAmount,
            'status'            => SavingsInterest::STATUS_PENDING,
        ]);

        // Notifier le propriétaire (et ses tuteurs légaux si mineur)
        $userId = (int) $account['user_id'];
        $title  = sprintf('Intérêts %d — %s', $year, $account['name']);
        $body   = sprintf(
            'Vos intérêts pour %d sur le compte « %s » ont été calculés : %s %s (taux : %s %%). '
            . 'Rendez-vous dans « Mes intérêts » pour les confirmer.',
            $year,
            $account['name'],
            number_format($calculatedAmount, 2, ',', ' '),
            $account['currency'],
            number_format($accountRate * 100, 2, ',', ' ')
        );
        $link = '/interests';

        $notifModel->notify($userId, 'interest_pending', $title, $body, $link);
        foreach ($guardianshipModel->getGuardiansOf($userId) as $g) {
            $notifModel->notify((int) $g['guardian_user_id'], 'interest_pending', $title, $body, $link);
        }

        echo sprintf("[%s] Compte #%d (%s) : %s %s intérêts %d (taux %s %%) en attente.\n",
            date('Y-m-d H:i:s'),
            $accountId,
            $account['name'],
            number_format($calculatedAmount, 2, ',', ' '),
            $account['currency'],
            $year,
            number_format($accountRate * 100, 2, ',', ' ')
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
