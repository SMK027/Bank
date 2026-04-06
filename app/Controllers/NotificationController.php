<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Notification;

class NotificationController extends Controller
{
    private Notification $notifModel;

    public function __construct()
    {
        $this->notifModel = new Notification();
    }

    /** Liste de toutes les notifications de l'utilisateur connecté. */
    public function index(): void
    {
        $this->requireAuth();

        $userId        = $this->getCurrentUserId();
        $notifications = $this->notifModel->getForUser($userId, 100);
        $unreadCount   = $this->notifModel->countUnread($userId);

        $this->render('notifications/index', [
            'title'         => 'Mes notifications',
            'notifications' => $notifications,
            'unreadCount'   => $unreadCount,
        ]);
    }

    /** Marque une notification comme lue et redirige vers son lien (POST). */
    public function markRead(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $notif  = $this->notifModel->find((int) $id);

        if ($notif && (int) $notif['user_id'] === $userId) {
            $this->notifModel->markRead((int) $id, $userId);
            $link = $notif['link'] ?? '/notifications';
            $this->redirect($link);
            return;
        }

        $this->redirect('/notifications');
    }

    /** Marque toutes les notifications comme lues (POST). */
    public function markAllRead(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $this->notifModel->markAllRead($this->getCurrentUserId());
        $this->setFlash('success', 'Toutes les notifications ont été marquées comme lues.');
        $this->redirect('/notifications');
    }

    /** Supprime une notification (POST). */
    public function delete(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $this->notifModel->deleteForUser((int) $id, $this->getCurrentUserId());
        $this->redirect('/notifications');
    }

    /** Supprime toutes les notifications lues (POST). */
    public function deleteRead(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $this->notifModel->deleteReadForUser($this->getCurrentUserId());
        $this->setFlash('success', 'Notifications lues supprimées.');
        $this->redirect('/notifications');
    }

    /** Retourne le nombre de notifications non lues (JSON — pour polling). */
    public function unreadCount(): void
    {
        $this->requireAuth();
        $count = $this->notifModel->countUnread($this->getCurrentUserId());
        $this->json(['count' => $count]);
    }
}
