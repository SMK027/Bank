<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AccountAccess;
use App\Models\DirectDebit;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;

class ModerationController extends Controller
{
    private Account $accountModel;
    private AccountAccess $accessModel;
    private User $userModel;
    private Transfer $transferModel;
    private Transaction $transactionModel;
    private DirectDebit $directDebitModel;

    public function __construct()
    {
        $this->accountModel     = new Account();
        $this->accessModel      = new AccountAccess();
        $this->userModel        = new User();
        $this->transferModel    = new Transfer();
        $this->transactionModel = new Transaction();
        $this->directDebitModel = new DirectDebit();
    }

    /**
     * Tableau de bord modérateur — liste tous les comptes.
     */
    public function index(): void
    {
        $this->requireModerator();

        $allAccounts = $this->accountModel->findAll('id', 'ASC');

        // Enrichir chaque compte avec son solde, son propriétaire et les utilisateurs avec accès partagé
        foreach ($allAccounts as &$acc) {
            $acc['balance'] = $this->accountModel->getBalance((int) $acc['id']);
            $owner = $this->userModel->find((int) $acc['user_id']);
            $acc['owner_name'] = $owner ? $owner['username'] : 'Inconnu';

            $sharedUsers = [];
            $accesses = $this->accessModel->getAccessesForAccount((int) $acc['id']);
            foreach ($accesses as $access) {
                $u = $this->userModel->find((int) $access['user_id']);
                if ($u) {
                    $sharedUsers[] = $u['username'];
                }
            }
            $acc['shared_users'] = $sharedUsers;
        }
        unset($acc);

        $this->render('moderation/index', [
            'title'       => 'Modération — Comptes',
            'allAccounts' => $allAccounts,
        ]);
    }

    /**
     * Geler un compte (POST).
     */
    public function freeze(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $accountId = (int) $id;
        $account   = $this->accountModel->find($accountId);

        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/moderation');
            return;
        }

        $this->accountModel->freezeAccount($accountId);
        $this->setFlash('success', 'Compte « ' . $account['name'] . ' » gelé avec succès.');
        $this->redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/moderation');
    }

    /**
     * Dégeler un compte (POST).
     */
    public function unfreeze(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $accountId = (int) $id;
        $account   = $this->accountModel->find($accountId);

        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect('/moderation');
            return;
        }

        $this->accountModel->unfreezeAccount($accountId);
        $this->setFlash('success', 'Compte « ' . $account['name'] . ' » dégelé avec succès.');
        $this->redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/moderation');
    }

    /**
     * Liste des utilisateurs avec gestion des rôles.
     */
    public function users(): void
    {
        $this->requireModerator();

        $allUsers = $this->userModel->findAll('id', 'ASC');
        $currentUserId = $this->getCurrentUserId();

        $this->render('moderation/users', [
            'title'         => 'Modération — Utilisateurs',
            'allUsers'      => $allUsers,
            'currentUserId' => $currentUserId,
        ]);
    }

    /**
     * Modifier le rôle d'un utilisateur (POST).
     */
    public function setRole(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $targetId = (int) $id;

        // Un modérateur ne peut pas changer son propre rôle
        if ($targetId === $this->getCurrentUserId()) {
            $this->setFlash('danger', 'Vous ne pouvez pas modifier votre propre rôle.');
            $this->redirect('/moderation/users');
            return;
        }

        $data = $this->getPostData(['role']);
        $role = $data['role'];

        $target = $this->userModel->find($targetId);
        if (!$target) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/moderation/users');
            return;
        }

        if (!$this->userModel->updateRole($targetId, $role)) {
            $this->setFlash('danger', 'Rôle invalide.');
            $this->redirect('/moderation/users');
            return;
        }

        $labels = ['user' => 'Utilisateur', 'moderator' => 'Modérateur'];
        $this->setFlash('success', 'Rôle de « ' . $target['username'] . ' » mis à jour : ' . ($labels[$role] ?? $role) . '.');
        $this->redirect('/moderation/users');
    }

    /**
     * Liste de tous les virements (espace modération).
     */
    public function transfers(): void
    {
        $this->requireModerator();

        $allUsers    = $this->userModel->findAll('username', 'ASC');
        $usersMap    = array_column($allUsers, null, 'id');
        $accountsMap = array_column($this->accountModel->findAll('id', 'ASC'), null, 'id');

        $rawTransfers      = $this->transferModel->findAll('created_at', 'DESC');
        $enrichedTransfers = [];
        foreach ($rawTransfers as $t) {
            $tUid    = (int) ($t['user_id'] ?? 0);
            $fromAcc = $accountsMap[$t['from_account_id']] ?? null;
            $toAcc   = $accountsMap[$t['to_account_id']]   ?? null;
            $tUser   = $usersMap[$tUid] ?? null;
            $enrichedTransfers[] = [
                'id'           => (int) $t['id'],
                'user_name'    => $tUser   ? ($tUser['username']  ?? 'Utilisateur #' . $tUid)            : 'Utilisateur #' . $tUid,
                'from_account' => $fromAcc ? ($fromAcc['name']    ?? 'Compte #' . $t['from_account_id']) : 'Compte #' . $t['from_account_id'],
                'to_account'   => $toAcc   ? ($toAcc['name']      ?? 'Compte #' . $t['to_account_id'])   : 'Compte #' . $t['to_account_id'],
                'amount'       => (float)  ($t['amount']       ?? 0),
                'motif'        => $t['motif']       ?? '',
                'status'       => $t['status']       ?? Transfer::STATUS_SUCCESS,
                'scheduled_at' => $t['scheduled_at'] ?? null,
                'executed_at'  => $t['executed_at']  ?? null,
                'created_at'   => $t['created_at']   ?? null,
            ];
        }

        $transfersJson = json_encode(
            $enrichedTransfers,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

        $tfAuthors = [];
        foreach ($enrichedTransfers as $tf) {
            if (!empty($tf['user_name']) && !in_array($tf['user_name'], $tfAuthors, true)) {
                $tfAuthors[] = $tf['user_name'];
            }
        }
        sort($tfAuthors);

        $this->render('moderation/transfers', [
            'title'         => 'Modération — Virements',
            'transfersJson' => $transfersJson,
            'tfAuthors'     => $tfAuthors,
            'totalCount'    => count($enrichedTransfers),
            'csrfToken'     => csrf_token(),
        ]);
    }

    /**
     * Annuler un virement (POST) — modérateurs uniquement.
     * Conditions : statut « scheduled » OU « success » exécuté il y a ≤ 7 jours.
     */
    public function cancelTransfer(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $transferId = (int) $id;
        $transfer   = $this->transferModel->find($transferId);

        if (!$transfer) {
            $this->setFlash('danger', 'Virement introuvable.');
            $this->redirect('/moderation/transfers');
            return;
        }

        if (!$this->transferModel->canCancel($transfer)) {
            $this->setFlash('danger', 'Ce virement ne peut pas être annulé : statut incompatible ou délai de 7 jours dépassé.');
            $this->redirect('/moderation/transfers');
            return;
        }

        $moderatorId = $this->getCurrentUserId();
        $amount      = (float) $transfer['amount'];
        $motif       = 'Annulation virement #' . $transferId;

        // Remboursement sur le compte émetteur (income)
        // user_id = 0 : action de modération → affiché comme "Modération" sur le compte
        $this->transactionModel->addTransaction(
            (int) $transfer['from_account_id'],
            'income',
            $amount,
            'Virement',
            $motif,
            0
        );

        // Récupération sur le compte destinataire (expense)
        $this->transactionModel->addTransaction(
            (int) $transfer['to_account_id'],
            'expense',
            $amount,
            'Virement',
            $motif,
            0
        );

        $this->transferModel->markCancelled($transferId);

        $this->setFlash('success', 'Virement #' . $transferId . ' annulé avec succès. Les soldes ont été rétablis.');
        $this->redirect('/moderation/transfers');
    }

    // =========================================================
    // PRÉLÈVEMENTS
    // =========================================================

    /**
     * Liste de tous les prélèvements.
     */
    public function directDebits(): void
    {
        $this->requireModerator();

        $accountsMap = array_column($this->accountModel->findAll('id', 'ASC'), null, 'id');
        $allUsers    = $this->userModel->findAll('username', 'ASC');
        $usersMap    = array_column($allUsers, null, 'id');

        $rawDebits = $this->directDebitModel->findAll('created_at', 'DESC');
        $enriched  = [];
        foreach ($rawDebits as $d) {
            $toAcc   = $accountsMap[$d['to_account_id']]   ?? null;
            $fromAcc = ($d['from_account_id'] !== null) ? ($accountsMap[$d['from_account_id']] ?? null) : null;
            $creator = $usersMap[$d['created_by']] ?? null;
            $enriched[] = [
                'id'              => (int) $d['id'],
                'mandate_number'  => $d['mandate_number'],
                'scheduled_at'    => $d['scheduled_at'],
                'executed_at'     => $d['executed_at'],
                'amount'          => (float) $d['amount'],
                'motif'           => $d['motif'] ?? '',
                'from_account'    => $fromAcc ? ($fromAcc['name'] ?? 'Compte #' . $d['from_account_id']) : 'Banque',
                'to_account'      => $toAcc   ? ($toAcc['name']   ?? 'Compte #' . $d['to_account_id'])   : 'Compte #' . $d['to_account_id'],
                'status'          => $d['status'],
                'created_by_name' => $creator ? $creator['username'] : 'Modération',
                'created_at'      => $d['created_at'],
            ];
        }

        $debitsJson = json_encode(
            $enriched,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

        $this->render('moderation/direct_debits', [
            'title'      => 'Modération — Prélèvements',
            'debitsJson' => $debitsJson,
            'totalCount' => count($enriched),
            'csrfToken'  => csrf_token(),
        ]);
    }

    /**
     * Formulaire de création d'un prélèvement (GET).
     */
    public function createDirectDebitForm(): void
    {
        $this->requireModerator();

        $allAccounts = $this->accountModel->findAll('name', 'ASC');

        $this->render('moderation/direct_debits_create', [
            'title'       => 'Modération — Nouveau prélèvement',
            'allAccounts' => $allAccounts,
            'csrfToken'   => csrf_token(),
        ]);
    }

    /**
     * Créer un prélèvement (POST).
     */
    public function createDirectDebit(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $data = $this->getPostData(['mandate_number', 'scheduled_at', 'amount', 'motif', 'from_account_id', 'to_account_id']);

        // Validation du numéro de mandat
        $mandateNumber = trim($data['mandate_number'] ?? '');
        if ($mandateNumber === '') {
            $this->setFlash('danger', 'Le numéro de mandat est obligatoire.');
            $this->redirect('/moderation/direct-debits/create');
            return;
        }

        // Validation de la date d'exécution
        $ts = strtotime($data['scheduled_at'] ?? '');
        if ($ts === false || $ts <= 0) {
            $this->setFlash('danger', 'La date d\'exécution est invalide.');
            $this->redirect('/moderation/direct-debits/create');
            return;
        }
        $scheduledAt = date('Y-m-d H:i:s', $ts);

        // Validation du montant
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant doit être strictement positif.');
            $this->redirect('/moderation/direct-debits/create');
            return;
        }

        // Compte destinataire (obligatoire)
        $toAccountId = (int) ($data['to_account_id'] ?? 0);
        $toAccount   = $toAccountId > 0 ? $this->accountModel->find($toAccountId) : null;
        if (!$toAccount) {
            $this->setFlash('danger', 'Compte destinataire invalide ou introuvable.');
            $this->redirect('/moderation/direct-debits/create');
            return;
        }

        // Compte émetteur (facultatif — vide ou 0 = banque)
        $fromAccountRaw = trim($data['from_account_id'] ?? '');
        $fromAccountId  = null;
        if ($fromAccountRaw !== '' && $fromAccountRaw !== '0') {
            $fromAccountId = (int) $fromAccountRaw;
            if (!$this->accountModel->find($fromAccountId)) {
                $this->setFlash('danger', 'Compte émetteur introuvable.');
                $this->redirect('/moderation/direct-debits/create');
                return;
            }
            if ($fromAccountId === $toAccountId) {
                $this->setFlash('danger', 'Le compte émetteur et le compte destinataire doivent être différents.');
                $this->redirect('/moderation/direct-debits/create');
                return;
            }
        }

        $motif = trim($data['motif'] ?? '') ?: null;

        $this->directDebitModel->createDirectDebit(
            $mandateNumber,
            $scheduledAt,
            $amount,
            $toAccountId,
            $fromAccountId,
            $motif,
            $this->getCurrentUserId()
        );

        $this->setFlash('success', sprintf(
            'Prélèvement de %s € planifié pour le %s sur « %s » (mandat %s).',
            number_format($amount, 2, ',', ' '),
            date('d/m/Y à H\hi', $ts),
            $toAccount['name'],
            $mandateNumber
        ));
        $this->redirect('/moderation/direct-debits');
    }

    /**
     * Annuler un prélèvement planifié (POST).
     */
    public function cancelDirectDebit(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $debitId     = (int) $id;
        $directDebit = $this->directDebitModel->find($debitId);

        if (!$directDebit) {
            $this->setFlash('danger', 'Prélèvement introuvable.');
            $this->redirect('/moderation/direct-debits');
            return;
        }

        if (!$this->directDebitModel->canCancel($directDebit)) {
            $this->setFlash('danger', 'Ce prélèvement ne peut pas être annulé (statut incompatible).');
            $this->redirect('/moderation/direct-debits');
            return;
        }

        $this->directDebitModel->markCancelled($debitId);
        $this->setFlash('success', 'Prélèvement #' . $debitId . ' (mandat ' . $directDebit['mandate_number'] . ') annulé.');
        $this->redirect('/moderation/direct-debits');
    }

    /**
     * Recherche de comptes pour l'autocomplete (GET, JSON).
     * Paramètre : ?q=terme_de_recherche
     */
    public function searchAccounts(): void
    {
        $this->requireModerator();

        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            $this->json([]);
            return;
        }

        $rows    = $this->accountModel->searchByQuery($q, 15);
        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id'       => (int) $row['id'],
                'label'    => $row['name'] . ' (' . $row['username'] . ') — ' . strtoupper((string) $row['currency']),
                'name'     => $row['name'],
                'currency' => $row['currency'],
                'owner'    => $row['username'],
            ];
        }

        $this->json($results);
    }
}
