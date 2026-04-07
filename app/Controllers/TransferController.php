<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Guardianship;
use App\Models\Notification;
use App\Models\RecurringTransfer;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;

class TransferController extends Controller
{
    private Account $accountModel;
    private Transaction $transactionModel;
    private Transfer $transferModel;
    private RecurringTransfer $recurringTransferModel;
    private User $userModel;
    private Notification $notifModel;
    private Guardianship $guardianshipModel;

    public function __construct()
    {
        $this->accountModel           = new Account();
        $this->transactionModel       = new Transaction();
        $this->transferModel          = new Transfer();
        $this->recurringTransferModel = new RecurringTransfer();
        $this->userModel              = new User();
        $this->notifModel             = new Notification();
        $this->guardianshipModel      = new Guardianship();
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        // Onglet Personnel : comptes propres + partagés (identique à un utilisateur normal)
        $accounts = $this->accountModel->getAccessibleAccounts($userId);
        foreach ($accounts['own'] as &$acc) {
            $acc['balance'] = $this->accountModel->getBalance((int) $acc['id']);
        }
        unset($acc);
        foreach ($accounts['shared'] as &$acc) {
            $acc['balance'] = $this->accountModel->getBalance((int) $acc['id']);
        }
        unset($acc);
        $ownAccounts    = $accounts['own'];
        $sharedAccounts = $accounts['shared'];

        // Onglet Modération : tous les comptes enrichis (modérateur uniquement)
        $allAccountsJson = null;
        $allUsers        = [];
        if ($this->isModerator()) {
            $allUsers    = $this->userModel->findAll('username', 'ASC');
            $usersMap    = array_column($allUsers, null, 'id');
            $enriched    = [];
            foreach ($this->accountModel->findAll('id', 'ASC') as $acc) {
                $uid  = (int) $acc['user_id'];
                $user = $usersMap[$uid] ?? null;
                $enriched[] = [
                    'id'           => (int) $acc['id'],
                    'name'         => $acc['name'],
                    'type'         => $acc['type'] ?? 'standard',
                    'currency'     => $acc['currency'],
                    'overdraft'    => (float) ($acc['overdraft'] ?? 0),
                    'balance'      => $this->accountModel->getBalance((int) $acc['id']),
                    'frozen'       => !empty($acc['frozen']),
                    'no_overdraft' => !Account::typeAllowsOverdraft($acc['type'] ?? 'standard'),
                    'user_id'      => $uid,
                    'user_name'    => $user ? ($user['username'] ?? 'Utilisateur #' . $uid) : 'Utilisateur #' . $uid,
                ];
            }
            $allAccountsJson = json_encode($enriched, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        }

        // Pré-sélection du compte émetteur si passé en GET
        $preselect = isset($_GET['from']) ? (int) $_GET['from'] : null;
        // Onglet actif par défaut si passé en GET (?tab=moderation)
        $activeTab = (isset($_GET['tab']) && $_GET['tab'] === 'moderation') ? 'moderation' : 'personal';

        $this->render('transfers/create', [
            'title'          => 'Virement entre comptes',
            'ownAccounts'    => $ownAccounts,
            'sharedAccounts' => $sharedAccounts,
            'preselect'      => $preselect,
            'isModerator'    => $this->isModerator(),
            'allAccountsJson'=> $allAccountsJson,
            'allUsers'       => $allUsers,
            'accountTypes'   => Account::TYPES,
            'activeTab'      => $activeTab,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $data   = $this->getPostData(['from_account_id', 'to_account_id', 'amount', 'motif', 'mode', 'scheduled_at', 'interval_days', 'first_execution_at']);

        $fromId      = (int) $data['from_account_id'];
        $toId        = (int) $data['to_account_id'];
        $isRecurring = (int) ($data['interval_days'] ?? 0) > 0;
        $modMode = $this->isModerator() && ($data['mode'] ?? '') === 'moderation';

        // Comptes identiques
        if ($fromId === $toId) {
            $this->setFlash('danger', 'Le compte émetteur et le compte destinataire doivent être différents.');
            $this->redirect('/transfers/create' . ($modMode ? '?tab=moderation' : ''));
            return;
        }

        // Montant strictement positif
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant doit être strictement positif.');
            $this->redirect('/transfers/create' . ($modMode ? '?tab=moderation' : ''));
            return;
        }

        // Vérifier l'accès au compte émetteur (bypass uniquement en mode modération)
        if (!$modMode && !$this->accountModel->hasAccess($fromId, $userId)) {
            $this->setFlash('danger', 'Accès refusé au compte émetteur.');
            $this->redirect('/transfers/create');
            return;
        }

        // Vérifier que le compte destinataire existe et est accessible
        $toAccount = $this->accountModel->find($toId);
        if (!$toAccount || (!$modMode && !$this->accountModel->hasAccess($toId, $userId))) {
            $this->setFlash('danger', 'Compte destinataire introuvable ou accès refusé.');
            $this->redirect('/transfers/create' . ($modMode ? '?tab=moderation' : ''));
            return;
        }

        $fromAccount = $this->accountModel->find($fromId);
        if (!$fromAccount) {
            $this->setFlash('danger', 'Compte émetteur introuvable.');
            $this->redirect('/transfers/create' . ($modMode ? '?tab=moderation' : ''));
            return;
        }

        // Bloquer le virement si le compte émetteur est gelé
        if ($this->accountModel->isFrozen($fromId)) {
            $this->setFlash('danger', sprintf(
                'Virement impossible : le compte émetteur « %s » est gelé. Les virements sortants sont bloqués.',
                $fromAccount['name'] ?? ''
            ));
            $this->redirect('/transfers/create?tab=' . ($modMode ? 'moderation' : 'personal'));
            return;
        }
        // Bloquer le virement sortant si le compte émetteur est désactivé (sauf modération)
        if ($this->accountModel->isDisabled($fromId) && !$modMode) {
            $this->setFlash('danger', sprintf(
                'Virement impossible : le compte « %s » est en cours de résiliation. Les virements sortants sont bloqués.',
                $fromAccount['name'] ?? ''
            ));
            $this->redirect('/transfers/create?tab=personal');
            return;
        }        $balance     = $this->accountModel->getBalance($fromId);
        $overdraft   = (float) ($fromAccount['overdraft'] ?? 0);
        $accountType = $fromAccount['type'] ?? 'standard';

        // Vérifier si le solde sera insuffisant
        $newBalance = $balance - $amount;
        if ($newBalance < -$overdraft) {
            if (!Account::typeAllowsOverdraft($accountType)) {
                $this->setFlash('danger', sprintf(
                    'Virement impossible : le compte émetteur (%s) ne permet pas le solde négatif. Solde disponible : %s %s.',
                    $fromAccount['name'],
                    number_format($balance, 2, ',', ' '),
                    $fromAccount['currency']
                ));
            } else {
                $this->setFlash('danger', sprintf(
                    'Fonds insuffisants : le virement dépasserait le découvert autorisé. Solde disponible : %s %s (découvert : %s %s).',
                    number_format($balance, 2, ',', ' '),
                    $fromAccount['currency'],
                    number_format($overdraft, 2, ',', ' '),
                    $fromAccount['currency']
                ));
            }
            $this->redirect('/transfers/create?tab=' . ($modMode ? 'moderation' : 'personal'));
            return;
        }

        $motif = trim($data['motif'] ?: '');

        // ── Virement récurrent ────────────────────────────────────────────────
        if ($isRecurring) {
            $intervalDays = (int) $data['interval_days'];
            if ($intervalDays < 1) {
                $this->setFlash('danger', "L'intervalle entre chaque virement doit être d'au moins 1 jour.");
                $this->redirect('/transfers/create' . ($modMode ? '?tab=moderation' : ''));
                return;
            }
            $rawFirst = trim($data['first_execution_at'] ?? '');
            $dtFirst  = \DateTime::createFromFormat('Y-m-d\TH:i', $rawFirst)
                     ?: \DateTime::createFromFormat('Y-m-d H:i:s', $rawFirst)
                     ?: \DateTime::createFromFormat('Y-m-d H:i', $rawFirst);
            if (!$dtFirst || $dtFirst->getTimestamp() <= time()) {
                $this->setFlash('danger', 'La date du premier virement doit être dans le futur.');
                $this->redirect('/transfers/create' . ($modMode ? '?tab=moderation' : ''));
                return;
            }
            $firstExecutionAt = $dtFirst->format('Y-m-d H:i:s');

            $this->recurringTransferModel->createRecurringTransfer(
                $fromId,
                $toId,
                $userId,
                $amount,
                $motif,
                $intervalDays,
                $firstExecutionAt
            );

            AuditLog::log($userId, AuditLog::ACTION_TRANSFER_RECURRING_CREATE, [
                'amount'        => $amount,
                'interval_days' => $intervalDays,
                'from_account'  => $fromAccount['name'],
                'to_account'    => $toAccount['name'],
            ], targetAccountId: $fromId);

            $this->notifModel->notify(
                $userId,
                'recurring_transfer_created',
                'Virement récurrent créé',
                sprintf(
                    'Virement de %s %s de « %s » vers « %s » toutes les %d jour(s), premier le %s.',
                    number_format($amount, 2, ',', ' '),
                    $fromAccount['currency'],
                    $fromAccount['name'],
                    $toAccount['name'],
                    $intervalDays,
                    $dtFirst->format('d/m/Y à H\hi')
                ),
                '/transfers/recurring'
            );

            $this->setFlash('success', sprintf(
                'Virement récurrent créé (%s %s de « %s » vers « %s » tous les %d jour(s), premier le %s).',
                number_format($amount, 2, ',', ' '),
                $fromAccount['currency'],
                $fromAccount['name'],
                $toAccount['name'],
                $intervalDays,
                $dtFirst->format('d/m/Y à H\hi')
            ));
            $this->redirect('/accounts/' . $fromId);
            return;
        }
        // ─────────────────────────────────────────────────────────────────────

        $scheduledAt = null;
        if (!empty($data['scheduled_at'])) {
            $ts = strtotime($data['scheduled_at']);
            if ($ts !== false && $ts > time()) {
                $scheduledAt = date('Y-m-d H:i:s', $ts);
            }
        }
        $label = 'Virement' . ($motif !== '' ? ' — ' . $motif : '');

        // Vérifier le plafond d'épargne du compte destinataire
        $toCap = (float) ($toAccount['cap'] ?? 0);
        if (Account::typeHasCap($toAccount['type'] ?? '') && $toCap > 0) {
            $toBalance = $this->accountModel->getFutureBalance($toId);
            if ($toBalance + $amount > $toCap) {
                $this->setFlash('danger', sprintf(
                    'Virement impossible : le compte destinataire « %s » est un compte épargne plafonné à %s %s. Solde actuel : %s %s.',
                    $toAccount['name'],
                    number_format($toCap, 2, ',', ' '),
                    $toAccount['currency'],
                    number_format($toBalance, 2, ',', ' '),
                    $toAccount['currency']
                ));
                $this->redirect('/transfers/create?tab=' . ($modMode ? 'moderation' : 'personal'));
                return;
            }
        }

        $debitTxId = $this->transactionModel->addTransaction(
            $fromId,
            'expense',
            $amount,
            'Virement',
            $label,
            $userId,
            $scheduledAt
        );

        // Crédit sur le compte destinataire
        $creditTxId = $this->transactionModel->addTransaction(
            $toId,
            'income',
            $amount,
            'Virement',
            $label,
            $userId,
            $scheduledAt
        );

        // Enregistrement du virement en base
        $transferId = $this->transferModel->createTransfer(
            $fromId,
            $toId,
            $userId,
            $amount,
            $motif,
            $scheduledAt,
            $debitTxId,
            $creditTxId
        );

        // Vérification du franchissement du seuil d'alerte (virement immédiat uniquement)
        if ($scheduledAt === null && Account::crossedAlertThreshold($fromAccount, $balance, $balance - $amount)) {
            $this->notifModel->sendBalanceAlert(
                (int) $fromAccount['user_id'],
                $fromAccount,
                $balance - $amount,
                $this->guardianshipModel
            );
        }

        if ($scheduledAt !== null) {
            $this->setFlash('success', sprintf(
                'Virement de %s %s planifié pour le %s de « %s » vers « %s ».',
                number_format($amount, 2, ',', ' '),
                $fromAccount['currency'],
                date('d/m/Y à H\hi', strtotime($scheduledAt)),
                $fromAccount['name'],
                $toAccount['name']
            ));
            // Alerte modérateurs : virement planifié
            $this->notifModel->notifyModerators(
                'mod_transfer_pending',
                'Virement planifié en attente',
                sprintf(
                    'Virement de %s %s de « %s » vers « %s » prévu le %s.',
                    number_format($amount, 2, ',', ' '),
                    $fromAccount['currency'],
                    $fromAccount['name'],
                    $toAccount['name'],
                    date('d/m/Y', strtotime($scheduledAt))
                ),
                '/moderation/transfers'
            );
        } else {
            $this->setFlash('success', sprintf(
                'Virement de %s %s effectué de « %s » vers « %s ».',
                number_format($amount, 2, ',', ' '),
                $fromAccount['currency'],
                $fromAccount['name'],
                $toAccount['name']
            ));
            // Notifier le propriétaire du compte destinataire s'il est différent de l'émetteur
            $toOwner = $this->userModel->find((int) $toAccount['user_id']);
            if ($toOwner && (int) $toOwner['id'] !== $userId) {
                $this->notifModel->notify(
                    (int) $toOwner['id'],
                    'transfer_received',
                    sprintf('Virement reçu : %s %s', number_format($amount, 2, ',', ' '), $fromAccount['currency']),
                    sprintf('De « %s »%s.', $fromAccount['name'], $motif !== '' ? ' — ' . $motif : ''),
                    '/accounts/' . $toId
                );
            }
        }
        AuditLog::log($userId, AuditLog::ACTION_TRANSFER_CREATE, [
            'transfer_id'  => $transferId,
            'amount'       => $amount,
            'from_account' => $fromAccount['name'],
            'to_account'   => $toAccount['name'],
        ], targetAccountId: $fromId);
        $this->redirect('/accounts/' . $fromId);
    }

    /**
     * Affiche la liste des virements récurrents de l'utilisateur courant.
     */
    public function listRecurring(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $all = $this->recurringTransferModel->getByUser($userId);

        // Enrichir avec les noms de comptes
        foreach ($all as &$r) {
            $fromAcc = $this->accountModel->find((int) $r['from_account_id']);
            $toAcc   = $this->accountModel->find((int) $r['to_account_id']);
            $r['from_account_name'] = $fromAcc['name'] ?? ('Compte #' . $r['from_account_id']);
            $r['to_account_name']   = $toAcc['name']   ?? ('Compte #' . $r['to_account_id']);
        }
        unset($r);

        $this->render('transfers/recurring', [
            'title'   => 'Virements récurrents',
            'items'   => $all,
        ]);
    }

    /**
     * Annule un virement récurrent (POST).
     */
    public function cancelRecurring(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $recId  = (int) $id;
        $userId = $this->getCurrentUserId();
        $record = $this->recurringTransferModel->find($recId);

        if (!$record) {
            $this->setFlash('danger', 'Virement récurrent introuvable.');
            $this->redirect('/transfers/recurring');
            return;
        }

        // Seul le créateur ou un modérateur peut annuler
        if ((int) $record['user_id'] !== $userId && !$this->isModerator()) {
            $this->setFlash('danger', 'Vous ne pouvez pas annuler ce virement récurrent.');
            $this->redirect('/transfers/recurring');
            return;
        }

        if (!$this->recurringTransferModel->canCancel($record)) {
            $this->setFlash('danger', 'Ce virement récurrent est déjà annulé.');
            $this->redirect('/transfers/recurring');
            return;
        }

        $this->recurringTransferModel->cancel($recId);
        AuditLog::log($userId, AuditLog::ACTION_TRANSFER_RECURRING_CANCEL, [
            'amount'        => $record['amount'],
            'interval_days' => $record['interval_days'],
        ], targetAccountId: (int) $record['from_account_id']);
        $this->setFlash('success', 'Virement récurrent annulé.');
        $redirectTo = $_POST['redirect_to'] ?? '';
        // Valide que la redirection est un chemin interne (pas une URL externe)
        if ($redirectTo !== '' && preg_match('#^/[a-zA-Z0-9/_#-]*$#', $redirectTo)) {
            $this->redirect($redirectTo);
        } else {
            $this->redirect('/transfers/recurring');
        }
    }
}

