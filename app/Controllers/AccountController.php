<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\AccountAccess;
use App\Models\DirectDebit;
use App\Models\Guardianship;
use App\Models\Mandate;
use App\Models\RecurringTransfer;
use App\Models\User;

class AccountController extends Controller
{
    private Account $accountModel;
    private Transaction $transactionModel;
    private AccountAccess $accessModel;
    private DirectDebit $directDebitModel;
    private User $userModel;
    private RecurringTransfer $recurringTransferModel;

    public function __construct()
    {
        $this->accountModel           = new Account();
        $this->transactionModel       = new Transaction();
        $this->accessModel            = new AccountAccess();
        $this->directDebitModel       = new DirectDebit();
        $this->userModel              = new User();
        $this->recurringTransferModel = new RecurringTransfer();
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $user    = $this->userModel->find($this->getCurrentUserId());
        $isMinor = User::isMinorFromDate($user['birth_date'] ?? null);
        if ($isMinor) {
            $this->setFlash('danger', 'Les mineurs ne peuvent pas créer de compte. Les comptes sont ouverts par la modération.');
            $this->redirect('/dashboard');
            return;
        }
        $isPro = User::isProfessional($user);
        $this->render('accounts/create', [
            'title'        => 'Créer un compte bancaire',
            'accountTypes' => Account::getAllowedTypes($isMinor, $isPro),
            'isMinor'      => $isMinor,
            'isPro'        => $isPro,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $data = $this->getPostData(['name', 'currency', 'overdraft', 'account_type', 'cap']);

        if (empty($data['name']) || empty($data['currency'])) {
            $this->setFlash('danger', 'Le nom et la devise sont requis.');
            $this->redirect('/accounts/create');
            return;
        }

        $user    = $this->userModel->find($this->getCurrentUserId());
        $isMinor = User::isMinorFromDate($user['birth_date'] ?? null);

        // Les mineurs ne peuvent pas créer de compte eux-mêmes
        if ($isMinor) {
            $this->setFlash('danger', 'Les mineurs ne peuvent pas créer de compte. Les comptes sont ouverts par la modération.');
            $this->redirect('/dashboard');
            return;
        }

        // Le type 'minor' est réservé à la modération, jamais au formulaire utilisateur
        if (($data['account_type'] ?? '') === 'minor') {
            $this->setFlash('danger', 'Les comptes mineurs sont créés uniquement par la modération.');
            $this->redirect('/dashboard');
            return;
        }

        // Le type 'pro' est réservé aux utilisateurs professionnels
        if (($data['account_type'] ?? '') === 'pro' && !User::isProfessional($user)) {
            $this->setFlash('danger', 'Les comptes professionnels sont réservés aux utilisateurs ayant un statut professionnel vérifié.');
            $this->redirect('/accounts/create');
            return;
        }

        $allowedTypes = Account::getAllowedTypes($isMinor, User::isProfessional($user));

        if (!array_key_exists($data['account_type'], $allowedTypes)) {
            $this->setFlash('danger', 'Type de compte non autorisé pour votre profil.');
            $this->redirect('/accounts/create');
            return;
        }

        $type      = $data['account_type'];
        $overdraft = Account::typeAllowsOverdraft($type) ? abs((float) ($data['overdraft'] ?: 0)) : 0.0;
        $cap       = Account::typeHasCap($type) && $data['cap'] !== '' ? abs((float) $data['cap']) : null;

        $this->accountModel->createAccount(
            $this->getCurrentUserId(),
            $data['name'],
            $data['currency'],
            $overdraft,
            $type,
            $cap
        );

        $this->setFlash('success', 'Compte bancaire créé avec succès !');
        $this->redirect('/dashboard');
    }

    public function show(string $id): void
    {
        $this->requireAuth();
        $accountId = (int) $id;
        $userId = $this->getCurrentUserId();

        $account = $this->accountModel->find($accountId);
        if (!$account || (!$this->isModerator() && !$this->accountModel->hasAccess($accountId, $userId))) {
            $this->setFlash('danger', 'Compte introuvable ou accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $transactions = $this->transactionModel->getByAccount($accountId);
        // Enrichir chaque transaction avec le nom de l'auteur et le statut programmé
        foreach ($transactions as &$t) {
            $authorId = isset($t['user_id']) ? (int) $t['user_id'] : 0;
            if ($authorId === 0) {
                // user_id = 0 : action explicitement anonymisée (ex. annulation par modération)
                $t['author_name'] = 'Modération';
            } else {
                $author = $this->userModel->find($authorId);
                if ($author && ($author['global_role'] ?? 'user') === 'moderator'
                    && !$this->accountModel->hasAccess($accountId, $authorId)) {
                    // Modérateur sans accès légitime → action de modération anonymisée
                    $t['author_name'] = 'Modération';
                } else {
                    $t['author_name'] = $author ? $author['username'] : 'Inconnu';
                }
            }
            $t['is_pending'] = Transaction::isPending($t);
        }
        unset($t);
        $balance       = $this->accountModel->getBalance($accountId);
        $futureBalance = $this->accountModel->getFutureBalance($accountId);
        $totalIncome   = $this->transactionModel->getTotalIncome($accountId, true);
        $totalExpense  = $this->transactionModel->getTotalExpense($accountId, true);
        $totalIncomeFuture  = $this->transactionModel->getTotalIncome($accountId);
        $totalExpenseFuture = $this->transactionModel->getTotalExpense($accountId);
        $hasPending    = abs($futureBalance - $balance) > 0.001;
        $isOwner = $this->accountModel->isOwner($accountId, $userId);
        $isModerator = $this->isModerator();
        $isFrozen    = $this->accountModel->isFrozen($accountId);

        // Récupérer les accès partagés
        $accesses = [];
        if ($isOwner || $isModerator) {
            $rawAccesses = $this->accessModel->getAccessesForAccount($accountId);
            foreach ($rawAccesses as &$access) {
                $user = $this->userModel->find((int) $access['user_id']);
                $access['username'] = $user ? $user['username'] : 'Inconnu';
            }
            unset($access);
            $accesses = $rawAccesses;
        }

        // Récupérer le propriétaire
        $owner = $this->userModel->find((int) $account['user_id']);

        // Responsables légaux si le propriétaire est mineur
        $guardians       = [];
        $isMinorAccount  = User::isMinorFromDate($owner['birth_date'] ?? null);
        $isGuardian      = false;
        if ($isMinorAccount) {
            $guardianshipModel = new Guardianship();
            $isGuardian = !$isOwner
                && $guardianshipModel->isActiveGuardianOf($userId, (int) $account['user_id']);
            foreach ($guardianshipModel->getGuardiansOf((int) $account['user_id']) as $g) {
                $guardianUser = $this->userModel->find((int) $g['guardian_user_id']);
                if ($guardianUser) {
                    $guardians[] = $guardianUser;
                }
            }
        }

        // Séparer les transactions à venir des exécutées
        $pendingTransactions  = array_values(array_filter($transactions, fn($t) => $t['is_pending']));
        $executedTransactions = array_values(array_filter($transactions, fn($t) => !$t['is_pending']));

        // IDs de transactions liées à un virement ou prélèvement (non supprimables individuellement)
        $allTxIds    = array_column($transactions, 'id');
        $linkedTxIds = $allTxIds ? $this->transactionModel->getProtectedIds($allTxIds) : [];

        // Prélèvements planifiés sur ce compte (to_account) non encore exécutés
        $upcomingDebits = $this->directDebitModel->findBy(
            ['to_account_id' => $accountId, 'status' => DirectDebit::STATUS_SCHEDULED],
            'scheduled_at',
            'ASC'
        );

        // Mandats rattachés au compte (émetteur ou destinataire) — comptes pro uniquement
        $mandateModel = new Mandate();
        $mandates = [];
        if ($account['type'] === 'pro') {
            $mandates = $mandateModel->getByAccount($accountId);
        }

        // Mandats à venir (prochaine exécution planifiée) — tous types de comptes
        $upcomingMandates = $mandateModel->getUpcomingByAccount($accountId);

        // Virements récurrents liés à ce compte (émetteur ou destinataire)
        $recurringTransfers = $this->recurringTransferModel->getByAccount($accountId);
        foreach ($recurringTransfers as &$r) {
            $fromAcc = $this->accountModel->find((int) $r['from_account_id']);
            $toAcc   = $this->accountModel->find((int) $r['to_account_id']);
            $r['from_account_name'] = $fromAcc['name'] ?? ('Compte #' . $r['from_account_id']);
            $r['to_account_name']   = $toAcc['name']   ?? ('Compte #' . $r['to_account_id']);
        }
        unset($r);

        $this->render('accounts/show', [
            'title'                => $account['name'],
            'account'             => $account,
            'transactions'        => $transactions,
            'pendingTransactions'  => $pendingTransactions,
            'executedTransactions' => $executedTransactions,
            'upcomingDebits'       => $upcomingDebits,
            'balance'             => $balance,
            'futureBalance'      => $futureBalance,
            'hasPending'         => $hasPending,
            'totalIncome'        => $totalIncome,
            'totalExpense'       => $totalExpense,
            'totalIncomeFuture'  => $totalIncomeFuture,
            'totalExpenseFuture' => $totalExpenseFuture,
            'isOwner'            => $isOwner,
            'isModerator'        => $isModerator,
            'isFrozen'           => $isFrozen,
            'accesses'           => $accesses,
            'owner'              => $owner,
            'guardians'          => $guardians,
            'isMinorAccount'     => $isMinorAccount,
            'isGuardian'         => $isGuardian,
            'categories'         => Transaction::CATEGORIES,
            'mandates'           => $mandates,
            'upcomingMandates'   => $upcomingMandates,
            'linkedTxIds'        => $linkedTxIds,
            'recurringTransfers' => $recurringTransfers,
        ]);
    }

    public function editForm(string $id): void
    {
        $this->requireAuth();
        $accountId = (int) $id;
        $userId = $this->getCurrentUserId();

        $account = $this->accountModel->find($accountId);
        if (!$account || !$this->accountModel->isOwner($accountId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $owner   = $this->userModel->find((int) $account['user_id']);
        $isMinor = User::isMinorFromDate($owner['birth_date'] ?? null);

        $this->render('accounts/edit', [
            'title'        => 'Modifier le compte',
            'account'      => $account,
            'accountTypes' => Account::TYPES,
            'isMinor'      => $isMinor,
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $accountId = (int) $id;
        $userId = $this->getCurrentUserId();

        if (!$this->accountModel->isOwner($accountId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $data = $this->getPostData(['name', 'currency', 'overdraft', 'account_type', 'cap']);

        if (empty($data['name']) || empty($data['currency'])) {
            $this->setFlash('danger', 'Le nom et la devise sont requis.');
            $this->redirect('/accounts/' . $id . '/edit');
            return;
        }

        // Les mineurs ne peuvent pas changer le type de leur compte
        $account = $this->accountModel->find($accountId);
        $accountOwner = $this->userModel->find((int) ($account['user_id'] ?? 0));
        if (User::isMinorFromDate($accountOwner['birth_date'] ?? null)) {
            $data['account_type'] = $account['type'] ?? 'savings';
        }

        $type      = array_key_exists($data['account_type'], Account::TYPES) ? $data['account_type'] : 'standard';
        $overdraft = Account::typeAllowsOverdraft($type) ? abs((float) ($data['overdraft'] ?: 0)) : 0.0;
        $cap       = Account::typeHasCap($type) && $data['cap'] !== '' ? abs((float) $data['cap']) : null;

        $this->accountModel->update($accountId, [
            'name'      => $data['name'],
            'currency'  => $data['currency'],
            'overdraft' => $overdraft,
            'type'      => $type,
            'cap'       => $cap,
        ]);

        $this->setFlash('success', 'Compte modifié avec succès.');
        $this->redirect('/accounts/' . $id);
    }

    public function deleteAccount(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $accountId   = (int) $id;
        $userId      = $this->getCurrentUserId();
        $isOwner     = $this->accountModel->isOwner($accountId, $userId);
        $isModerator = $this->isModerator();

        if (!$isOwner && !$isModerator) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        // Supprimer les transactions associées
        $transactions = $this->transactionModel->getByAccount($accountId);
        foreach ($transactions as $t) {
            $this->transactionModel->delete((int) $t['id']);
        }

        // Supprimer les accès partagés
        $accesses = $this->accessModel->getAccessesForAccount($accountId);
        foreach ($accesses as $a) {
            $this->accessModel->delete((int) $a['id']);
        }

        $this->accountModel->delete($accountId);
        $this->setFlash('success', 'Compte supprimé.');
        $this->redirect('/dashboard');
    }

    // -----------------------------------------------------------------------
    // Relevé de compte (PDF)
    // -----------------------------------------------------------------------

    public function statementForm(string $id): void
    {
        $this->requireAuth();
        $accountId   = (int) $id;
        $userId      = $this->getCurrentUserId();
        $isModerator = $this->isModerator();

        $account = $this->accountModel->find($accountId);
        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/dashboard');
            return;
        }

        $hasAccess = $this->accountModel->isOwner($accountId, $userId)
            || $this->accountModel->hasAccess($accountId, $userId)
            || $isModerator;

        if (!$hasAccess) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $this->render('accounts/statement_form', [
            'title'   => 'Relevé de compte — ' . $account['name'],
            'account' => $account,
        ]);
    }

    public function generateStatement(string $id): void
    {
        $this->requireAuth();
        $accountId   = (int) $id;
        $userId      = $this->getCurrentUserId();
        $isModerator = $this->isModerator();

        $account = $this->accountModel->find($accountId);
        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/dashboard');
            return;
        }

        $hasAccess = $this->accountModel->isOwner($accountId, $userId)
            || $this->accountModel->hasAccess($accountId, $userId)
            || $isModerator;

        if (!$hasAccess) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        // Validation des dates (GET params)
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo   = $_GET['date_to']   ?? '';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $this->setFlash('danger', 'Dates invalides.');
            $this->redirect("/accounts/{$accountId}/statement");
            return;
        }

        if ($dateFrom > $dateTo) {
            $this->setFlash('danger', 'La date de début doit être antérieure à la date de fin.');
            $this->redirect("/accounts/{$accountId}/statement");
            return;
        }

        $owner        = $this->userModel->find((int) $account['user_id']);
        $transactions = $this->transactionModel->getByAccountBetween($accountId, $dateFrom, $dateTo);
        $openingBal   = $this->transactionModel->getBalanceBeforeDate($accountId, $dateFrom);

        // Calcul du solde courant et des totaux
        $totalIncome  = 0.0;
        $totalExpense = 0.0;
        $runningBal   = $openingBal;

        foreach ($transactions as &$t) {
            if ($t['type'] === 'income') {
                $totalIncome += (float) $t['amount'];
                $runningBal  += (float) $t['amount'];
            } else {
                $totalExpense += (float) $t['amount'];
                $runningBal   -= (float) $t['amount'];
            }
            $t['running_balance'] = $runningBal;
        }
        unset($t);
        $closingBal = $runningBal;

        // Génération du HTML du relevé
        $accountTypes = \App\Models\Account::TYPES;
        $typeLabel    = $accountTypes[$account['type']]['label'] ?? ucfirst($account['type']);
        $generatedAt  = (new \DateTime())->format('d/m/Y à H:i');

        $html = $this->renderStatementHtml(
            $account,
            $owner,
            $typeLabel,
            $dateFrom,
            $dateTo,
            $openingBal,
            $closingBal,
            $totalIncome,
            $totalExpense,
            $transactions,
            $generatedAt
        );

        // Génération PDF avec mPDF
        $mpdf = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 15,
            'margin_bottom' => 20,
            'margin_left'   => 15,
            'margin_right'  => 15,
            'tempDir'       => sys_get_temp_dir(),
        ]);

        $mpdf->SetTitle('Relevé de compte — ' . $account['name']);
        $mpdf->SetAuthor('BankApp');
        $mpdf->SetCreator('BankApp');

        $mpdf->SetHTMLFooter('
            <table width="100%" style="font-size:8pt;color:#888;border-top:1px solid #ddd;padding-top:4px;">
                <tr>
                    <td>Document généré le ' . $generatedAt . ' — Confidentiel</td>
                    <td style="text-align:right;">Page {PAGENO} / {nbpg}</td>
                </tr>
            </table>');

        $mpdf->WriteHTML($html);

        $filename = 'releve_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($account['name']))
                  . '_' . str_replace('-', '', $dateFrom)
                  . '_' . str_replace('-', '', $dateTo)
                  . '.pdf';

        $mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);
        exit;
    }

    private function renderStatementHtml(
        array   $account,
        ?array  $owner,
        string  $typeLabel,
        string  $dateFrom,
        string  $dateTo,
        float   $openingBal,
        float   $closingBal,
        float   $totalIncome,
        float   $totalExpense,
        array   $transactions,
        string  $generatedAt
    ): string {
        $fmt = fn(float $v): string => number_format($v, 2, ',', ' ') . ' ' . e($account['currency']);

        $fromFmt = (new \DateTime($dateFrom))->format('d/m/Y');
        $toFmt   = (new \DateTime($dateTo))->format('d/m/Y');

        $rows = '';
        foreach ($transactions as $t) {
            $isIncome  = $t['type'] === 'income';
            $amount    = (float) $t['amount'];
            $bal       = (float) $t['running_balance'];
            $color     = $isIncome ? '#16a34a' : '#dc2626';
            $sign      = $isIncome ? '+' : '−';
            $balColor  = $bal < 0 ? '#dc2626' : '#1e293b';
            $date      = (new \DateTime($t['created_at']))->format('d/m/Y H:i');

            $rows .= '<tr>
                <td style="color:#64748b;font-size:10pt;">' . e($date) . '</td>
                <td>' . e($t['category']) . '</td>
                <td style="max-width:200px;">' . e($t['comment'] ?: '—') . '</td>
                <td style="text-align:right;color:' . $color . ';font-weight:600;">'
                    . $sign . ' ' . number_format($amount, 2, ',', ' ') . '</td>
                <td style="text-align:right;color:' . $balColor . ';font-weight:600;">'
                    . number_format($bal, 2, ',', ' ') . ' ' . e($account['currency']) . '</td>
            </tr>';
        }

        if (empty($transactions)) {
            $rows = '<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:20px;">Aucune opération sur cette période.</td></tr>';
        }

        $ownerName = e($owner['username'] ?? 'Inconnu');

        return '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
    body          { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1e293b; margin: 0; }
    h1            { font-size: 18pt; color: #1e40af; margin: 0 0 4px; }
    h2            { font-size: 12pt; color: #334155; margin: 0 0 10px; font-weight: normal; }
    .header-block { border-bottom: 2px solid #1e40af; padding-bottom: 12px; margin-bottom: 18px; }
    .bank-name    { font-size: 22pt; font-weight: 700; color: #1e40af; letter-spacing: 1px; }
    .meta-grid    { width: 100%; }
    .meta-grid td { vertical-align: top; padding: 2px 0; }
    .label        { color: #64748b; font-size: 9pt; }
    .value        { color: #1e293b; font-weight: 600; }
    .summary-box  { background: #f1f5f9; border-radius: 6px; padding: 12px 16px; margin: 16px 0; }
    .summary-grid { width: 100%; }
    .summary-grid td { padding: 4px 8px; font-size: 9.5pt; }
    .s-label      { color: #64748b; }
    .s-value      { font-weight: 700; text-align: right; }
    .income       { color: #16a34a; }
    .expense      { color: #dc2626; }
    .neutral      { color: #1e293b; }
    table.ops     { width: 100%; border-collapse: collapse; margin-top: 12px; }
    table.ops th  { background: #1e40af; color: #fff; padding: 7px 8px; font-size: 9pt; text-align: left; }
    table.ops td  { padding: 6px 8px; font-size: 9pt; border-bottom: 1px solid #e2e8f0; }
    table.ops tr:nth-child(even) td { background: #f8fafc; }
    .closing-row td { font-weight: 700; background: #eff6ff !important; border-top: 2px solid #1e40af; }
    .no-break     { page-break-inside: avoid; }
</style>
</head>
<body>

<div class="header-block">
    <table style="width:100%">
        <tr>
            <td>
                <div class="bank-name">&#127981; BankApp</div>
                <div style="color:#64748b;font-size:9pt;">Banque de simulation — Relevé de compte</div>
            </td>
            <td style="text-align:right;vertical-align:bottom;">
                <div style="font-size:9pt;color:#64748b;">Généré le ' . $generatedAt . '</div>
            </td>
        </tr>
    </table>
</div>

<h1>' . e($account['name']) . '</h1>
<h2>Relevé du ' . $fromFmt . ' au ' . $toFmt . '</h2>

<table class="meta-grid" style="margin-bottom:6px;">
    <tr>
        <td style="width:50%">
            <span class="label">Type de compte&nbsp;</span>
            <span class="value">' . $typeLabel . '</span>
        </td>
        <td>
            <span class="label">Titulaire&nbsp;</span>
            <span class="value">' . $ownerName . '</span>
        </td>
    </tr>
    <tr>
        <td>
            <span class="label">Devise&nbsp;</span>
            <span class="value">' . e($account['currency']) . '</span>
        </td>
        <td>
            <span class="label">N° de compte&nbsp;</span>
            <span class="value">#' . (int) $account['id'] . '</span>
        </td>
    </tr>
</table>

<div class="summary-box no-break">
    <table class="summary-grid">
        <tr>
            <td class="s-label">Solde d\'ouverture (' . $fromFmt . ')</td>
            <td class="s-value neutral">' . $fmt($openingBal) . '</td>
            <td style="width:50px;"></td>
            <td class="s-label">Nombre d\'opérations</td>
            <td class="s-value neutral">' . count($transactions) . '</td>
        </tr>
        <tr>
            <td class="s-label">Total crédits (entrées)</td>
            <td class="s-value income">+ ' . $fmt($totalIncome) . '</td>
            <td></td>
            <td class="s-label">Total débits (sorties)</td>
            <td class="s-value expense">− ' . $fmt($totalExpense) . '</td>
        </tr>
        <tr>
            <td class="s-label" style="font-weight:700;font-size:10.5pt;">Solde de clôture (' . $toFmt . ')</td>
            <td class="s-value neutral" style="font-size:12pt;">' . $fmt($closingBal) . '</td>
            <td colspan="3"></td>
        </tr>
    </table>
</div>

<table class="ops">
    <thead>
        <tr>
            <th style="width:17%">Date</th>
            <th style="width:16%">Catégorie</th>
            <th>Libellé</th>
            <th style="width:14%;text-align:right;">Montant (' . e($account['currency']) . ')</th>
            <th style="width:18%;text-align:right;">Solde courant</th>
        </tr>
    </thead>
    <tbody>
        ' . $rows . '
    </tbody>
</table>

</body>
</html>';
    }
}

