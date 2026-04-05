<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Account;
use App\Models\Ticket;
use App\Models\TicketMessage;

class TicketController extends Controller
{
    private Ticket        $ticketModel;
    private TicketMessage $messageModel;
    private Account       $accountModel;

    public function __construct()
    {
        $this->ticketModel  = new Ticket();
        $this->messageModel = new TicketMessage();
        $this->accountModel = new Account();
    }

    // --------------------------------------------------------
    // Liste des tickets de l'utilisateur
    // --------------------------------------------------------

    public function index(): void
    {
        $this->requireAuth();

        $userId  = $this->getCurrentUserId();
        $tickets = $this->ticketModel->getByUser($userId);

        $this->render('tickets/index', [
            'title'   => 'Mes demandes',
            'tickets' => $tickets,
        ]);
    }

    // --------------------------------------------------------
    // Formulaire de création
    // --------------------------------------------------------

    public function createForm(): void
    {
        $this->requireAuth();

        $userId   = $this->getCurrentUserId();
        $accounts = $this->accountModel->getAccessibleAccounts($userId);

        $this->render('tickets/create', [
            'title'    => 'Nouvelle demande',
            'types'    => Ticket::TYPES,
            'accounts' => $accounts,
        ]);
    }

    // --------------------------------------------------------
    // Traitement de la création
    // --------------------------------------------------------

    public function store(): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $userId = $this->getCurrentUserId();
        $data   = $this->getPostData(['type', 'subject', 'body', 'account_id']);

        // Validation type
        if (!array_key_exists($data['type'], Ticket::TYPES)) {
            $this->setFlash('danger', 'Type de demande invalide.');
            $this->redirect('/tickets/create');
            return;
        }

        // Validation sujet
        $subject = trim($data['subject']);
        if (mb_strlen($subject) < 5 || mb_strlen($subject) > 255) {
            $this->setFlash('danger', 'Le sujet doit contenir entre 5 et 255 caractères.');
            $this->redirect('/tickets/create');
            return;
        }

        // Validation corps du message
        $body = trim($data['body']);
        if (mb_strlen($body) < 10) {
            $this->setFlash('danger', 'Veuillez détailler votre demande (minimum 10 caractères).');
            $this->redirect('/tickets/create');
            return;
        }

        // Compte associé (facultatif)
        $accountId = ($data['account_id'] !== '' && $data['account_id'] !== '0')
            ? (int) $data['account_id']
            : null;

        // Vérifier que l'utilisateur a accès au compte indiqué
        if ($accountId !== null) {
            $account = $this->accountModel->find($accountId);
            if (!$account || !$this->accountModel->hasAccess($accountId, $userId)) {
                $this->setFlash('danger', 'Compte associé invalide.');
                $this->redirect('/tickets/create');
                return;
            }
        }

        // Créer le ticket
        $ticketId = $this->ticketModel->create([
            'user_id'    => $userId,
            'type'       => $data['type'],
            'subject'    => $subject,
            'status'     => 'open',
            'account_id' => $accountId,
        ]);

        // Poster le premier message
        $this->messageModel->post($ticketId, $userId, $body, false);

        $this->setFlash('success', 'Votre demande a été soumise. L\'équipe de modération vous répondra dans les meilleurs délais.');
        $this->redirect('/tickets/' . $ticketId);
    }

    // --------------------------------------------------------
    // Détail d'un ticket + échange de messages
    // --------------------------------------------------------

    public function show(string $id): void
    {
        $this->requireAuth();

        $ticketId = (int) $id;
        $userId   = $this->getCurrentUserId();

        $ticket = $this->ticketModel->findWithUser($ticketId);
        if (!$ticket || (int) $ticket['user_id'] !== $userId) {
            $this->setFlash('danger', 'Ticket introuvable.');
            $this->redirect('/tickets');
            return;
        }

        $messages = $this->messageModel->getByTicket($ticketId);
        $account  = $ticket['account_id'] ? $this->accountModel->find((int) $ticket['account_id']) : null;

        $this->render('tickets/show', [
            'title'    => 'Demande #' . $ticketId . ' — ' . $ticket['subject'],
            'ticket'   => $ticket,
            'messages' => $messages,
            'account'  => $account,
            'statuses' => Ticket::STATUSES,
        ]);
    }

    // --------------------------------------------------------
    // Réponse de l'utilisateur
    // --------------------------------------------------------

    public function reply(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $ticketId = (int) $id;
        $userId   = $this->getCurrentUserId();

        $ticket = $this->ticketModel->find($ticketId);
        if (!$ticket || (int) $ticket['user_id'] !== $userId) {
            $this->setFlash('danger', 'Ticket introuvable.');
            $this->redirect('/tickets');
            return;
        }

        if (Ticket::isClosed($ticket['status'])) {
            $this->setFlash('warning', 'Ce ticket est clôturé et ne peut plus recevoir de réponses.');
            $this->redirect('/tickets/' . $ticketId);
            return;
        }

        $data = $this->getPostData(['body']);
        $body = trim($data['body']);
        if (mb_strlen($body) < 2) {
            $this->setFlash('danger', 'Réponse trop courte.');
            $this->redirect('/tickets/' . $ticketId);
            return;
        }

        $this->messageModel->post($ticketId, $userId, $body, false);

        // Repasser en "open" si le statut était "pending_user"
        if ($ticket['status'] === 'pending_user') {
            $this->ticketModel->update($ticketId, ['status' => 'open']);
        } else {
            // Met à jour updated_at
            $this->ticketModel->update($ticketId, ['status' => $ticket['status']]);
        }

        $this->setFlash('success', 'Réponse envoyée.');
        $this->redirect('/tickets/' . $ticketId . '#messages');
    }

    // --------------------------------------------------------
    // Fermeture par l'utilisateur
    // --------------------------------------------------------

    public function close(string $id): void
    {
        $this->requireAuth();
        $this->validateCSRF();

        $ticketId = (int) $id;
        $userId   = $this->getCurrentUserId();

        $ticket = $this->ticketModel->find($ticketId);
        if (!$ticket || (int) $ticket['user_id'] !== $userId) {
            $this->setFlash('danger', 'Ticket introuvable.');
            $this->redirect('/tickets');
            return;
        }

        if (Ticket::isClosed($ticket['status'])) {
            $this->redirect('/tickets/' . $ticketId);
            return;
        }

        $this->ticketModel->update($ticketId, ['status' => 'closed']);
        $this->setFlash('info', 'Ticket marqué comme clôturé.');
        $this->redirect('/tickets/' . $ticketId);
    }
}
