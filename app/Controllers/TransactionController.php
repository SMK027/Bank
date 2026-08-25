<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\Check;
use App\Models\Checkbook;
use App\Models\DeferredDebit;
use App\Models\Guardianship;
use App\Models\Notification;
use App\Models\OverdraftAuthorization;
use App\Models\PaymentCard;
use App\Models\Transaction;
use App\Models\User;

class TransactionController extends Controller
{
    /**
     * Date à partir de laquelle une carte bancaire est obligatoire
     * lors de l'enregistrement d'un débit différé.
     * Correspond à l'introduction de la liaison carte–débit différé (migration 049).
     */
    public const CARD_REQUIRED_SINCE = '2026-05-01 00:00:00';

    private Account $accountModel;
    private Transaction $transactionModel;
    private User $userModel;
    private Notification $notifModel;
    private Guardianship $guardianshipModel;
    private DeferredDebit $deferredDebitModel;
    private PaymentCard $cardModel;
    private Checkbook $checkbookModel;
    private Check $checkModel;

    public function __construct()
    {
        $this->accountModel       = new Account();
        $this->transactionModel   = new Transaction();
        $this->userModel          = new User();
        $this->notifModel         = new Notification();
        $this->guardianshipModel  = new Guardianship();
        $this->deferredDebitModel = new DeferredDebit();
        $this->cardModel          = new PaymentCard();
        $this->checkbookModel     = new Checkbook();
        $this->checkModel         = new Check();
    }

    public function create(string $accountId): void
    {
        $this->requireAuth();
        $this->requireFeature('transactions.create');
        $this->validateCSRF();

        $accId = (int) $accountId;
        $userId = $this->getCurrentUserId();

        if (!$this->isModerator() && !$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        if (!$this->ensureEventAccountOperational($accId, '/accounts/' . $accountId)) {
            return;
        }

        $data = $this->getPostData(['type', 'amount', 'category', 'comment', 'scheduled_at', 'card_id', 'checkbook_id', 'check_payee']);

        if (empty($data['type']) || empty($data['amount']) || empty($data['category'])) {
            $this->setFlash('danger', 'Le type, le montant et la catégorie sont requis.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        if (!in_array($data['type'], ['income', 'expense'], true)) {
            $this->setFlash('danger', 'Type de transaction invalide.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        if (!Transaction::isValidCategory($data['category'], $data['type'])) {
            $this->setFlash('danger', 'Catégorie invalide pour ce type d\'opération.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $amount = abs((float) $data['amount']);
        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant doit être positif.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Traiter la date programmée
        $scheduledAt = null;
        if (!empty($data['scheduled_at'])) {
            $dt = parse_datetime_input($data['scheduled_at']);
            if (!$dt) {
                $this->setFlash('danger', 'La date programmée est invalide (format attendu : jj/mm/aaaa hh:mm).');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            if (!$this->isModerator() && $dt->getTimestamp() <= time()) {
                $this->setFlash('danger', 'La date programmée doit être dans le futur.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            $scheduledAt = $dt->format('Y-m-d H:i:s');
        }

        // Bloquer les opérations sortantes si le compte est gelé
        if ($data['type'] === 'expense' && $this->accountModel->isFrozen($accId)) {
            $this->setFlash('danger', 'Ce compte est gelé. Les opérations sortantes sont impossibles.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Bloquer toutes les opérations manuelles si le compte est désactivé (hors modérateurs)
        if ($this->accountModel->isDisabled($accId) && !$this->isModerator()) {
            $this->setFlash('danger', 'Ce compte est en cours de résiliation. Aucune opération manuelle n\'est possible.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Vérifier le découvert pour les dépenses (ignoré pour les modérateurs)
        // On utilise le solde futur (incl. opérations programmées) pour le contrôle
        if ($data['type'] === 'expense' && !$this->isModerator()) {
            $account   = $this->accountModel->find($accId);
            $balance   = $this->accountModel->getFutureBalance($accId);
            $overdraft = (float) ($account['overdraft'] ?? 0);
            $accountType = $account['type'] ?? 'standard';

            // Intégrer l'autorisation de dépassement émise par la modération
            $authModel  = new OverdraftAuthorization();
            $extraLimit = $authModel->getExtraLimitForAccount($accId);
            $overdraft += $extraLimit;
            $hasAuth    = $extraLimit > 0.0;

            $wouldExceed = ($balance - $amount) < -$overdraft;

            if ($wouldExceed) {
                // Bloquer définitivement si le type de compte interdit le découvert
                // et qu'aucune autorisation de modération n'est active
                if (!\App\Models\Account::typeAllowsOverdraft($accountType) && !$hasAuth) {
                    $this->setFlash('danger', 'Opération impossible : ce type de compte (' . (\App\Models\Account::TYPES[$accountType]['label'] ?? $accountType) . ') ne permet pas le solde négatif.');
                    $this->redirect('/accounts/' . $accountId);
                    return;
                }

                $force = trim($_POST['force_overdraft'] ?? '') === '1';
                if (!$force) {
                    $newBalance = number_format($balance - $amount, 2, ',', ' ');
                    $this->setFlash('danger', sprintf(
                        'Découvert dépassé. Solde prévu : %s %s. Cochez la case « Forcer l\'opération » pour confirmer.',
                        $newBalance,
                        $account['currency'] ?? ''
                    ));
                    $this->redirect('/accounts/' . $accountId);
                    return;
                }
            }
        }

        // Vérifier le plafond d'épargne pour les entrées
        if ($data['type'] === 'income') {
            $account = $account ?? $this->accountModel->find($accId);
            if ($account && Account::typeHasCap($account['type'] ?? '')) {
                $tCap = (float) ($account['cap'] ?? 0);
                if ($tCap > 0) {
                    $currentBalance = $this->accountModel->getFutureBalance($accId);
                    if ($currentBalance + $amount > $tCap) {
                        $this->setFlash('danger', sprintf(
                            'Opération impossible : ce compte épargne a un plafond de %s %s. Solde actuel : %s %s.',
                            number_format($tCap, 2, ',', ' '),
                            $account['currency'],
                            number_format($currentBalance, 2, ',', ' '),
                            $account['currency']
                        ));
                        $this->redirect('/accounts/' . $accountId);
                        return;
                    }
                }
            }
        }

        // Carte bancaire associée (facultatif, dépenses uniquement)
        $cardId = null;
        if ($data['type'] === 'expense' && !empty($data['card_id'])) {
            $cardId = (int) $data['card_id'];
            $card   = $this->cardModel->find($cardId);
            if (!$card || (int) $card['account_id'] !== $accId) {
                $this->setFlash('danger', 'Carte introuvable ou non associée à ce compte.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            // Vérification du plafond mensuel (sans bypass possible)
            if (isset($card['monthly_limit']) && $card['monthly_limit'] !== null) {
                $limit   = (float) $card['monthly_limit'];
                $already = $this->cardModel->getMonthlyTotal($cardId);
                if ($already + $amount > $limit) {
                    $remaining = max(0.0, $limit - $already);
                    $this->setFlash('danger', sprintf(
                        'Plafond mensuel insuffisant pour la carte %s. Déjà utilisé : %.2f € / %.2f €. Montant demandé : %.2f €. Disponible : %.2f €.',
                        PaymentCard::mask($card['card_number']),
                        $already, $limit, $amount, $remaining
                    ));
                    $this->redirect('/accounts/' . $accountId);
                    return;
                }
            }
        }

        // Seuil d'alerte : capturer le solde actuel avant l'opération (dépense immédiate uniquement)
        $balanceBefore = ($data['type'] === 'expense' && $scheduledAt === null)
            ? $this->accountModel->getBalance($accId)
            : 0.0;

        // ── Paiement par chèque ──────────────────────────────────────────────
        $checkbookId = ($data['type'] === 'expense' && !empty($data['checkbook_id']))
            ? (int) $data['checkbook_id']
            : null;

        if ($checkbookId !== null) {
            // Valider le chéquier (actif, lié au compte)
            $checkbook = $this->checkbookModel->find($checkbookId);
            if (!$checkbook
                || (int) $checkbook['account_id'] !== $accId
                || $checkbook['status'] !== 'active'
            ) {
                $this->setFlash('danger', 'Chéquier invalide ou en opposition.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }

            // Carte et chèque sont mutuellement exclusifs
            $cardId = null;

            // La transaction est enregistrée en attente (scheduled_at sentinel)
            $scheduledAt = Check::PENDING_SCHEDULED_AT;

            $payee = mb_substr(trim($data['check_payee'] ?? ''), 0, 150);

            // 1. Créer la transaction en attente (check_id = null pour l'instant — mis à jour après)
            $txId = $this->transactionModel->addTransaction(
                $accId,
                'expense',
                $amount,
                $data['category'],
                $data['comment'],
                $userId,
                $scheduledAt,
                null // card_id
            );

            // 2. Créer le chèque lié à cette transaction
            $checkId = $this->checkModel->emit($checkbookId, $amount, $payee, $txId);

            // 3. Lier le chèque à la transaction
            $this->transactionModel->update($txId, ['check_id' => $checkId]);

            $nextNum = $this->checkModel->find($checkId)['check_number'] ?? '?';
            $this->setFlash('success', sprintf(
                'Dépense enregistrée par chèque n°%s — en attente de confirmation d\'encaissement.',
                $nextNum
            ));
            $this->redirect('/accounts/' . $accountId);
            return;
        }
        // ────────────────────────────────────────────────────────────────────

        $this->transactionModel->addTransaction(
            $accId,
            $data['type'],
            $amount,
            $data['category'],
            $data['comment'],
            $userId,
            $scheduledAt,
            $cardId
        );

        // Vérification du franchissement du seuil d'alerte (dépense immédiate uniquement)
        if ($data['type'] === 'expense' && $scheduledAt === null) {
            $alertAccount = $account ?? $this->accountModel->find($accId);
            if (Account::crossedAlertThreshold($alertAccount, $balanceBefore, $balanceBefore - $amount)) {
                $this->notifModel->sendBalanceAlert(
                    (int) $alertAccount['user_id'],
                    $alertAccount,
                    $balanceBefore - $amount,
                    $this->guardianshipModel
                );
            }
        }

        $label = $data['type'] === 'income' ? 'Entrée' : 'Dépense';
        $msg   = $scheduledAt
            ? $label . ' programmée pour le ' . date('d/m/Y H:i', strtotime($scheduledAt)) . '.'
            : $label . ' enregistrée avec succès.';
        $this->setFlash('success', $msg);
        $this->redirect('/accounts/' . $accountId);
    }

    public function deleteTransaction(string $accountId, string $transactionId): void
    {
        $this->requireAuth();
        $this->requireFeature('transactions.edit');
        $this->validateCSRF();

        $accId  = (int) $accountId;
        $txId   = (int) $transactionId;
        $userId = $this->getCurrentUserId();

        if (!$this->isModerator() && !$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        if (!$this->ensureEventAccountOperational($accId, '/accounts/' . $accountId)) {
            return;
        }

        $transaction = $this->transactionModel->find($txId);
        if (!$transaction || (int) $transaction['account_id'] !== $accId) {
            $this->setFlash('danger', 'Transaction introuvable.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Utilisateurs : uniquement les transactions des 7 derniers jours
        if (!$this->isModerator()) {
            $createdAt = strtotime($transaction['created_at'] ?? '');
            if (!$createdAt || (time() - $createdAt) > 7 * 86400) {
                $this->setFlash('danger', 'Cette opération date de plus de 7 jours et ne peut plus être supprimée.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
        }

        if ($this->transactionModel->getProtectedIds([$txId]) !== []) {
            $this->setFlash('danger', 'Cette transaction est liée à un virement ou un prélèvement automatique. Pour l\'annuler, utilisez la gestion dédiée (annulation du virement ou rejet du prélèvement).');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Transactions issues d'un débit différé exécuté :
        // non supprimables par les utilisateurs normaux (traçabilité comptable).
        if (!$this->isModerator() && $this->deferredDebitModel->getExecutedTransactionIds([$txId]) !== []) {
            $this->setFlash('danger', 'Cette opération est liée à un débit différé exécuté et ne peut pas être supprimée.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Opérations issues d'une action de modération (TPE, annulation de
        // virement, annulation/remboursement de crédit, rejet de prélèvement…) :
        // non supprimables afin de garantir la traçabilité comptable.
        if (Transaction::isModerationOnly($transaction)) {
            $this->setFlash('danger', 'Cette opération a été générée par la modération et ne peut pas être supprimée manuellement.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Restituer le quota mensuel carte si la transaction supprimée est
        // une dépense avec carte associée (schedulée ou immédiate).
        if (($transaction['type'] ?? '') === 'expense' && !empty($transaction['card_id'])) {
            $this->cardModel->restoreMonthlySpent(
                (int) $transaction['card_id'],
                (float) $transaction['amount'],
                isCancellation: true
            );
        }

        $this->transactionModel->delete($txId);
        $this->setFlash('success', 'Transaction supprimée.');
        $this->redirect('/accounts/' . $accountId);
    }

    // ── MODIFICATION DE TRANSACTIONS ─────────────────────────────────────────

    /**
     * Modifier les dates d'une transaction exécutée.
     * Utilisateurs : dans les 7 jours. Modérateurs : toutes.
     * Transactions liées (virements/prélèvements) : non modifiables.
     */
    public function editTransaction(string $accountId, string $transactionId): void
    {
        $this->requireAuth();
        $this->requireFeature('transactions.edit');
        $this->validateCSRF();

        $accId = (int) $accountId;
        $txId  = (int) $transactionId;
        $userId = $this->getCurrentUserId();

        if (!$this->isModerator() && !$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        if (!$this->ensureEventAccountOperational($accId, '/accounts/' . $accountId)) {
            return;
        }

        $transaction = $this->transactionModel->find($txId);
        if (!$transaction || (int) $transaction['account_id'] !== $accId) {
            $this->setFlash('danger', 'Transaction introuvable.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Transactions liées à un virement/prélèvement : non modifiables
        if ($this->transactionModel->getProtectedIds([$txId]) !== []) {
            $this->setFlash('danger', 'Cette transaction est liée à un virement ou un prélèvement et ne peut pas être modifiée directement.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Opérations issues d'une action de modération : non modifiables
        // (TPE, annulation de virement, annulation/remboursement de crédit,
        // rejet de prélèvement…), même par un modérateur.
        if (Transaction::isModerationOnly($transaction)) {
            $this->setFlash('danger', 'Cette opération a été générée par la modération et ne peut pas être modifiée manuellement.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Utilisateurs : uniquement les transactions des 7 derniers jours
        if (!$this->isModerator()) {
            $createdAt = strtotime($transaction['created_at'] ?? '');
            if (!$createdAt || (time() - $createdAt) > 7 * 86400) {
                $this->setFlash('danger', 'Cette opération date de plus de 7 jours et ne peut plus être modifiée.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
        }

        $data = $this->getPostData(['created_at', 'scheduled_at']);
        $updates = [];

        // Date d'enregistrement
        if (!empty($data['created_at'])) {
            $dtCreated = parse_datetime_input($data['created_at']);
            if (!$dtCreated) {
                $this->setFlash('danger', 'Date d\'enregistrement invalide (format : jj/mm/aaaa hh:mm).');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            $updates['created_at'] = $dtCreated->format('Y-m-d H:i:s');
        }

        // Date d'exécution (si la transaction était programmée)
        if (!empty($data['scheduled_at'])) {
            $dtScheduled = parse_datetime_input($data['scheduled_at']);
            if (!$dtScheduled) {
                $this->setFlash('danger', 'Date d\'exécution invalide (format : jj/mm/aaaa hh:mm).');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            $updates['scheduled_at'] = $dtScheduled->format('Y-m-d H:i:s');
        }

        if (empty($updates)) {
            $this->setFlash('warning', 'Aucune modification apportée.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $this->transactionModel->update($txId, $updates);
        $this->setFlash('success', 'Dates de l\'opération modifiées.');
        $this->redirect('/accounts/' . $accountId);
    }

    // ── EXCLUSION DU BUDGET ──────────────────────────────────────────────────

    /**
     * Bascule l'inclusion/exclusion d'une transaction dans le calcul des budgets.
     * Accessible à tout utilisateur ayant accès au compte (propriétaire ou accès partagé).
     * Seules les dépenses exécutées peuvent être concernées.
     */
    public function toggleBudgetExclusion(string $accountId, string $transactionId): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $accId  = (int) $accountId;
        $txId   = (int) $transactionId;
        $userId = $this->getCurrentUserId();

        if (!$this->isModerator() && !$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        if (!$this->ensureEventAccountOperational($accId, '/accounts/' . $accountId)) {
            return;
        }

        $transaction = $this->transactionModel->find($txId);
        if (!$transaction || (int) $transaction['account_id'] !== $accId) {
            $this->setFlash('danger', 'Transaction introuvable.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        if ($transaction['type'] !== 'expense') {
            $this->setFlash('danger', 'Seules les dépenses peuvent être exclues du budget.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Ne pas toggler les transactions encore programmées (non exécutées)
        if (!empty($transaction['scheduled_at']) && strtotime($transaction['scheduled_at']) > time()) {
            $this->setFlash('danger', 'Les opérations programmées ne sont pas encore comptabilisées dans le budget.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $nowExcluded = $this->transactionModel->toggleBudgetExclusion($txId);
        $msg = $nowExcluded
            ? 'Opération exclue du budget mensuel.'
            : 'Opération réintégrée dans le budget mensuel.';
        $this->setFlash('success', $msg);
        $this->redirect('/accounts/' . $accountId);
    }

    // ── DÉBITS DIFFÉRÉS ─────────────────────────────────────────────────────

    public function createDeferredDebit(string $accountId): void
    {
        $this->requireAuth();
        $this->requireFeature('deferred_debits');
        $this->validateCSRF();

        $accId  = (int) $accountId;
        $userId = $this->getCurrentUserId();

        if (!$this->isModerator() && !$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        if (!$this->ensureEventAccountOperational($accId, '/accounts/' . $accountId)) {
            return;
        }

        $account = $this->accountModel->find($accId);
        if (!$account || empty($account['deferred_debit_enabled'])) {
            $this->setFlash('danger', 'Le débit différé n\'est pas activé sur ce compte.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Bloquer si compte gelé
        if ($this->accountModel->isFrozen($accId)) {
            $this->setFlash('danger', 'Ce compte est gelé. Les opérations sortantes sont impossibles.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Bloquer si compte désactivé (hors modérateurs)
        if ($this->accountModel->isDisabled($accId) && !$this->isModerator()) {
            $this->setFlash('danger', 'Ce compte est en cours de résiliation.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $data = $this->getPostData(['amount', 'category', 'comment', 'operation_date', 'period_end_date', 'force_override', 'card_id']);

        if (empty($data['amount']) || empty($data['category'])) {
            $this->setFlash('danger', 'Le montant et la catégorie sont requis.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        if (!Transaction::isValidCategory($data['category'], 'expense')) {
            $this->setFlash('danger', 'Catégorie invalide.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $amount = abs((float) $data['amount']);
        if ($amount <= 0) {
            $this->setFlash('danger', 'Le montant doit être positif.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Vérification des fonds disponibles (solde à venir + découvert autorisé)
        $futureBalance = $this->accountModel->getFutureBalance($accId);
        $overdraft     = (float) ($account['overdraft'] ?? 0);
        $available     = $futureBalance + $overdraft;

        if ($amount > $available) {
            if (!$this->isModerator() || empty($data['force_override'])) {
                $this->setFlash('danger', sprintf(
                    'Fonds insuffisants. Solde à venir : %.2f € (+ découvert %.2f € = %.2f € disponibles). Montant demandé : %.2f €.',
                    $futureBalance, $overdraft, $available, $amount
                ));
                $this->redirect('/accounts/' . $accountId);
                return;
            }
        }

        // Vérification du plafond mensuel de la carte sélectionnée (sans bypass possible)
        $cardId = !empty($data['card_id']) ? (int) $data['card_id'] : null;
        if ($cardId !== null) {
            $card = $this->cardModel->find($cardId);
            if (!$card || (int) $card['account_id'] !== $accId) {
                $this->setFlash('danger', 'Carte introuvable ou non associée à ce compte.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            if (isset($card['monthly_limit']) && $card['monthly_limit'] !== null) {
                $limit   = (float) $card['monthly_limit'];
                $already = $this->cardModel->getMonthlyTotal($cardId);
                if ($already + $amount > $limit) {
                    $remaining = max(0.0, $limit - $already);
                    $this->setFlash('danger', sprintf(
                        'Plafond mensuel insuffisant pour la carte %s. Déjà utilisé : %.2f € / %.2f €. Montant demandé : %.2f €. Disponible : %.2f €.',
                        PaymentCard::mask($card['card_number']),
                        $already, $limit, $amount, $remaining
                    ));
                    $this->redirect('/accounts/' . $accountId);
                    return;
                }
            }
        }

        // Date de l'opération
        $operationDate = null;
        if (!empty($data['operation_date'])) {
            $dtOp = parse_datetime_input($data['operation_date']);
            if (!$dtOp) {
                $this->setFlash('danger', 'La date de l\'opération est invalide (format : jj/mm/aaaa hh:mm).');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            $operationDate = $dtOp->format('Y-m-d H:i:s');
        } else {
            $operationDate = date('Y-m-d H:i:s');
        }
        // Carte obligatoire pour les opérations datant du seuil d'introduction (migration 049) ou après
        if ($operationDate >= self::CARD_REQUIRED_SINCE && $cardId === null) {
            $this->setFlash('danger', sprintf(
                'Une carte bancaire est obligatoire pour les opérations datant du %s ou après.',
                date('d/m/Y', strtotime(self::CARD_REQUIRED_SINCE))
            ));
            $this->redirect('/accounts/' . $accountId);
            return;
        }
        // Date de fin de période (obligatoire, doit être dans le futur)
        if (empty($data['period_end_date'])) {
            $this->setFlash('danger', 'La date de fin de période est requise.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }
        $dtPeriod = parse_datetime_input($data['period_end_date']);
        if (!$dtPeriod) {
            $this->setFlash('danger', 'La date de fin de période est invalide (format : jj/mm/aaaa).');
            $this->redirect('/accounts/' . $accountId);
            return;
        }
        if ($dtPeriod->format('Y-m-d') < date('Y-m-d')) {
            $this->setFlash('danger', 'La date de fin de période doit être aujourd\'hui ou dans le futur.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }
        // Délai minimum de 5 jours avant la date de fin de période
        $minDelayDays = 5;
        $today = new \DateTime('today');
        $period = new \DateTime($dtPeriod->format('Y-m-d'));
        $daysUntilPeriod = (int) $today->diff($period)->format('%r%a');
        if ($daysUntilPeriod < $minDelayDays) {
            $this->setFlash('danger', sprintf(
                'La date de fin de période doit être au moins %d jours après aujourd\'hui.',
                $minDelayDays
            ));
            $this->redirect('/accounts/' . $accountId);
            return;
        }
        $periodEndDate = $dtPeriod->format('Y-m-d');

        $this->deferredDebitModel->createDeferredDebit(
            $accId,
            $userId,
            $amount,
            $data['category'],
            $data['comment'] ?? '',
            $operationDate,
            $periodEndDate,
            $cardId
        );

        $this->setFlash('success', sprintf(
            'Opération à débit différé enregistrée (%.2f €). Sera débitée le %s.',
            $amount,
            $dtPeriod->format('d/m/Y')
        ));
        $this->redirect('/accounts/' . $accountId);
    }

    public function cancelDeferredDebit(string $accountId, string $debitId): void
    {
        $this->requireAuth();
        $this->requireFeature('deferred_debits');
        $this->validateCSRF();

        $accId = (int) $accountId;
        $ddId  = (int) $debitId;
        $userId = $this->getCurrentUserId();

        if (!$this->isModerator() && !$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        if (!$this->ensureEventAccountOperational($accId, '/accounts/' . $accountId)) {
            return;
        }

        $dd = $this->deferredDebitModel->find($ddId);
        if (!$dd || (int) $dd['account_id'] !== $accId || $dd['status'] !== DeferredDebit::STATUS_PENDING) {
            $this->setFlash('danger', 'Opération introuvable ou déjà traitée.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Différés issus d'un paiement TPE : annulation réservée à la page
        // de modération des paiements TPE (y compris pour les modérateurs).
        if (str_starts_with((string) ($dd['comment'] ?? ''), '[TPE')) {
            $this->setFlash('danger', 'Ce débit différé provient d\'un paiement par carte (TPE) et doit être annulé depuis la page de modération des paiements TPE.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Verrou : plus aucune modification possible 7 jours après la fin de période
        $periodEndTs = strtotime($dd['period_end_date'] ?? '');
        if ($periodEndTs && (time() - $periodEndTs) > 7 * 86400) {
            $this->setFlash('danger', 'Cette opération ne peut plus être annulée : la date de fin de période est dépassée de plus de 7 jours.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $this->deferredDebitModel->cancel($ddId);
        $this->setFlash('success', 'Opération à débit différé annulée.');
        $this->redirect('/accounts/' . $accountId);
    }

    /**
     * Modifier les dates d'une opération à débit différé en attente.
     */
    public function editDeferredDebit(string $accountId, string $debitId): void
    {
        $this->requireAuth();
        $this->requireFeature('deferred_debits');
        $this->validateCSRF();

        $accId  = (int) $accountId;
        $ddId   = (int) $debitId;
        $userId = $this->getCurrentUserId();

        if (!$this->isModerator() && !$this->accountModel->hasAccess($accId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/dashboard');
            return;
        }

        $dd = $this->deferredDebitModel->find($ddId);
        if (!$dd || (int) $dd['account_id'] !== $accId) {
            $this->setFlash('danger', 'Opération introuvable.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Différés issus d'un paiement TPE : édition réservée aux modérateurs.
        // Les utilisateurs sont redirigés vers la page de modération des
        // paiements TPE, qui reste le point d'entrée unique pour l'annulation
        // (réversion du crédit côté commerçant).
        if (str_starts_with((string) ($dd['comment'] ?? ''), '[TPE') && !$this->isModerator()) {
            $this->setFlash('danger', 'Ce débit différé provient d\'un paiement par carte (TPE) et doit être modifié depuis la page de modération des paiements TPE.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $isExecuted = $dd['status'] === DeferredDebit::STATUS_EXECUTED;
        $isPending  = $dd['status'] === DeferredDebit::STATUS_PENDING;

        if (!$isPending && !$isExecuted) {
            $this->setFlash('danger', 'Cette opération ne peut plus être modifiée.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        // Verrou général : plus aucune modification possible (utilisateur ou modérateur)
        // 7 jours après la date de fin de période définie
        $periodEndTs = strtotime($dd['period_end_date'] ?? '');
        if ($periodEndTs && (time() - $periodEndTs) > 7 * 86400) {
            $this->setFlash('danger', 'Cette opération ne peut plus être modifiée : la date de fin de période est dépassée de plus de 7 jours.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $data = $this->getPostData(['operation_date', 'period_end_date']);
        $updates = [];

        // Date d'opération
        if (!empty($data['operation_date'])) {
            $dtOp = parse_datetime_input($data['operation_date']);
            if (!$dtOp) {
                $this->setFlash('danger', 'Date d\'opération invalide (format : jj/mm/aaaa hh:mm).');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            $updates['operation_date'] = $dtOp->format('Y-m-d H:i:s');
        }

        // Date de fin de période
        if (!empty($data['period_end_date'])) {
            $dtPeriod = parse_datetime_input($data['period_end_date']);
            if (!$dtPeriod) {
                $this->setFlash('danger', 'Date de fin de période invalide (format : jj/mm/aaaa).');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            // Pour les DD en attente, la date doit être dans le futur
            if ($isPending && $dtPeriod->format('Y-m-d') < date('Y-m-d')) {
                $this->setFlash('danger', 'La date de fin de période doit être aujourd\'hui ou dans le futur.');
                $this->redirect('/accounts/' . $accountId);
                return;
            }
            $updates['period_end_date'] = $dtPeriod->format('Y-m-d');
        }

        if (empty($updates)) {
            $this->setFlash('warning', 'Aucune modification apportée.');
            $this->redirect('/accounts/' . $accountId);
            return;
        }

        $this->deferredDebitModel->update($ddId, $updates);

        // Pour les DD exécutés, supprimer la transaction liée et repasser en attente
        if ($isExecuted && !empty($dd['transaction_id'])) {
            $this->transactionModel->delete((int) $dd['transaction_id']);
            $this->deferredDebitModel->update($ddId, [
                'status'         => DeferredDebit::STATUS_PENDING,
                'transaction_id' => null,
                'executed_at'    => null,
            ]);
            $this->setFlash('success', 'Débit différé modifié. L\'opération associée a été supprimée et le débit sera ré-exécuté à la prochaine échéance.');
        } else {
            $this->setFlash('success', 'Opération à débit différé modifiée.');
        }

        $this->redirect('/accounts/' . $accountId);
    }

    private function ensureEventAccountOperational(int $accountId, string $redirect): bool
    {
        $account = $this->accountModel->find($accountId);
        if (!$account) {
            $this->setFlash('danger', 'Compte introuvable.');
            $this->redirect($redirect);
            return false;
        }

        if (!Account::manualOperationsAllowed((string) ($account['type'] ?? ''))) {
            $this->setFlash('danger', 'Les opérations manuelles sont désactivées sur les comptes événementiels.');
            $this->redirect($redirect);
            return false;
        }

        $reason = Account::operationBlockedReason($account);
        if ($reason !== null) {
            $this->setFlash('danger', $reason);
            $this->redirect($redirect);
            return false;
        }

        return true;
    }
}
