<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\ExpenseSplit;
use App\Models\Friendship;
use App\Models\Notification;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Models\User;

class ExpenseSplitController extends Controller
{
    private ExpenseSplit   $splitModel;
    private Friendship     $friendModel;
    private Transaction    $transactionModel;
    private Account        $accountModel;
    private PaymentRequest $requestModel;
    private User           $userModel;
    private Notification   $notifModel;

    public function __construct()
    {
        $this->splitModel       = new ExpenseSplit();
        $this->friendModel      = new Friendship();
        $this->transactionModel = new Transaction();
        $this->accountModel     = new Account();
        $this->requestModel     = new PaymentRequest();
        $this->userModel        = new User();
        $this->notifModel       = new Notification();
    }

    /**
     * POST /accounts/{accountId}/transactions/{transactionId}/split
     *
     * Corps attendu :
     *   participants[0][user_id]  = int
     *   participants[0][amount]   = float (ex: "12.50")
     *   participants[1][user_id]  = int
     *   participants[1][amount]   = float
     *   …
     */
    public function create(string $accountId, string $transactionId): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId   = $this->getCurrentUserId();
        $accId    = (int) $accountId;
        $txId     = (int) $transactionId;

        // ── Vérifications de base ────────────────────────────────────────────

        if (!$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé à ce compte.');
            $this->redirect("/accounts/{$accId}");
            return;
        }

        $account = $this->accountModel->find($accId);
        if (($account['type'] ?? '') === 'event') {
            $this->setFlash('danger', 'La répartition de dépenses est désactivée sur les comptes événementiels.');
            $this->redirect("/accounts/{$accId}");
            return;
        }

        $tx = $this->transactionModel->find($txId);
        if (!$tx || (int) $tx['account_id'] !== $accId) {
            $this->setFlash('danger', 'Transaction introuvable.');
            $this->redirect("/accounts/{$accId}");
            return;
        }

        if ($tx['type'] !== 'expense') {
            $this->setFlash('danger', 'Seules les dépenses peuvent être réparties.');
            $this->redirect("/accounts/{$accId}");
            return;
        }

        if (!empty($tx['pending'])) {
            $this->setFlash('danger', 'Impossible de répartir une opération en attente.');
            $this->redirect("/accounts/{$accId}");
            return;
        }

        if ($this->splitModel->existsForTransaction($txId)) {
            $this->setFlash('danger', 'Cette dépense a déjà été répartie.');
            $this->redirect("/accounts/{$accId}");
            return;
        }

        // ── Validation des participants ──────────────────────────────────────

        $rawParticipants = $_POST['participants'] ?? [];
        if (!is_array($rawParticipants) || count($rawParticipants) === 0) {
            $this->setFlash('danger', 'Veuillez sélectionner au moins un participant.');
            $this->redirect("/accounts/{$accId}");
            return;
        }

        $txAmount   = (float) $tx['amount'];
        $totalSplit = 0.0;
        $validated  = [];
        $currency   = '';

        $account = $this->accountModel->find($accId);
        $currency = $account ? ($account['currency'] ?? 'EUR') : 'EUR';

        foreach ($rawParticipants as $p) {
            $pUserId = (int) ($p['user_id'] ?? 0);
            $rawAmt  = str_replace([' ', ','], ['', '.'], $p['amount'] ?? '0');
            $pAmount = round((float) $rawAmt, 2);

            if ($pUserId <= 0) {
                $this->setFlash('danger', 'Participant invalide.');
                $this->redirect("/accounts/{$accId}");
                return;
            }

            if ($pUserId === $userId) {
                $this->setFlash('danger', 'Vous ne pouvez pas vous inclure en tant que participant.');
                $this->redirect("/accounts/{$accId}");
                return;
            }

            if ($pAmount <= 0) {
                $this->setFlash('danger', 'Chaque montant doit être strictement positif.');
                $this->redirect("/accounts/{$accId}");
                return;
            }

            // Vérifier que le participant est un ami
            if (!$this->friendModel->areFriends($userId, $pUserId)) {
                $user = $this->userModel->find($pUserId);
                $this->setFlash('danger', sprintf(
                    'Vous devez être ami avec %s pour le/la inclure dans une répartition.',
                    e($user['username'] ?? '#' . $pUserId)
                ));
                $this->redirect("/accounts/{$accId}");
                return;
            }

            $totalSplit       += $pAmount;
            $validated[]       = ['user_id' => $pUserId, 'amount' => $pAmount];
        }

        $totalSplit = round($totalSplit, 2);

        if ($totalSplit > round($txAmount + 0.001, 2)) {
            $this->setFlash('danger', sprintf(
                'Le total des participations (%s %s) dépasse le montant de la dépense (%s %s).',
                number_format($totalSplit, 2, ',', ' '),
                $currency,
                number_format($txAmount, 2, ',', ' '),
                $currency
            ));
            $this->redirect("/accounts/{$accId}");
            return;
        }

        // ── Création des demandes de paiement et enregistrement ──────────────

        $comment  = $tx['comment'] ? 'Répartition — ' . $tx['comment'] : 'Répartition de dépense';
        $myName   = $this->userModel->find($userId)['username'] ?? 'Quelqu\'un';

        foreach ($validated as &$p) {
            $prId = $this->requestModel->createRequest(
                $userId,                  // requester : celui qui paie et demande le remboursement
                $p['user_id'],            // recipient : l'ami qui doit sa part
                $p['amount'],
                $currency,
                $comment,
                $accId,                   // le compte créditeur = le compte de la dépense
                14                        // validité : 14 jours
            );

            $p['payment_request_id'] = $prId;

            // Notification
            $this->notifModel->notify(
                $p['user_id'],
                'expense_split_request',
                sprintf('%s vous demande %s %s (répartition de dépense)', $myName, number_format($p['amount'], 2, ',', ' '), $currency),
                $comment,
                '/payment-requests'
            );
        }
        unset($p);

        $this->splitModel->createSplit(
            $txId,
            $accId,
            $userId,
            $txAmount,
            $validated
        );

        $count = count($validated);
        $this->setFlash('success', sprintf(
            'Répartition créée — %d demande%s de paiement envoyée%s pour un total de %s %s.',
            $count,
            $count > 1 ? 's' : '',
            $count > 1 ? 's' : '',
            number_format($totalSplit, 2, ',', ' '),
            $currency
        ));
        $this->redirect("/accounts/{$accId}");
    }
}
