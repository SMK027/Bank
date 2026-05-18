<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Friendship;
use App\Models\Notification;
use App\Models\User;

class FriendController extends Controller
{
    private Friendship   $friendModel;
    private User         $userModel;
    private Notification $notifModel;

    public function __construct()
    {
        $this->friendModel = new Friendship();
        $this->userModel   = new User();
        $this->notifModel  = new Notification();
    }

    // ── Page principale ──────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $friends         = $this->friendModel->getFriends($userId);
        $pendingReceived = $this->friendModel->getPendingReceived($userId);
        $pendingSent     = $this->friendModel->getPendingSent($userId);

        $this->render('friends/index', [
            'title'          => 'Mes amis',
            'friends'        => $friends,
            'pendingReceived' => $pendingReceived,
            'pendingSent'    => $pendingSent,
        ]);
    }

    // ── Envoi d'une demande ──────────────────────────────────────────────────

    public function send(): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId = $this->getCurrentUserId();

        $recipientId = (int) ($_POST['recipient_id'] ?? 0);
        if ($recipientId <= 0) {
            $this->setFlash('danger', 'Utilisateur invalide.');
            $this->redirect('/friends');
            return;
        }

        if ($recipientId === $userId) {
            $this->setFlash('danger', 'Vous ne pouvez pas vous ajouter vous-même.');
            $this->redirect('/friends');
            return;
        }

        $recipient = $this->userModel->find($recipientId);
        if (!$recipient) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/friends');
            return;
        }

        // Vérifie si une relation acceptée existe déjà
        if ($this->friendModel->areFriends($userId, $recipientId)) {
            $this->setFlash('info', sprintf('Vous êtes déjà amis avec %s.', e($recipient['username'])));
            $this->redirect('/friends');
            return;
        }

        // Vérifie si une demande en attente existe déjà (reçue de l'autre côté)
        $existing = $this->friendModel->findBetween($userId, $recipientId);
        if ($existing && $existing['status'] === Friendship::STATUS_PENDING) {
            if ((int) $existing['recipient_id'] === $userId) {
                // L'autre a déjà envoyé une demande → on l'accepte directement
                $this->friendModel->acceptRequest((int) $existing['id']);
                $sender = $this->userModel->find($userId);
                $this->notifModel->notify(
                    $recipientId,
                    'friend_accepted',
                    sprintf('%s a accepté votre demande d\'ami', $sender['username'] ?? ''),
                    null,
                    '/friends'
                );
                $this->setFlash('success', sprintf('Vous êtes maintenant amis avec %s !', e($recipient['username'])));
                $this->redirect('/friends');
                return;
            }
            $this->setFlash('info', 'Une demande d\'ami est déjà en cours.');
            $this->redirect('/friends');
            return;
        }

        $this->friendModel->sendRequest($userId, $recipientId);

        $sender = $this->userModel->find($userId);
        $this->notifModel->notify(
            $recipientId,
            'friend_request',
            sprintf('%s vous a envoyé une demande d\'ami', $sender['username'] ?? ''),
            null,
            '/friends'
        );

        $this->setFlash('success', sprintf('Demande envoyée à %s.', e($recipient['username'])));
        $this->redirect('/friends');
    }

    // ── Accepter une demande ─────────────────────────────────────────────────

    public function accept(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId = $this->getCurrentUserId();

        $request = $this->friendModel->find((int) $id);
        if (!$request || (int) $request['recipient_id'] !== $userId || $request['status'] !== Friendship::STATUS_PENDING) {
            $this->setFlash('danger', 'Demande introuvable ou invalide.');
            $this->redirect('/friends');
            return;
        }

        $this->friendModel->acceptRequest((int) $id);

        $me = $this->userModel->find($userId);
        $this->notifModel->notify(
            (int) $request['requester_id'],
            'friend_accepted',
            sprintf('%s a accepté votre demande d\'ami', $me['username'] ?? ''),
            null,
            '/friends'
        );

        $requester = $this->userModel->find((int) $request['requester_id']);
        $this->setFlash('success', sprintf('Vous êtes maintenant amis avec %s !', e($requester['username'] ?? '')));
        $this->redirect('/friends');
    }

    // ── Refuser une demande ──────────────────────────────────────────────────

    public function refuse(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId = $this->getCurrentUserId();

        $request = $this->friendModel->find((int) $id);
        if (!$request || (int) $request['recipient_id'] !== $userId || $request['status'] !== Friendship::STATUS_PENDING) {
            $this->setFlash('danger', 'Demande introuvable ou invalide.');
            $this->redirect('/friends');
            return;
        }

        $this->friendModel->refuseRequest((int) $id);
        $this->setFlash('success', 'Demande refusée.');
        $this->redirect('/friends');
    }

    // ── Annuler une demande envoyée ──────────────────────────────────────────

    public function cancel(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId = $this->getCurrentUserId();

        $request = $this->friendModel->find((int) $id);
        if (!$request || (int) $request['requester_id'] !== $userId || $request['status'] !== Friendship::STATUS_PENDING) {
            $this->setFlash('danger', 'Demande introuvable ou invalide.');
            $this->redirect('/friends');
            return;
        }

        $this->friendModel->cancelRequest((int) $id);
        $this->setFlash('success', 'Demande annulée.');
        $this->redirect('/friends');
    }

    // ── Supprimer un ami ─────────────────────────────────────────────────────

    public function remove(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $userId   = $this->getCurrentUserId();
        $friendId = (int) $id;

        if (!$this->friendModel->areFriends($userId, $friendId)) {
            $this->setFlash('danger', 'Cette relation n\'existe pas.');
            $this->redirect('/friends');
            return;
        }

        $this->friendModel->removeFriend($userId, $friendId);
        $this->setFlash('success', 'Ami supprimé.');
        $this->redirect('/friends');
    }

    // ── Recherche d'utilisateurs (AJAX) ─────────────────────────────────────

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
        $users = array_values(array_filter($users, fn($u) => (int) $u['id'] !== $userId));

        $friends = $this->friendModel->getFriends($userId);
        $friendIds = array_column($friends, 'friend_id');

        $results = array_map(fn($u) => [
            'id'        => (int) $u['id'],
            'username'  => $u['username'],
            'is_friend' => in_array((int) $u['id'], array_map('intval', $friendIds), true),
        ], $users);

        $this->json($results);
    }

    // ── Liste des amis (AJAX pour la modale de répartition) ─────────────────

    public function listFriends(): void
    {
        $this->requireAuth();
        $userId  = $this->getCurrentUserId();
        $friends = $this->friendModel->getFriends($userId);

        $results = array_map(fn($f) => [
            'id'       => (int) $f['friend_id'],
            'username' => $f['friend_username'],
        ], $friends);

        $this->json($results);
    }
}
