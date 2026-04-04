<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Account;
use App\Models\Transaction;

class DashboardController extends Controller
{
    private Account $accountModel;

    public function __construct()
    {
        $this->accountModel = new Account();
    }

    public function index(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $data = $this->accountModel->getAccessibleAccounts($userId);
        $ownAccounts = $data['own'];
        $sharedAccounts = $data['shared'];

        // Calculer les soldes pour chaque compte
        $totalBalance = 0.0;
        foreach ($ownAccounts as &$account) {
            $account['balance'] = $this->accountModel->getBalance((int) $account['id']);
            $totalBalance += $account['balance'];
        }
        unset($account);

        foreach ($sharedAccounts as &$account) {
            $account['balance'] = $this->accountModel->getBalance((int) $account['id']);
        }
        unset($account);

        $this->render('dashboard/index', [
            'title'          => 'Tableau de bord',
            'ownAccounts'    => $ownAccounts,
            'sharedAccounts' => $sharedAccounts,
            'totalBalance'   => $totalBalance,
        ]);
    }
}
