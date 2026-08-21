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
        $ownAccounts    = array_values(array_filter($data['own'], fn($a) => empty($a['internal'])));
        $internalAccounts = array_values(array_filter($data['own'], fn($a) => !empty($a['internal'])));
        $sharedAccounts = $data['shared'];

        // Calculer les soldes (courant + à venir) en une seule passe batch
        // pour éviter les requêtes N+1 (cf. IMPROVEMENTS.md §5).
        $allAccountIds = array_map(
            'intval',
            array_merge(
                array_column($ownAccounts, 'id'),
                array_column($internalAccounts, 'id'),
                array_column($sharedAccounts, 'id')
            )
        );
        $balances = $this->accountModel->getBalancesBatch($allAccountIds);

        $applyBalances = function (array &$accounts) use ($balances): void {
            foreach ($accounts as &$account) {
                $aid = (int) $account['id'];
                $account['balance']        = $balances[$aid]['balance'] ?? 0.0;
                $account['future_balance'] = $balances[$aid]['future_balance'] ?? 0.0;
            }
            unset($account);
        };

        $applyBalances($ownAccounts);
        $applyBalances($internalAccounts);
        $applyBalances($sharedAccounts);

        $totalBalance = 0.0;
        foreach ($ownAccounts as $account) {
            if (($account['type'] ?? '') === 'event') {
                continue;
            }
            $totalBalance += $account['balance'];
        }

        $user    = (new User())->find($userId);
        $isMinor = User::isMinorFromDate($user['birth_date'] ?? null);

        $this->render('dashboard/index', [
            'title'            => 'Tableau de bord',
            'ownAccounts'      => $ownAccounts,
            'internalAccounts' => $internalAccounts,
            'sharedAccounts'   => $sharedAccounts,
            'totalBalance'     => $totalBalance,
            'isMinor'          => $isMinor,
        ]);
    }
}
