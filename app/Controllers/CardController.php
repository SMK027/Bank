<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AccountAccess;
use App\Models\AuditLog;
use App\Models\Guardianship;
use App\Models\PaymentCard;
use App\Models\User;

/**
 * Gestion des cartes bancaires de l'utilisateur (côté UI).
 * Les paiements via carte (débit/crédit) sont exposés par
 * App\Controllers\Api\PaymentApiController.
 */
class CardController extends Controller
{
    private PaymentCard $cardModel;
    private Account $accountModel;
    private User $userModel;
    private AccountAccess $accessModel;
    private Guardianship $guardianshipModel;

    public function __construct()
    {
        $this->cardModel          = new PaymentCard();
        $this->accountModel       = new Account();
        $this->userModel          = new User();
        $this->accessModel        = new AccountAccess();
        $this->guardianshipModel  = new Guardianship();
    }

    /** Liste les cartes bancaires de l'utilisateur connecté. */
    public function index(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $cards = $this->cardModel->getByUser($userId);

        // Récupérer les comptes pour afficher le nom + permettre la modification
        $accountsById = [];
        foreach ($this->accountModel->getByUser($userId) as $acc) {
            $accountsById[(int) $acc['id']] = $acc;
        }

        // Comptes éligibles pour l'association d'une carte (hors épargne, non désactivés)
        $eligibleAccounts = array_values(array_filter(
            $accountsById,
            fn(array $a) => Account::typeAllowsCard($a['type'] ?? '') && empty($a['disabled_at'])
        ));

        // Cartes des comptes partagés (procuration et tutelle légale active)
        $sharedCards       = [];
        $sharedAccountsById = [];

        // Via procuration
        foreach ($this->accessModel->getValidAccessesForUser($userId) as $access) {
            $acc = $this->accountModel->find((int) $access['account_id']);
            if (!$acc) {
                continue;
            }
            $sharedAccountsById[(int) $acc['id']] = $acc;
            foreach ($this->cardModel->getByAccount((int) $acc['id']) as $card) {
                $sharedCards[] = $card;
            }
        }

        // Via tutelle légale active
        foreach ($this->guardianshipModel->getMinorsOf($userId) as $link) {
            $minorId = (int) $link['minor_user_id'];
            $minor   = $this->userModel->find($minorId);
            if (!$minor || !User::isMinorFromDate($minor['birth_date'] ?? null)) {
                continue;
            }
            foreach ($this->accountModel->getByUser($minorId) as $acc) {
                $accId = (int) $acc['id'];
                if (isset($sharedAccountsById[$accId])) {
                    continue;
                }
                $sharedAccountsById[$accId] = $acc;
                foreach ($this->cardModel->getByAccount($accId) as $card) {
                    $sharedCards[] = $card;
                }
            }
        }

        // Dépenses du mois en cours pour chaque carte ayant un plafond
        // (TPE + débits différés pending du mois calendaire)
        $monthlySpentById = [];
        foreach (array_merge($cards, $sharedCards) as $c) {
            if (isset($c['monthly_limit']) && $c['monthly_limit'] !== null) {
                $monthlySpentById[(int) $c['id']] = $this->cardModel->getMonthlyTotal((int) $c['id']);
            }
        }

        $this->render('cards/index', [
            'title'              => 'Mes cartes bancaires',
            'cards'              => $cards,
            'accountsById'       => $accountsById,
            'eligibleAccounts'   => $eligibleAccounts,
            'sharedCards'        => $sharedCards,
            'sharedAccountsById' => $sharedAccountsById,
            'monthlySpentById'   => $monthlySpentById,
            'isModerator'        => $this->isModerator(),
        ]);
    }

    /** Formulaire de création d'une carte. */
    public function createForm(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $eligibleAccounts = array_values(array_filter(
            $this->accountModel->getByUser($userId),
            fn(array $a) => Account::typeAllowsCard($a['type'] ?? '') && empty($a['disabled_at'])
        ));

        if (empty($eligibleAccounts)) {
            $this->setFlash('warning', 'Vous devez d\'abord créer un compte non-épargne pour enregistrer une carte bancaire.');
            $this->redirect('/cards');
            return;
        }

        $this->render('cards/create', [
            'title'            => 'Enregistrer une carte bancaire',
            'eligibleAccounts' => $eligibleAccounts,
        ]);
    }

    /** Crée une nouvelle carte bancaire. */
    public function create(): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId = $this->getCurrentUserId();

        $data = $this->getPostData(['account_id', 'last4', 'label', 'expires_at', 'monthly_limit']);

        $accountId    = (int) ($data['account_id'] ?? 0);
        $last4        = preg_replace('/\D/', '', (string) $data['last4']) ?? '';
        $label        = mb_substr((string) $data['label'], 0, 100);
        $expiresRaw   = trim((string) ($data['expires_at'] ?? ''));
        $limitRaw     = trim((string) ($data['monthly_limit'] ?? ''));

        $expiresAt    = $expiresRaw !== '' ? PaymentCard::parseExpiry($expiresRaw) : null;
        $monthlyLimit = $limitRaw !== '' ? (float) str_replace(',', '.', $limitRaw) : null;

        if ($expiresRaw !== '' && $expiresAt === null) {
            $this->setFlash('danger', 'Format de date d\'expiration invalide (attendu MM/AA).');
            $this->redirect('/cards/create');
            return;
        }
        if ($expiresAt !== null && strtotime($expiresAt) < strtotime('today')) {
            $this->setFlash('danger', 'La date d\'expiration ne peut pas être dans le passé.');
            $this->redirect('/cards/create');
            return;
        }
        if ($monthlyLimit !== null && $monthlyLimit <= 0) {
            $this->setFlash('danger', 'Le plafond mensuel doit être strictement positif.');
            $this->redirect('/cards/create');
            return;
        }

        if (!preg_match('/^\d{4}$/', $last4)) {
            $this->setFlash('danger', 'Les 4 derniers chiffres de la carte doivent être 4 chiffres exactement.');
            $this->redirect('/cards/create');
            return;
        }

        $account = $this->accountModel->find($accountId);
        if (!$account || (int) $account['user_id'] !== $userId) {
            $this->setFlash('danger', 'Compte introuvable ou non autorisé.');
            $this->redirect('/cards/create');
            return;
        }

        if (!Account::typeAllowsCard($account['type'] ?? '')) {
            $this->setFlash('danger', 'Les comptes d\'épargne ne peuvent pas être associés à une carte bancaire.');
            $this->redirect('/cards/create');
            return;
        }

        if (!empty($account['disabled_at'])) {
            $this->setFlash('danger', 'Ce compte est en cours de résiliation.');
            $this->redirect('/cards/create');
            return;
        }

        if ($this->cardModel->userHasLast4($userId, $last4)) {
            $this->setFlash('danger', 'Vous possédez déjà une carte se terminant par ces 4 chiffres.');
            $this->redirect('/cards/create');
            return;
        }

        try {
            $cardNumber = $this->cardModel->generateCardNumber($last4);
        } catch (\Throwable $e) {
            $this->setFlash('danger', 'Impossible de générer un numéro de carte. Réessayez.');
            $this->redirect('/cards/create');
            return;
        }

        $cardData = [
            'user_id'     => $userId,
            'account_id'  => $accountId,
            'card_number' => $cardNumber,
            'last4'       => $last4,
            'label'       => $label,
            'status'      => 'active',
        ];
        if ($expiresAt !== null) {
            $cardData['expires_at'] = $expiresAt;
        }
        if ($monthlyLimit !== null) {
            $cardData['monthly_limit'] = $monthlyLimit;
        }
        $cardId = $this->cardModel->create($cardData);

        AuditLog::log($userId, AuditLog::ACTION_CARD_CREATE, [
            'card_id' => $cardId,
            'last4'   => $last4,
            'masked'  => PaymentCard::mask($cardNumber),
        ], targetAccountId: $accountId);

        $this->setFlash('success', 'Carte enregistrée. Numéro complet : ' . PaymentCard::mask($cardNumber) . '. Conservez-le précieusement (visible une seule fois).');
        // Pour un POC : on peut afficher le numéro complet une fois.
        $_SESSION['card_just_created'] = ['number' => $cardNumber, 'card_id' => $cardId];
        $this->redirect('/cards');
    }

    /** Modifie le compte associé à une carte. */
    public function updateAccount(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId = $this->getCurrentUserId();

        $id = (int) $id;
        $card = $this->cardModel->find($id);
        if (!$card || (int) $card['user_id'] !== $userId) {
            $this->setFlash('danger', 'Carte introuvable.');
            $this->redirect('/cards');
            return;
        }

        $newAccountId = (int) ($_POST['account_id'] ?? 0);
        $account = $this->accountModel->find($newAccountId);
        if (!$account || (int) $account['user_id'] !== $userId) {
            $this->setFlash('danger', 'Compte introuvable ou non autorisé.');
            $this->redirect('/cards');
            return;
        }

        if (!Account::typeAllowsCard($account['type'] ?? '')) {
            $this->setFlash('danger', 'Les comptes d\'épargne ne peuvent pas être associés à une carte bancaire.');
            $this->redirect('/cards');
            return;
        }

        if (!empty($account['disabled_at'])) {
            $this->setFlash('danger', 'Ce compte est en cours de résiliation.');
            $this->redirect('/cards');
            return;
        }

        $this->cardModel->update($id, ['account_id' => $newAccountId]);

        AuditLog::log($userId, AuditLog::ACTION_CARD_UPDATE_ACCOUNT, [
            'card_id'        => $id,
            'old_account_id' => (int) $card['account_id'],
            'new_account_id' => $newAccountId,
        ], targetAccountId: $newAccountId);

        $this->setFlash('success', 'Compte associé à la carte mis à jour.');
        $this->redirect('/cards');
    }

    /** Met à jour la date d'expiration et/ou le plafond mensuel d'une carte. */
    public function updateSettings(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId = $this->getCurrentUserId();

        $id   = (int) $id;
        $card = $this->cardModel->find($id);
        if (!$card || (int) $card['user_id'] !== $userId) {
            $this->setFlash('danger', 'Carte introuvable.');
            $this->redirect('/cards');
            return;
        }

        $expiresRaw  = trim((string) ($_POST['expires_at']    ?? ''));
        $limitRaw    = trim((string) ($_POST['monthly_limit'] ?? ''));
        $clearExpiry = isset($_POST['clear_expiry']);
        $clearLimit  = isset($_POST['clear_limit']);

        $changes = [];

        // — Expiration —
        if ($clearExpiry) {
            $changes['expires_at'] = null;
        } elseif ($expiresRaw !== '') {
            $expiresAt = PaymentCard::parseExpiry($expiresRaw);
            if ($expiresAt === null) {
                $this->setFlash('danger', 'Format de date d\'expiration invalide (attendu MM/AA).');
                $this->redirect('/cards');
                return;
            }
            if (strtotime($expiresAt) < strtotime('today')) {
                $this->setFlash('danger', 'La date d\'expiration ne peut pas être dans le passé.');
                $this->redirect('/cards');
                return;
            }
            $changes['expires_at'] = $expiresAt;
        }

        // — Plafond mensuel —
        if ($clearLimit) {
            $changes['monthly_limit'] = null;
        } elseif ($limitRaw !== '') {
            $limit = (float) str_replace([' ', "\xc2\xa0", ','], ['', '', '.'], $limitRaw);
            if ($limit <= 0) {
                $this->setFlash('danger', 'Le plafond mensuel doit être strictement positif.');
                $this->redirect('/cards');
                return;
            }
            $changes['monthly_limit'] = $limit;
        }

        if (empty($changes)) {
            $this->setFlash('warning', 'Aucune modification détectée.');
            $this->redirect('/cards');
            return;
        }

        $this->cardModel->update($id, $changes);

        AuditLog::log($userId, AuditLog::ACTION_CARD_UPDATE_SETTINGS, [
            'card_id'       => $id,
            'last4'         => $card['last4'] ?? '',
            'changes'       => array_map(fn($v) => $v ?? 'supprimé', $changes),
        ]);

        $this->setFlash('success', 'Paramètres de la carte mis à jour.');
        $this->redirect('/cards');
    }

    /** Supprime une carte. */
    public function delete(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId = $this->getCurrentUserId();

        $id = (int) $id;
        $card = $this->cardModel->find($id);
        if (!$card || (int) $card['user_id'] !== $userId) {
            $this->setFlash('danger', 'Carte introuvable.');
            $this->redirect('/cards');
            return;
        }

        $this->cardModel->delete($id);
        AuditLog::log($userId, AuditLog::ACTION_CARD_DELETE, [
            'card_id' => $id,
            'last4'   => $card['last4'] ?? '',
        ]);

        $this->setFlash('success', 'Carte bancaire supprimée.');
        $this->redirect('/cards');
    }

    /** Affiche le formulaire de confirmation par mot de passe pour révéler le numéro. */
    public function revealForm(string $id): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $id = (int) $id;
        $card = $this->cardModel->find($id);
        if (!$card || (int) $card['user_id'] !== $userId) {
            $this->setFlash('danger', 'Carte introuvable.');
            $this->redirect('/cards');
            return;
        }

        $this->render('cards/reveal', [
            'title' => 'Afficher le numéro de carte',
            'card'  => $card,
            'pan'   => null,
        ]);
    }

    /** Vérifie le mot de passe et affiche le numéro complet de la carte. */
    public function reveal(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId = $this->getCurrentUserId();

        $id = (int) $id;
        $card = $this->cardModel->find($id);
        if (!$card || (int) $card['user_id'] !== $userId) {
            $this->setFlash('danger', 'Carte introuvable.');
            $this->redirect('/cards');
            return;
        }

        $password = (string) ($_POST['password'] ?? '');
        $user = $this->userModel->find($userId);
        if (!$user || !password_verify($password, $user['password'])) {
            AuditLog::log($userId, AuditLog::ACTION_CARD_REVEAL_FAIL, [
                'card_id' => $id,
                'last4'   => $card['last4'] ?? '',
            ]);
            $this->render('cards/reveal', [
                'title' => 'Afficher le numéro de carte',
                'card'  => $card,
                'pan'   => null,
                'error' => 'Mot de passe incorrect.',
            ]);
            return;
        }

        AuditLog::log($userId, AuditLog::ACTION_CARD_REVEAL, [
            'card_id' => $id,
            'last4'   => $card['last4'] ?? '',
        ]);

        $this->render('cards/reveal', [
            'title' => 'Afficher le numéro de carte',
            'card'  => $card,
            'pan'   => $card['card_number'],
        ]);
    }

    /**
     * Active ou bloque une carte.
     * Autorisé pour le titulaire de la carte, les mandataires avec procuration valide
     * sur le compte associé, et les responsables légaux actifs du titulaire.
     */
    public function toggleStatus(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId = $this->getCurrentUserId();

        $id   = (int) $id;
        $card = $this->cardModel->find($id);
        if (!$card) {
            $this->setFlash('danger', 'Carte introuvable.');
            $this->redirect('/cards');
            return;
        }

        $cardOwnerId = (int) $card['user_id'];
        $accountId   = (int) $card['account_id'];

        $authorized = false;

        // Titulaire direct
        if ($cardOwnerId === $userId) {
            $authorized = true;
        }

        // Procuration valide sur le compte associé
        if (!$authorized) {
            $authorized = $this->accessModel->hasValidAccess($accountId, $userId);
        }

        // Responsable légal actif du titulaire
        if (!$authorized) {
            $owner = $this->userModel->find($cardOwnerId);
            if ($owner && User::isMinorFromDate($owner['birth_date'] ?? null)) {
                $authorized = $this->guardianshipModel->isActiveGuardianOf($userId, $cardOwnerId);
            }
        }

        if (!$authorized) {
            $this->setFlash('danger', 'Action non autorisée.');
            $this->redirect('/cards');
            return;
        }

        $newStatus = ($card['status'] === 'active') ? 'blocked' : 'active';
        $this->cardModel->update($id, ['status' => $newStatus]);

        AuditLog::log($userId, AuditLog::ACTION_CARD_TOGGLE_STATUS, [
            'card_id'    => $id,
            'last4'      => $card['last4'] ?? '',
            'new_status' => $newStatus,
        ], targetAccountId: $accountId);

        $label = $newStatus === 'active' ? 'activée' : 'bloquée';
        $this->setFlash('success', "Carte {$label} avec succès.");
        $this->redirect('/cards');
    }

    /**
     * Remet à zéro le plafond mensuel dépensé d'une carte à débit différé.
     * Action réservée au titulaire de la carte.
     * Limitée à une seule remise par mois calendaire.
     */
    public function resetMonthlySpent(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId = $this->getCurrentUserId();

        $cardId = (int) $id;
        $card   = $this->cardModel->find($cardId);
        if (!$card || (int) $card['user_id'] !== $userId) {
            $this->setFlash('danger', 'Carte introuvable.');
            $this->redirect('/cards');
            return;
        }

        if ($card['monthly_limit'] === null) {
            $this->setFlash('danger', 'Cette carte n\'a pas de plafond mensuel configuré.');
            $this->redirect('/cards');
            return;
        }

        $account = $this->accountModel->find((int) $card['account_id']);
        if (!$account || empty($account['deferred_debit_enabled'])) {
            $this->setFlash('danger', 'La remise à zéro manuelle est réservée aux cartes liées à un compte à débit différé. Le plafond des cartes à débit immédiat est remis à zéro automatiquement le 1er de chaque mois.');
            $this->redirect('/cards');
            return;
        }

        // Une seule remise autorisée par mois calendaire
        $lastReset = $card['monthly_reset_at'] ?? null;
        if ($lastReset !== null && date('Y-m', strtotime($lastReset)) === date('Y-m')) {
            $this->setFlash('danger', 'Le plafond a déjà été remis à zéro ce mois-ci. La prochaine remise sera possible le 1er du mois prochain.');
            $this->redirect('/cards');
            return;
        }

        $this->cardModel->resetMonthlySpent($cardId);
        AuditLog::log($userId, AuditLog::ACTION_CARD_RESET_SPENT, [
            'card_id'          => $cardId,
            'last4'            => $card['last4'] ?? '',
            'previous_reset_at' => $lastReset,
        ], targetAccountId: (int) $card['account_id']);

        $this->setFlash('success', 'Plafond mensuel remis à zéro. Vous pouvez à nouveau dépenser jusqu\'au plafond configuré.');
        $this->redirect('/cards');
    }

    /**
     * Permet à un modérateur de corriger manuellement le montant dépensé ce mois
     * sur une carte. Utile pour intégrer des dépenses hors système ou corriger un écart.
     * La valeur est automatiquement ignorée dès que le mois calendaire change.
     */
    public function overrideMonthlySpent(string $id): void
    {
        $this->requireAuth();
        if (!$this->isModerator()) {
            $this->setFlash('danger', 'Action réservée à la modération.');
            $this->redirect('/cards');
            return;
        }
        $this->validateCSRF();

        $cardId = (int) $id;
        $card   = $this->cardModel->find($cardId);
        if (!$card) {
            $this->setFlash('danger', 'Carte introuvable.');
            $this->redirect('/cards');
            return;
        }

        // La carte doit avoir un plafond mensuel pour que l'override ait un sens
        if ($card['monthly_limit'] === null) {
            $this->setFlash('danger', 'Cette carte n\'a pas de plafond mensuel configuré.');
            $this->redirect('/cards');
            return;
        }

        $clear    = !empty($_POST['clear_override']);
        $rawValue = trim((string) ($_POST['override_value'] ?? ''));

        if ($clear) {
            $this->cardModel->setMonthlySpentOverride($cardId, null);
            AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_CARD_OVERRIDE_SPENT, [
                'card_id' => $cardId,
                'last4'   => $card['last4'] ?? '',
                'action'  => 'clear',
            ], targetUserId: (int) $card['user_id']);
            $this->setFlash('success', 'Correction du plafond dépensé supprimée — le calcul automatique reprend.');
            $this->redirect('/cards');
            return;
        }

        if ($rawValue === '') {
            $this->setFlash('danger', 'Veuillez saisir une valeur.');
            $this->redirect('/cards');
            return;
        }

        $value = (float) str_replace([' ', "\xc2\xa0", ','], ['', '', '.'], $rawValue);
        if ($value < 0) {
            $this->setFlash('danger', 'Le montant dépensé ne peut pas être négatif.');
            $this->redirect('/cards');
            return;
        }

        $this->cardModel->setMonthlySpentOverride($cardId, $value);
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_CARD_OVERRIDE_SPENT, [
            'card_id' => $cardId,
            'last4'   => $card['last4'] ?? '',
            'value'   => $value,
            'month'   => date('Y-m'),
        ], targetUserId: (int) $card['user_id']);

        $this->setFlash('success', sprintf(
            'Plafond mensuel dépensé ajusté à %s € pour %s.',
            number_format($value, 2, ',', ' '),
            date('m/Y')
        ));
        $this->redirect('/cards');
    }
}
