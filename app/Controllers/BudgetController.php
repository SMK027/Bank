<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Transaction;

class BudgetController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        // Mois sélectionné (défaut : mois courant)
        $month = $_GET['month'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }

        // Navigation mois précédent / suivant
        $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
        $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));
        $isCurrentMonth = ($month === date('Y-m'));

        // Comptes personnels actifs de l'utilisateur (hors internes)
        $accountModel  = new Account();
        $data          = $accountModel->getAccessibleAccounts($userId);
        $ownAccountIds = array_column(
            array_filter($data['own'], fn($a) => empty($a['internal']) && empty($a['disabled_at'])),
            'id'
        );

        // Dépenses réelles par catégorie pour le mois sélectionné
        $txModel          = new Transaction();
        $spentByCategory  = $txModel->getMonthlyExpensesByCategory(
            array_map('intval', $ownAccountIds),
            $month
        );

        // Budgets définis par l'utilisateur
        $budgetModel = new Budget();
        $budgets     = $budgetModel->getForUser($userId);

        // Toutes les catégories pertinentes (dépenses + budgets définis)
        $allCategories = array_values(array_unique(array_merge(
            array_keys($spentByCategory),
            array_keys($budgets)
        )));
        usort($allCategories, fn($a, $b) => strcmp($a, $b));

        // Total dépensé ce mois
        $totalSpent = array_sum($spentByCategory);

        $this->render('budget/index', [
            'title'             => 'Budgets mensuels',
            'month'             => $month,
            'prevMonth'         => $prevMonth,
            'nextMonth'         => $nextMonth,
            'isCurrentMonth'    => $isCurrentMonth,
            'spentByCategory'   => $spentByCategory,
            'budgets'           => $budgets,
            'allCategories'     => $allCategories,
            'totalSpent'        => $totalSpent,
            'expenseCategories' => Transaction::EXPENSE_CATEGORIES,
        ]);
    }

    public function save(): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId = $this->getCurrentUserId();

        $category = trim($_POST['category'] ?? '');
        $rawLimit = str_replace([' ', ','], ['', '.'], $_POST['monthly_limit'] ?? '0');
        $limit    = (float) $rawLimit;
        $month    = $_POST['month'] ?? date('Y-m');

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }

        if (!array_key_exists($category, Transaction::EXPENSE_CATEGORIES)) {
            $this->setFlash('danger', 'Catégorie de dépense invalide.');
            $this->redirect('/budget?month=' . urlencode($month));
            return;
        }

        $budgetModel = new Budget();
        if ($limit <= 0) {
            $budgetModel->deleteForCategory($userId, $category);
            $this->setFlash('success', 'Budget supprimé pour « ' . $category . ' ».');
        } else {
            $budgetModel->upsert($userId, $category, $limit);
            $this->setFlash('success', 'Budget mis à jour pour « ' . $category . ' ».');
        }

        $this->redirect('/budget?month=' . urlencode($month));
    }
}
