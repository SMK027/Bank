<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\Notification;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;

class PaymentRequestController extends Controller
{
    private PaymentRequest $requestModel;
    private Account        $accountModel;
    private Transaction    $transactionModel;
    private Transfer       $transferModel;
    private User           $userModel;
    private Notification   $notifModel;

    public function __construct()
    {
        $this->requestModel     = new PaymentRequest();
        $this->accountModel     = new Account();
        $this->transactionModel = new Transaction();
        $this->transferModel    = new Transfer();
        $this->userModel        = new User();
        $this->notifModel       = new Notification();
    }

    // ── Liste des demandes ────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $requests        = $this->requestModel->getForUser($userId);
        $pendingReceived = $this->requestModel->countPendingForRecipient($userId);

        // Comptes de l'utilisateur pour le formulaire de création (compte créditeur)
        $data        = $this->accountModel->getAccessibleAccounts($userId);
        $ownAccounts = array_values(array_filter(
            $data['own'],
            fn($a) => empty($a['disabled_at']) && empty($a['internal'])
        ));

        $this->render('payment_requests/index', [
            'title'           => 'Demandes d\'argent',
            'requests'        => $requests,
            'pendingReceived'  => $pendingReceived,
            'ownAccounts'     => $ownAccounts,
            'currentUserId'   => $userId,
        ]);
    }

    // ── Création d'une demande ────────────────────────────────────────────────

    public function create(): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId = $this->getCurrentUserId();

        $recipientSearch = trim($_POST['recipient_search'] ?? '');
        $recipientId     = (int) ($_POST['recipient_id'] ?? 0);
        $fromAccountId   = (int) ($_POST['from_account_id'] ?? 0);
        $rawAmount       = str_replace([' ', ','], ['', '.'], $_POST['amount'] ?? '0');
        $amount          = (float) $rawAmount;
        $motif           = trim($_POST['motif'] ?? '');
        $expiryDays      = max(1, min(30, (int) ($_POST['expiry_days'] ?? PaymentRequest::DEFAULT_EXPIRY_DAYS)));

        if ($recipientId <= 0) {
            $this->setFlash('danger', 'Destinataire invalide.');
            $this->redirect('/payment-requests');
            return;
        }

        if ($recipientId === $userId) {
            $this->setFlash('danger', 'Vous ne pouvez pas vous envoyer une demande à vous-même.');
            $this->redirect('/payment-requests');
            return;
        }

        $recipient = $this->userModel->find($recipientId);
        if (!$recipient) {
            $this->setFlash('danger', 'Destinataire introuvable.');
            $this->redirect('/payment-requests');
            return;
        }

        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant doit être strictement positif.');
            $this->redirect('/payment-requests');
            return;
        }

        // Vérifier le compte créditeur (facultatif mais s'il est fourni, doit appartenir à l'utilisateur)
        $creditAccount = null;
        if ($fromAccountId > 0) {
            $creditAccount = $this->accountModel->find($fromAccountId);
            if (!$creditAccount || !$this->accountModel->hasAccess($fromAccountId, $userId)) {
                $this->setFlash('danger', 'Compte de réception invalide.');
                $this->redirect('/payment-requests');
                return;
            }
        }

        $currency = $creditAccount ? $creditAccount['currency'] : 'EUR';

        $requestId = $this->requestModel->createRequest(
            $userId,
            $recipientId,
            $amount,
            $currency,
            $motif,
            $fromAccountId > 0 ? $fromAccountId : null,
            $expiryDays
        );

        // Notifier le destinataire
        $senderName = $this->userModel->find($userId)['username'] ?? 'Quelqu\'un';
        $this->notifModel->notify(
            $recipientId,
            'payment_request_received',
            sprintf('%s vous demande %s %s', $senderName, number_format($amount, 2, ',', ' '), $currency),
            $motif !== '' ? $motif : null,
            '/payment-requests'
        );

        $this->setFlash('success', sprintf(
            'Demande de %s %s envoyée à %s.',
            number_format($amount, 2, ',', ' '),
            $currency,
            e($recipient['username'])
        ));
        $this->redirect('/payment-requests');
    }

    // ── Paiement d'une demande reçue ─────────────────────────────────────────

    public function pay(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId    = $this->getCurrentUserId();
        $requestId = (int) $id;

        $request = $this->requestModel->find($requestId);
        if (!$request || (int) $request['recipient_id'] !== $userId) {
            $this->setFlash('danger', 'Demande introuvable.');
            $this->redirect('/payment-requests');
            return;
        }

        if (!$this->requestModel->isPending($request)) {
            $this->setFlash('danger', 'Cette demande n\'est plus en attente.');
            $this->redirect('/payment-requests');
            return;
        }

        $fromAccountId = (int) ($_POST['from_account_id'] ?? 0);
        if ($fromAccountId <= 0) {
            $this->setFlash('danger', 'Veuillez sélectionner un compte à débiter.');
            $this->redirect('/payment-requests');
            return;
        }

        if (!$this->accountModel->hasAccess($fromAccountId, $userId)) {
            $this->setFlash('danger', 'Accès refusé au compte sélectionné.');
            $this->redirect('/payment-requests');
            return;
        }

        $fromAccount = $this->accountModel->find($fromAccountId);
        if (!$fromAccount) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/payment-requests');
            return;
        }

        if ($this->accountModel->isFrozen($fromAccountId)) {
            $this->setFlash('danger', 'Le compte sélectionné est gelé.');
            $this->redirect('/payment-requests');
            return;
        }

        if ($this->accountModel->isDisabled($fromAccountId)) {
            $this->setFlash('danger', 'Le compte sélectionné est en cours de résiliation.');
            $this->redirect('/payment-requests');
            return;
        }

        $amount   = (float) $request['amount'];
        $balance  = $this->accountModel->getBalance($fromAccountId);
        $overdraft = (float) ($fromAccount['overdraft'] ?? 0);

        if ($balance - $amount < -$overdraft) {
            $this->setFlash('danger', sprintf(
                'Fonds insuffisants. Solde disponible : %s € (découvert autorisé : %s €).',
                number_format($balance, 2, ',', ' '),
                number_format($overdraft, 2, ',', ' ')
            ));
            $this->redirect('/payment-requests');
            return;
        }

        // Compte créditeur : soit celui spécifié dans la demande, soit n'importe quel compte actif du demandeur
        $toAccountId = (int) ($request['from_account_id'] ?? 0);
        if ($toAccountId <= 0) {
            // Chercher un compte actif du demandeur
            $requesterAccounts = $this->accountModel->getAccessibleAccounts((int) $request['requester_id']);
            $activeOwn = array_values(array_filter(
                $requesterAccounts['own'],
                fn($a) => empty($a['disabled_at']) && empty($a['internal'])
            ));
            if (empty($activeOwn)) {
                $this->setFlash('danger', 'Le demandeur ne dispose pas de compte actif pour recevoir les fonds.');
                $this->redirect('/payment-requests');
                return;
            }
            $toAccountId = (int) $activeOwn[0]['id'];
        }

        $toAccount = $this->accountModel->find($toAccountId);
        if (!$toAccount) {
            $this->setFlash('danger', 'Compte destinataire introuvable.');
            $this->redirect('/payment-requests');
            return;
        }

        $motifLabel = 'Paiement demande #' . $requestId . ($request['motif'] !== '' ? ' — ' . $request['motif'] : '');

        // Débit sur le compte du payeur
        $debitTxId = $this->transactionModel->addTransaction(
            $fromAccountId,
            'expense',
            $amount,
            'Virement',
            $motifLabel,
            $userId
        );

        // Crédit sur le compte du demandeur
        $creditTxId = $this->transactionModel->addTransaction(
            $toAccountId,
            'income',
            $amount,
            'Virement',
            $motifLabel,
            $userId
        );

        // Enregistrement du virement
        $transferId = $this->transferModel->createTransfer(
            $fromAccountId,
            $toAccountId,
            $userId,
            $amount,
            $motifLabel,
            null,
            $debitTxId,
            $creditTxId
        );

        $this->requestModel->markPaid($requestId, $fromAccountId, $transferId);

        // Notifier le demandeur
        $payerName = $this->userModel->find($userId)['username'] ?? 'Quelqu\'un';
        $this->notifModel->notify(
            (int) $request['requester_id'],
            'payment_request_paid',
            sprintf('%s a payé votre demande de %s %s', $payerName, number_format($amount, 2, ',', ' '), $request['currency']),
            $request['motif'] !== '' ? $request['motif'] : null,
            '/payment-requests'
        );

        $this->setFlash('success', sprintf(
            'Paiement de %s %s effectué.',
            number_format($amount, 2, ',', ' '),
            $request['currency']
        ));
        $this->redirect('/payment-requests');
    }

    // ── Refus d'une demande reçue ────────────────────────────────────────────

    public function refuse(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId    = $this->getCurrentUserId();
        $requestId = (int) $id;

        $request = $this->requestModel->find($requestId);
        if (!$request || (int) $request['recipient_id'] !== $userId) {
            $this->setFlash('danger', 'Demande introuvable.');
            $this->redirect('/payment-requests');
            return;
        }

        if (!$this->requestModel->isPending($request)) {
            $this->setFlash('danger', 'Cette demande n\'est plus en attente.');
            $this->redirect('/payment-requests');
            return;
        }

        $this->requestModel->markRefused($requestId);

        // Notifier le demandeur
        $refuserName = $this->userModel->find($userId)['username'] ?? 'Quelqu\'un';
        $this->notifModel->notify(
            (int) $request['requester_id'],
            'payment_request_refused',
            sprintf('%s a refusé votre demande de %s %s', $refuserName, number_format((float)$request['amount'], 2, ',', ' '), $request['currency']),
            $request['motif'] !== '' ? $request['motif'] : null,
            '/payment-requests'
        );

        $this->setFlash('success', 'Demande refusée.');
        $this->redirect('/payment-requests');
    }

    // ── Annulation par le demandeur ─────────────────────────────────────────

    public function cancel(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId    = $this->getCurrentUserId();
        $requestId = (int) $id;

        $request = $this->requestModel->find($requestId);
        if (!$request || (int) $request['requester_id'] !== $userId) {
            $this->setFlash('danger', 'Demande introuvable.');
            $this->redirect('/payment-requests');
            return;
        }

        if (!$this->requestModel->isPending($request)) {
            $this->setFlash('danger', 'Cette demande ne peut plus être annulée.');
            $this->redirect('/payment-requests');
            return;
        }

        $this->requestModel->markCancelled($requestId);
        $this->setFlash('success', 'Demande annulée.');
        $this->redirect('/payment-requests');
    }

    // ── Recherche d'utilisateurs (AJAX) ────────────────────────────────────

    public function searchUsers(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();
        $q      = trim($_GET['q'] ?? '');

        if (strlen($q) < 2) {
            $this->json([]);
            return;
        }

        $users = $this->userModel->searchByQuery($q, 10);
        // Exclure l'utilisateur connecté
        $users = array_values(array_filter($users, fn($u) => (int) $u['id'] !== $userId));

        $results = array_map(fn($u) => [
            'id'       => (int) $u['id'],
            'username' => $u['username'],
        ], $users);

        $this->json($results);
    }
}
