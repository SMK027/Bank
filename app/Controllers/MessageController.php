<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;

class MessageController extends Controller
{
    private Conversation $conversationModel;
    private Message      $messageModel;
    private User         $userModel;
    private Notification $notifModel;

    public function __construct()
    {
        $this->conversationModel = new Conversation();
        $this->messageModel      = new Message();
        $this->userModel         = new User();
        $this->notifModel        = new Notification();
    }

    // ────────────────────────────────────────────────────────────
    // Liste des conversations (boîte de réception)
    // ────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();

        $userId        = $this->getCurrentUserId();
        $conversations = $this->conversationModel->getForUser($userId);

        $this->render('messages/index', [
            'title'         => 'Messagerie',
            'conversations' => $conversations,
            'isModerator'   => $this->isModerator(),
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // Formulaire nouvelle conversation
    // ────────────────────────────────────────────────────────────

    public function createForm(): void
    {
        $this->requireAuth();

        $userId      = $this->getCurrentUserId();
        $isModerator = $this->isModerator();

        // Les utilisateurs non-modérateurs ne peuvent écrire qu'à la modération
        // Les modérateurs peuvent écrire à d'autres modérateurs ou à des utilisateurs
        $recipients = [];
        if ($isModerator) {
            // Lister tous les utilisateurs (modérateurs + users)
            $all = $this->userModel->findAll('username', 'ASC');
            $recipients = array_filter($all, fn($u) => (int) $u['id'] !== $userId);
        }

        $this->render('messages/create', [
            'title'       => 'Nouveau message',
            'isModerator' => $isModerator,
            'recipients'  => $recipients,
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // Recherche AJAX d'utilisateurs (modérateurs uniquement)
    // ────────────────────────────────────────────────────────────

    public function searchUsers(): void
    {
        $this->requireAuth();
        $this->requireModerator();

        $q = trim($_GET['q'] ?? '');
        if (mb_strlen($q) < 2) {
            $this->json([]);
            return;
        }

        $pdo  = \App\Core\Database::getInstance();
        $stmt = $pdo->prepare(
            "SELECT id, username, global_role FROM users
             WHERE username LIKE ? AND id != ?
             ORDER BY username LIMIT 20"
        );
        $stmt->execute(['%' . $q . '%', $this->getCurrentUserId()]);
        $this->json($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    // ────────────────────────────────────────────────────────────
    // Traitement création conversation
    // ────────────────────────────────────────────────────────────

    public function store(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId      = $this->getCurrentUserId();
        $isModerator = $this->isModerator();
        $data        = $this->getPostData(['subject', 'body', 'type', 'recipients']);

        // Validation sujet
        $subject = trim($data['subject']);
        if (mb_strlen($subject) < 2 || mb_strlen($subject) > 255) {
            $this->setFlash('danger', 'Le sujet doit contenir entre 2 et 255 caractères.');
            $this->redirect('/messages/create');
            return;
        }

        // Validation corps
        $body = trim($data['body']);
        if (mb_strlen($body) < 1) {
            $this->setFlash('danger', 'Le message ne peut pas être vide.');
            $this->redirect('/messages/create');
            return;
        }

        // Déterminer le type et les destinataires
        if ($isModerator) {
            $type = $data['type'] === Conversation::TYPE_MOD_ONLY
                ? Conversation::TYPE_MOD_ONLY
                : Conversation::TYPE_MOD_USER;

            // Récupérer les destinataires
            $recipientIds = [];
            $rawRecipients = $_POST['recipients'] ?? [];
            if (is_array($rawRecipients)) {
                $recipientIds = array_map('intval', $rawRecipients);
            } elseif (!empty($rawRecipients)) {
                $recipientIds = array_map('intval', explode(',', (string) $rawRecipients));
            }

            if (empty($recipientIds)) {
                $this->setFlash('danger', 'Veuillez sélectionner au moins un destinataire.');
                $this->redirect('/messages/create');
                return;
            }

            // Valider les destinataires
            foreach ($recipientIds as $rid) {
                $recipient = $this->userModel->find($rid);
                if (!$recipient) {
                    $this->setFlash('danger', 'Destinataire introuvable.');
                    $this->redirect('/messages/create');
                    return;
                }
                // Si conversation mod_only, tous les destinataires doivent être modérateurs
                if ($type === Conversation::TYPE_MOD_ONLY && ($recipient['global_role'] ?? '') !== 'moderator') {
                    $this->setFlash('danger', 'Les conversations entre modérateurs ne peuvent inclure que des modérateurs.');
                    $this->redirect('/messages/create');
                    return;
                }
            }
        } else {
            // Utilisateur classique → conversation avec la modération
            $type = Conversation::TYPE_MOD_USER;

            // Ajouter tous les modérateurs comme participants
            $pdo = \App\Core\Database::getInstance();
            $stmt = $pdo->prepare(
                "SELECT id FROM users WHERE global_role = 'moderator'"
            );
            $stmt->execute();
            $recipientIds = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

            if (empty($recipientIds)) {
                $this->setFlash('danger', 'Aucun modérateur disponible.');
                $this->redirect('/messages/create');
                return;
            }
        }

        // Créer la conversation
        $convId = $this->conversationModel->createConversation(
            $subject,
            $type,
            $userId,
            $recipientIds
        );

        // Poster le premier message
        $this->messageModel->post($convId, $userId, $body);

        // Notifications aux destinataires
        foreach ($recipientIds as $rid) {
            $this->notifModel->notify(
                $rid,
                'new_message',
                'Nouveau message : ' . $subject,
                mb_substr($body, 0, 100) . (mb_strlen($body) > 100 ? '…' : ''),
                '/messages/' . $convId
            );
        }

        $this->setFlash('success', 'Message envoyé.');
        $this->redirect('/messages/' . $convId);
    }

    // ────────────────────────────────────────────────────────────
    // Afficher une conversation
    // ────────────────────────────────────────────────────────────

    public function show(string $id): void
    {
        $this->requireAuth();

        $convId = (int) $id;
        $userId = $this->getCurrentUserId();

        $conversation = $this->conversationModel->find($convId);
        if (!$conversation) {
            $this->setFlash('danger', 'Conversation introuvable.');
            $this->redirect('/messages');
            return;
        }

        // Vérifier l'accès : participant ou modérateur sur conversation mod_user
        if (!$this->conversationModel->isParticipant($convId, $userId)) {
            $this->setFlash('danger', 'Accès refusé.');
            $this->redirect('/messages');
            return;
        }

        $messages     = $this->messageModel->getByConversation($convId);
        $participants = $this->conversationModel->getParticipants($convId);

        // Marquer comme lu
        $this->conversationModel->markRead($convId, $userId);

        $this->render('messages/show', [
            'title'        => $conversation['subject'],
            'conversation' => $conversation,
            'messages'     => $messages,
            'participants' => $participants,
            'isModerator'  => $this->isModerator(),
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // Répondre dans une conversation
    // ────────────────────────────────────────────────────────────

    public function reply(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $convId = (int) $id;
        $userId = $this->getCurrentUserId();

        $conversation = $this->conversationModel->find($convId);
        if (!$conversation || !$this->conversationModel->isParticipant($convId, $userId)) {
            $this->setFlash('danger', 'Conversation introuvable.');
            $this->redirect('/messages');
            return;
        }

        if (!empty($conversation['is_closed'])) {
            $this->setFlash('warning', 'Cette conversation est clôturée.');
            $this->redirect('/messages/' . $convId);
            return;
        }

        $data = $this->getPostData(['body']);
        $body = trim($data['body']);
        if (mb_strlen($body) < 1) {
            $this->setFlash('danger', 'Le message ne peut pas être vide.');
            $this->redirect('/messages/' . $convId);
            return;
        }

        $this->messageModel->post($convId, $userId, $body);

        // Marquer comme lu pour l'auteur
        $this->conversationModel->markRead($convId, $userId);

        // Mettre à jour updated_at de la conversation
        $this->conversationModel->update($convId, []);

        // Notifier les autres participants
        $participants = $this->conversationModel->getParticipants($convId);
        $currentUser  = $this->userModel->find($userId);
        $authorName   = $currentUser['username'] ?? 'Inconnu';

        foreach ($participants as $p) {
            if ((int) $p['id'] !== $userId) {
                $this->notifModel->notify(
                    (int) $p['id'],
                    'new_message',
                    'Réponse dans : ' . $conversation['subject'],
                    $authorName . ' : ' . mb_substr($body, 0, 100) . (mb_strlen($body) > 100 ? '…' : ''),
                    '/messages/' . $convId
                );
            }
        }

        $this->setFlash('success', 'Message envoyé.');
        $this->redirect('/messages/' . $convId . '#messages-end');
    }

    // ────────────────────────────────────────────────────────────
    // Fermer / rouvrir une conversation (modérateurs)
    // ────────────────────────────────────────────────────────────

    public function close(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $this->requireModerator();

        $convId = (int) $id;
        $this->conversationModel->close($convId);
        $this->setFlash('info', 'Conversation clôturée.');
        $this->redirect('/messages/' . $convId);
    }

    public function reopen(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $this->requireModerator();

        $convId = (int) $id;
        $this->conversationModel->reopen($convId);
        $this->setFlash('success', 'Conversation rouverte.');
        $this->redirect('/messages/' . $convId);
    }

    // ────────────────────────────────────────────────────────────
    // Ajouter un participant (modérateurs)
    // ────────────────────────────────────────────────────────────

    public function addParticipant(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();
        $this->requireModerator();

        $convId = (int) $id;
        $conversation = $this->conversationModel->find($convId);
        if (!$conversation) {
            $this->setFlash('danger', 'Conversation introuvable.');
            $this->redirect('/messages');
            return;
        }

        $newUserId = (int) ($_POST['user_id'] ?? 0);
        $newUser   = $this->userModel->find($newUserId);
        if (!$newUser) {
            $this->setFlash('danger', 'Utilisateur introuvable.');
            $this->redirect('/messages/' . $convId);
            return;
        }

        // Si mod_only, vérifier que le nouvel ajout est modérateur
        if ($conversation['type'] === Conversation::TYPE_MOD_ONLY && ($newUser['global_role'] ?? '') !== 'moderator') {
            $this->setFlash('danger', 'Seuls les modérateurs peuvent être ajoutés à cette conversation.');
            $this->redirect('/messages/' . $convId);
            return;
        }

        $this->conversationModel->addParticipant($convId, $newUserId);

        $this->notifModel->notify(
            $newUserId,
            'new_message',
            'Ajouté à la conversation : ' . $conversation['subject'],
            null,
            '/messages/' . $convId
        );

        $this->setFlash('success', e($newUser['username']) . ' a été ajouté à la conversation.');
        $this->redirect('/messages/' . $convId);
    }
}
