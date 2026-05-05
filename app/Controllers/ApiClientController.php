<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ApiClient;
use App\Models\AuditLog;

/**
 * Modération : gestion des clients API tiers autorisés à utiliser
 * l'API de paiement par carte bancaire.
 */
class ApiClientController extends Controller
{
    private ApiClient $clientModel;

    public function __construct()
    {
        $this->clientModel = new ApiClient();
    }

    public function index(): void
    {
        $this->requireModerator();

        $clients = $this->clientModel->findAll('id', 'DESC');

        // Affichage unique du secret après création
        $newCredentials = $_SESSION['api_client_new'] ?? null;
        unset($_SESSION['api_client_new']);

        $this->render('moderation/api_clients', [
            'title'          => 'Clients API',
            'clients'        => $clients,
            'newCredentials' => $newCredentials,
        ]);
    }

    public function create(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            $this->setFlash('danger', 'Le nom du client est requis (max 150 caractères).');
            $this->redirect('/moderation/api-clients');
            return;
        }

        $creds = $this->clientModel->provision($name, $this->getCurrentUserId());

        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_API_CLIENT_CREATE, [
            'client_id' => $creds['id'],
            'name'      => $name,
        ]);

        // Stocker en session pour affichage UNIQUE après redirection
        $_SESSION['api_client_new'] = [
            'id'         => $creds['id'],
            'name'       => $name,
            'api_key'    => $creds['api_key'],
            'api_secret' => $creds['api_secret'],
        ];

        $this->setFlash('success', 'Client API créé. Le secret n\'est affiché qu\'une seule fois.');
        $this->redirect('/moderation/api-clients');
    }

    public function revoke(int $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $client = $this->clientModel->find($id);
        if (!$client) {
            $this->setFlash('danger', 'Client introuvable.');
            $this->redirect('/moderation/api-clients');
            return;
        }

        $this->clientModel->revoke($id);
        AuditLog::log($this->getCurrentUserId(), AuditLog::ACTION_API_CLIENT_REVOKE, [
            'client_id' => $id,
            'name'      => $client['name'] ?? '',
        ]);

        $this->setFlash('success', 'Client API révoqué.');
        $this->redirect('/moderation/api-clients');
    }
}
