<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Guardianship;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;

/**
 * Gestion des opérations de caisse sur les comptes de type « coffre d'entreprise ».
 *
 * Deux opérations :
 *  – Encaissement (income) : réception d'espèces (recette, remboursement, etc.)
 *  – Décaissement (expense) : sortie d'espèces (règlement fournisseur, remise en banque, etc.)
 *
 * Règles :
 *  – Réservé aux comptes de type 'vault'.
 *  – Pas de découvert autorisé : un décaissement ne peut pas faire passer le solde en négatif.
 *  – Accessible au propriétaire du compte et aux modérateurs.
 */
class VaultController extends Controller
{
    private Account     $accountModel;
    private Transaction $transactionModel;
    private Notification $notifModel;
    private Guardianship $guardianshipModel;

    public function __construct()
    {
        $this->accountModel      = new Account();
        $this->transactionModel  = new Transaction();
        $this->notifModel        = new Notification();
        $this->guardianshipModel = new Guardianship();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Formulaire standalone (GET)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Affiche le formulaire dédié encaissement / décaissement.
     * GET /accounts/{id}/vault
     */
    public function form(string $id): void
    {
        $this->requireAuth();

        $accountId = (int) $id;
        $userId    = $this->getCurrentUserId();

        $account = $this->accountModel->find($accountId);
        if (!$account || ($account['type'] ?? '') !== 'vault') {
            $this->setFlash('danger', 'Ce compte n\'est pas un coffre d\'entreprise.');
            $this->redirect('/dashboard');
            return;
        }

        $isOwner    = $this->accountModel->isOwner($accountId, $userId);
        $isModerator = $this->isModerator();

        if (!$isOwner && !$isModerator) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $eventBlockedReason = Account::operationBlockedReason($account);
        if ($eventBlockedReason !== null) {
            $this->setFlash('danger', $eventBlockedReason);
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $balance = $this->accountModel->getBalance($accountId);

        $this->render('vault/form', [
            'title'           => 'Caisse — ' . $account['name'],
            'account'         => $account,
            'balance'         => $balance,
            'isModerator'     => $isModerator,
            'isOwner'         => $isOwner,
            'incomeCategories' => Transaction::VAULT_INCOME_CATEGORIES,
            'expenseCategories' => Transaction::VAULT_EXPENSE_CATEGORIES,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Traitement de l'opération (POST)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Enregistre un encaissement ou un décaissement.
     * POST /accounts/{id}/vault
     */
    public function operate(string $id): void
    {
        $this->requireAuth();
        $this->requireFeature('transactions.create');
        $this->validateCSRF();

        $accountId = (int) $id;
        $userId    = $this->getCurrentUserId();

        $account = $this->accountModel->find($accountId);
        if (!$account || ($account['type'] ?? '') !== 'vault') {
            $this->setFlash('danger', 'Ce compte n\'est pas un coffre d\'entreprise.');
            $this->redirect('/dashboard');
            return;
        }

        $isOwner     = $this->accountModel->isOwner($accountId, $userId);
        $isModerator = $this->isModerator();

        if (!$isOwner && !$isModerator) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        if ($this->accountModel->isFrozen($accountId)) {
            $this->setFlash('danger', 'Ce coffre est gelé. Aucune opération n\'est possible.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        if ($this->accountModel->isDisabled($accountId) && !$isModerator) {
            $this->setFlash('danger', 'Ce coffre est en cours de résiliation. Aucune opération n\'est possible.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $data = $this->getPostData(['operation_type', 'amount', 'category', 'tiers', 'comment', 'operation_date']);

        // ── Validation du type d'opération ───────────────────────────────────
        $operationType = $data['operation_type'] ?? '';
        if (!in_array($operationType, ['income', 'expense'], true)) {
            $this->setFlash('danger', 'Type d\'opération invalide.');
            $this->redirect('/accounts/' . $accountId . '/vault');
            return;
        }

        // ── Validation du montant ─────────────────────────────────────────────
        $amount = abs((float) ($data['amount'] ?? 0));
        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant doit être strictement positif.');
            $this->redirect('/accounts/' . $accountId . '/vault');
            return;
        }

        // ── Validation de la catégorie ────────────────────────────────────────
        $category = trim($data['category'] ?? '');
        if (!Transaction::isValidVaultCategory($category, $operationType)) {
            $this->setFlash('danger', 'Catégorie invalide pour ce type d\'opération.');
            $this->redirect('/accounts/' . $accountId . '/vault');
            return;
        }

        // ── Contrôle du solde pour les décaissements ─────────────────────────
        if ($operationType === 'expense') {
            $balance = $this->accountModel->getBalance($accountId);
            if ($balance - $amount < 0) {
                $this->setFlash('danger', sprintf(
                    'Décaissement impossible : solde insuffisant (%s %s). Le coffre ne peut pas être à découvert.',
                    number_format($balance, 2, ',', ' '),
                    $account['currency']
                ));
                $this->redirect('/accounts/' . $accountId . '/vault');
                return;
            }
        }

        // ── Construction du commentaire ───────────────────────────────────────
        $tiers   = trim($data['tiers'] ?? '');
        $comment = trim($data['comment'] ?? '');

        $fullComment = '';
        if ($tiers !== '') {
            $fullComment .= $tiers;
        }
        if ($comment !== '') {
            $fullComment .= ($fullComment !== '' ? ' — ' : '') . $comment;
        }
        if ($fullComment === '') {
            $fullComment = $operationType === 'income' ? 'Encaissement' : 'Décaissement';
        }
        $fullComment = mb_substr($fullComment, 0, 255);

        // ── Date d'opération (optionnelle) ────────────────────────────────────
        $scheduledAt = null;
        $rawDate     = trim($data['operation_date'] ?? '');
        if ($rawDate !== '') {
            $dt = parse_datetime_input($rawDate);
            if (!$dt) {
                $this->setFlash('danger', 'Date d\'opération invalide (format attendu : jj/mm/aaaa hh:mm).');
                $this->redirect('/accounts/' . $accountId . '/vault');
                return;
            }
            // Uniquement le passé ou maintenant ; les modérateurs peuvent aussi antidater
            if (!$isModerator && $dt->getTimestamp() > time()) {
                $this->setFlash('danger', 'La date de l\'opération ne peut pas être dans le futur.');
                $this->redirect('/accounts/' . $accountId . '/vault');
                return;
            }
            // On utilise scheduled_at = null et on force la created_at via un champ séparé
            // Ici on stocke simplement en created_at via update post-insert
        }

        // ── Enregistrement de la transaction ─────────────────────────────────
        $txId = $this->transactionModel->addTransaction(
            $accountId,
            $operationType,
            $amount,
            $category,
            $fullComment,
            (int) $userId
        );

        // Antidatage si demandé (modérateur uniquement hors date future)
        if ($rawDate !== '' && isset($dt)) {
            $this->transactionModel->update($txId, [
                'created_at' => $dt->format('Y-m-d H:i:s'),
            ]);
        }

        // ── Audit ─────────────────────────────────────────────────────────────
        $action = $operationType === 'income'
            ? AuditLog::ACTION_VAULT_CASH_IN
            : AuditLog::ACTION_VAULT_CASH_OUT;

        AuditLog::log(
            (int) $userId,
            $action,
            [
                'amount'    => $amount,
                'category'  => $category,
                'tiers'     => $tiers ?: null,
                'comment'   => $comment ?: null,
                'tx_id'     => $txId,
            ],
            targetUserId:    (int) $account['user_id'],
            targetAccountId: $accountId
        );

        // ── Notification au propriétaire ─────────────────────────────────────
        $owner = (int) $account['user_id'];
        if ($owner !== (int) $userId) {
            // Un modérateur a réalisé l'opération — notifier le propriétaire
            $notifBody = sprintf(
                'Un %s de %s %s a été enregistré sur votre coffre « %s »%s.',
                $operationType === 'income' ? 'encaissement' : 'décaissement',
                number_format($amount, 2, ',', ' '),
                $account['currency'],
                $account['name'],
                $tiers !== '' ? ' (tiers : ' . $tiers . ')' : ''
            );
            $this->notifModel->notify(
                $owner,
                $operationType === 'income' ? 'vault_cash_in' : 'vault_cash_out',
                ucfirst($operationType === 'income' ? 'Encaissement' : 'Décaissement') . ' coffre',
                $notifBody,
                '/accounts/' . $accountId
            );
        }

        // ── Réponse ───────────────────────────────────────────────────────────
        $label = $operationType === 'income' ? 'Encaissement' : 'Décaissement';
        $this->setFlash('success', sprintf(
            '%s de %s %s enregistré sur « %s ».',
            $label,
            number_format($amount, 2, ',', ' '),
            $account['currency'],
            $account['name']
        ));

        $this->redirect('/accounts/' . $accountId);
    }
}
