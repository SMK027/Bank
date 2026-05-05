<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\AuditLog;
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

    public function __construct()
    {
        $this->cardModel    = new PaymentCard();
        $this->accountModel = new Account();
        $this->userModel    = new User();
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

        $this->render('cards/index', [
            'title'            => 'Mes cartes bancaires',
            'cards'            => $cards,
            'accountsById'     => $accountsById,
            'eligibleAccounts' => $eligibleAccounts,
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

        $data = $this->getPostData(['account_id', 'last4', 'label']);

        $accountId = (int) ($data['account_id'] ?? 0);
        $last4     = preg_replace('/\D/', '', (string) $data['last4']) ?? '';
        $label     = mb_substr((string) $data['label'], 0, 100);

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

        $cardId = $this->cardModel->create([
            'user_id'     => $userId,
            'account_id'  => $accountId,
            'card_number' => $cardNumber,
            'last4'       => $last4,
            'label'       => $label,
            'status'      => 'active',
        ]);

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
}
