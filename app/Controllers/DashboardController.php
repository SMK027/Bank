<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

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
            $account['balance']        = $this->accountModel->getBalance((int) $account['id']);
            $account['future_balance'] = $this->accountModel->getFutureBalance((int) $account['id']);
            $totalBalance += $account['balance'];
        }
        unset($account);

        foreach ($sharedAccounts as &$account) {
            $account['balance']        = $this->accountModel->getBalance((int) $account['id']);
            $account['future_balance'] = $this->accountModel->getFutureBalance((int) $account['id']);
        }
        unset($account);

        $user    = (new User())->find($userId);
        $isMinor = User::isMinorFromDate($user['birth_date'] ?? null);

        $this->render('dashboard/index', [
            'title'          => 'Tableau de bord',
            'ownAccounts'    => $ownAccounts,
            'sharedAccounts' => $sharedAccounts,
            'totalBalance'   => $totalBalance,
            'isMinor'        => $isMinor,
        ]);
    }
}
