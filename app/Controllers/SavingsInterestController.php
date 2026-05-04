<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Guardianship;
use App\Models\Notification;
use App\Models\SavingsInterest;
use App\Models\SavingsRate;
use App\Models\Transaction;

class SavingsInterestController extends Controller
{
    private SavingsInterest $interestModel;
    private SavingsRate     $rateModel;
    private Account         $accountModel;
    private Transaction     $transactionModel;
    private Notification    $notifModel;
    private Guardianship    $guardianshipModel;

    public function __construct()
    {
        $this->interestModel    = new SavingsInterest();
        $this->rateModel        = new SavingsRate();
        $this->accountModel     = new Account();
        $this->transactionModel = new Transaction();
        $this->notifModel       = new Notification();
        $this->guardianshipModel = new Guardianship();
    }

    // ── Liste des intérêts en attente ────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $userId  = $this->getCurrentUserId();
        $pending = $this->interestModel->getPendingForUser($userId);

        $this->render('accounts/interests', [
            'title'   => 'Mes intérêts d\'épargne',
            'pending' => $pending,
        ]);
    }

    // ── Formulaire de confirmation ────────────────────────────────────────────

    public function confirmForm(string $id): void
    {
        $this->requireAuth();
        $userId   = $this->getCurrentUserId();
        $interest = $this->interestModel->find((int) $id);

        if (!$this->checkAccess($interest, $userId)) {
            return;
        }

        $account  = $this->accountModel->find((int) $interest['account_id']);
        $balance  = $this->accountModel->getBalance((int) $interest['account_id']);
        $cap      = $this->resolveCap($account);
        // Le taux de référence est celui enregistré dans l'intérêt (taux du compte au moment du calcul)
        $rate     = (float) $interest['rate'];
        $maxNow   = SavingsInterest::computeMaxAmount($balance, $rate, $cap);

        $this->render('accounts/interest_confirm', [
            'title'    => 'Confirmer les intérêts ' . (int) $interest['year'],
            'interest' => $interest,
            'account'  => $account,
            'balance'  => $balance,
            'maxNow'   => $maxNow,
        ]);
    }

    // ── Traitement de la confirmation ─────────────────────────────────────────

    public function confirm(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId   = $this->getCurrentUserId();
        $interest = $this->interestModel->find((int) $id);

        if (!$this->checkAccess($interest, $userId)) {
            return;
        }

        $data     = $this->getPostData(['choice', 'custom_amount']);
        $account  = $this->accountModel->find((int) $interest['account_id']);
        $balance  = $this->accountModel->getBalance((int) $interest['account_id']);
        $cap      = $this->resolveCap($account);
        $maxNow   = SavingsInterest::computeMaxAmount($balance, (float) $interest['rate'], $cap);

        // ── Déterminer le montant à verser ────────────────────────────────────
        if ($data['choice'] === 'yes') {
            // "Oui" : on utilise le montant calculé, silencieusement borné au max actuel
            $amount = round(min((float) $interest['calculated_amount'], $maxNow), 2);
        } else {
            // "Non" : montant saisi manuellement
            $amount = round(abs((float) $data['custom_amount']), 2);
        }

        // ── Validations ────────────────────────────────────────────────────────
        if ($amount <= 0) {
            $this->setFlash('danger', 'Aucun intérêt versable actuellement (solde nul ou plafond atteint).');
            $this->redirect('/interests/' . $id . '/confirm');
            return;
        }

        if ($amount > $maxNow + 0.01) {
            $this->setFlash('danger', sprintf(
                'Le montant saisi (%s %s) dépasse le maximum théorique autorisé (%s %s) calculé d\'après votre solde actuel et le taux configuré.',
                number_format($amount, 2, ',', ' '),
                $account['currency'],
                number_format($maxNow, 2, ',', ' '),
                $account['currency']
            ));
            $this->redirect('/interests/' . $id . '/confirm');
            return;
        }

        // ── Créer la transaction de versement ─────────────────────────────────
        $txId = $this->transactionModel->addTransaction(
            (int) $interest['account_id'],
            'income',
            $amount,
            'Épargne',
            sprintf(
                'Intérêts %d — taux : %s %%',
                (int) $interest['year'],
                number_format((float) $interest['rate'] * 100, 2, ',', ' ')
            ),
            $userId
        );

        // ── Marquer comme confirmé ─────────────────────────────────────────────
        $this->interestModel->update((int) $id, [
            'status'           => SavingsInterest::STATUS_CONFIRMED,
            'confirmed_amount' => $amount,
            'transaction_id'   => $txId,
            'confirmed_at'     => date('Y-m-d H:i:s'),
        ]);

        // ── Audit ──────────────────────────────────────────────────────────────
        AuditLog::log($userId, AuditLog::ACTION_INTEREST_CONFIRM, [
            'interest_id' => (int) $id,
            'year'        => (int) $interest['year'],
            'amount'      => $amount,
            'choice'      => $data['choice'] === 'yes' ? 'calculated' : 'custom',
        ], targetAccountId: (int) $interest['account_id']);

        $this->setFlash('success', sprintf(
            'Intérêts %d de %s %s versés sur le compte « %s ».',
            (int) $interest['year'],
            number_format($amount, 2, ',', ' '),
            $account['currency'],
            $account['name']
        ));
        $this->redirect('/accounts/' . $interest['account_id']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Vérifie que l'intérêt existe, est pending et appartient à l'utilisateur.
     * Redirige et retourne false si l'accès est refusé.
     */
    private function checkAccess(?array $interest, int $userId): bool
    {
        if (!$interest) {
            $this->setFlash('danger', 'Intérêts introuvables.');
            $this->redirect('/interests');
            return false;
        }
        if ($interest['status'] !== SavingsInterest::STATUS_PENDING) {
            $this->setFlash('info', 'Ces intérêts ont déjà été traités.');
            $this->redirect('/interests');
            return false;
        }
        $account = $this->accountModel->find((int) $interest['account_id']);
        if (!$account || (int) $account['user_id'] !== $userId) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/interests');
            return false;
        }
        return true;
    }

    /** Retourne le plafond (float) du compte si pertinent, null sinon. Les comptes internes sont exemptés. */
    private function resolveCap(?array $account): ?float
    {
        if ($account && Account::typeHasCap($account['type'] ?? '') && (float) ($account['cap'] ?? 0) > 0 && !Account::isInternal($account)) {
            return (float) $account['cap'];
        }
        return null;
    }
}
