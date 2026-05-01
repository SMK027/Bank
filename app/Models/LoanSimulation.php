<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Simulateur de crédits.
 *
 * Définit les types de crédits disponibles avec leurs taux annuels,
 * calcule les mensualités et conserve l'historique des simulations.
 *
 * Formule de mensualité (amortissement constant) :
 *   M = P × r × (1+r)^n / ((1+r)^n − 1)
 *
 *   P = capital emprunté
 *   r = taux mensuel (taux annuel / 12 / 100)
 *   n = nombre de mensualités
 */
class LoanSimulation extends Model
{
    protected string $table      = 'loan_simulations';
    protected bool   $hasUpdatedAt = false;

    // ── Référentiel des types de crédits ─────────────────────────────────────

    /**
     * Retourne les types de crédits disponibles avec leurs paramètres.
     *
     * @return array<string, array{label: string, rate: float, min_months: int, max_months: int, min_amount: float, max_amount: float}>
     */
    public static function getTypes(): array
    {
        return [
            'personal' => [
                'label'      => 'Crédit personnel',
                'rate'       => 5.50,
                'min_months' => 6,
                'max_months' => 84,
                'min_amount' => 500.00,
                'max_amount' => 75_000.00,
                'icon'       => 'bi-person-fill',
                'description' => 'Financement de projets personnels (travaux, voyage, mariage…)',
            ],
            'auto' => [
                'label'      => 'Crédit auto',
                'rate'       => 3.50,
                'min_months' => 12,
                'max_months' => 84,
                'min_amount' => 2_000.00,
                'max_amount' => 100_000.00,
                'icon'       => 'bi-car-front-fill',
                'description' => 'Achat d\'un véhicule neuf ou d\'occasion',
            ],
            'mortgage' => [
                'label'      => 'Crédit immobilier',
                'rate'       => 2.50,
                'min_months' => 60,
                'max_months' => 300,
                'min_amount' => 10_000.00,
                'max_amount' => 1_000_000.00,
                'icon'       => 'bi-house-fill',
                'description' => 'Acquisition ou construction d\'un bien immobilier',
            ],
            'consumer' => [
                'label'      => 'Crédit consommation',
                'rate'       => 10.00,
                'min_months' => 3,
                'max_months' => 60,
                'min_amount' => 200.00,
                'max_amount' => 40_000.00,
                'icon'       => 'bi-bag-fill',
                'description' => 'Financement d\'achats courants, électroménager, etc.',
            ],
            'student' => [
                'label'      => 'Crédit étudiant',
                'rate'       => 2.00,
                'min_months' => 12,
                'max_months' => 120,
                'min_amount' => 500.00,
                'max_amount' => 50_000.00,
                'icon'       => 'bi-mortarboard-fill',
                'description' => 'Financement des études supérieures et frais associés',
            ],
        ];
    }

    // ── Calcul ────────────────────────────────────────────────────────────────

    /**
     * Calcule les détails d'un remboursement par mensualités constantes.
     *
     * @param float $amount      Capital emprunté (€)
     * @param int   $months      Durée en mois
     * @param float $annualRate  Taux annuel en % (ex : 5.50 pour 5,50 %)
     *
     * @return array{
     *     monthly_payment: float,
     *     total_cost: float,
     *     total_interest: float,
     *     amortization: array<int, array{month: int, payment: float, principal: float, interest: float, remaining: float}>
     * }
     */
    public static function calculate(float $amount, int $months, float $annualRate): array
    {
        $monthlyRate = $annualRate / 100 / 12;

        if ($monthlyRate == 0.0) {
            $monthly = $amount / $months;
        } else {
            $factor  = (1 + $monthlyRate) ** $months;
            $monthly = $amount * ($monthlyRate * $factor) / ($factor - 1);
        }

        $monthly      = round($monthly, 2);
        $totalCost    = round($monthly * $months, 2);
        $totalInterest = round($totalCost - $amount, 2);

        // Tableau d'amortissement
        $amortization = [];
        $remaining    = $amount;

        for ($i = 1; $i <= $months; $i++) {
            $interestPart  = round($remaining * $monthlyRate, 2);
            $principalPart = round($monthly - $interestPart, 2);
            $remaining     = round($remaining - $principalPart, 2);

            // Correction d'arrondi sur la dernière échéance
            if ($i === $months && abs($remaining) < 1.0) {
                $principalPart += $remaining;
                $remaining      = 0.0;
            }

            $amortization[] = [
                'month'     => $i,
                'payment'   => $monthly,
                'principal' => $principalPart,
                'interest'  => $interestPart,
                'remaining' => max(0.0, $remaining),
            ];
        }

        return [
            'monthly_payment' => $monthly,
            'total_cost'      => $totalCost,
            'total_interest'  => $totalInterest,
            'amortization'    => $amortization,
        ];
    }

    // ── Persistance ───────────────────────────────────────────────────────────

    /**
     * Enregistre une simulation pour un utilisateur.
     */
    public function saveSimulation(
        int    $userId,
        string $loanType,
        float  $amount,
        int    $months,
        float  $annualRate,
        float  $monthlyPayment,
        float  $totalCost,
        float  $totalInterest
    ): int {
        $stmt = $this->getPdo()->prepare(
            'INSERT INTO `loan_simulations`
             (user_id, loan_type, amount, months, annual_rate, monthly_payment, total_cost, total_interest)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $loanType,
            $amount,
            $months,
            $annualRate,
            $monthlyPayment,
            $totalCost,
            $totalInterest,
        ]);
        return (int) $this->getPdo()->lastInsertId();
    }

    /**
     * Retourne les dernières simulations d'un utilisateur.
     */
    public function getHistoryForUser(int $userId, int $limit = 10): array
    {
        $stmt = $this->getPdo()->prepare(
            'SELECT * FROM `loan_simulations`
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ' . max(1, $limit)
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
