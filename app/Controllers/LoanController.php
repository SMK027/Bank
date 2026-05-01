<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\LoanSimulation;

class LoanController extends Controller
{
    private LoanSimulation $loanModel;

    public function __construct()
    {
        $this->loanModel = new LoanSimulation();
    }

    // ── Formulaire + liste ─────────────────────────────────────────────────────

    public function simulatorForm(): void
    {
        $this->requireAuth();
        $userId  = $this->getCurrentUserId();
        $history = $this->loanModel->getHistoryForUser($userId, 10);

        $this->render('loans/simulator', [
            'title'      => 'Simulateur de crédits',
            'types'      => LoanSimulation::getTypes(),
            'history'    => $history,
            'simulation' => null,
        ]);
    }

    // ── Traitement du formulaire ───────────────────────────────────────────────

    public function simulate(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $data   = $this->getPostData(['loan_type', 'amount', 'months']);
        $types  = LoanSimulation::getTypes();

        // --- Validation ---
        if (empty($data['loan_type']) || !isset($types[$data['loan_type']])) {
            $this->setFlash('danger', 'Type de crédit invalide.');
            $this->redirect('/loans/simulator');
            return;
        }

        $type   = $types[$data['loan_type']];
        $amount = (float) str_replace(',', '.', $data['amount']);
        $months = (int) $data['months'];

        if ($amount < $type['min_amount'] || $amount > $type['max_amount']) {
            $this->setFlash('danger', sprintf(
                'Le montant doit être compris entre %s € et %s € pour ce type de crédit.',
                number_format($type['min_amount'], 0, ',', ' '),
                number_format($type['max_amount'], 0, ',', ' ')
            ));
            $this->redirect('/loans/simulator');
            return;
        }

        if ($months < $type['min_months'] || $months > $type['max_months']) {
            $this->setFlash('danger', sprintf(
                'La durée doit être comprise entre %d et %d mois pour ce type de crédit.',
                $type['min_months'],
                $type['max_months']
            ));
            $this->redirect('/loans/simulator');
            return;
        }

        // --- Calcul ---
        $result = LoanSimulation::calculate($amount, $months, $type['rate']);

        // --- Persistance ---
        $this->loanModel->saveSimulation(
            $userId,
            $data['loan_type'],
            $amount,
            $months,
            $type['rate'],
            $result['monthly_payment'],
            $result['total_cost'],
            $result['total_interest']
        );

        $history = $this->loanModel->getHistoryForUser($userId, 10);

        $this->render('loans/simulator', [
            'title'      => 'Simulateur de crédits',
            'types'      => $types,
            'history'    => $history,
            'simulation' => [
                'loan_type'       => $data['loan_type'],
                'type_label'      => $type['label'],
                'amount'          => $amount,
                'months'          => $months,
                'annual_rate'     => $type['rate'],
                'monthly_payment' => $result['monthly_payment'],
                'total_cost'      => $result['total_cost'],
                'total_interest'  => $result['total_interest'],
                'amortization'    => $result['amortization'],
            ],
        ]);
    }
}
