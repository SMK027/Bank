<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\JWT;
use App\Core\Session;
use App\Core\Middleware;

/**
 * Contrôleur de base pour l'API REST.
 * Gère l'authentification JWT et les réponses JSON.
 *
 * À étendre pour créer des contrôleurs API spécifiques :
 *
 *   class UserApiController extends ApiController
 *   {
 *       public function index(): void
 *       {
 *           $this->requireAuth();
 *           $this->json(['success' => true, 'users' => [...]]);
 *       }
 *   }
 */
abstract class ApiController
{
    protected ?int $userId = null;
    protected ?array $userPayload = null;

    /**
     * Retourne une réponse JSON.
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Retourne une erreur JSON.
     */
    protected function error(string $message, int $status = 400): void
    {
        $this->json(['success' => false, 'message' => $message], $status);
    }

    /**
     * Récupère le token JWT du header Authorization.
     */
    protected function getBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Exige une authentification JWT valide.
     */
    protected function requireAuth(): void
    {
        $token = $this->getBearerToken();
        if (!$token) {
            $this->error('Token d\'authentification requis.', 401);
        }

        $payload = JWT::decode($token);
        if (!$payload || empty($payload['user_id'])) {
            $this->error('Token invalide ou expiré.', 401);
        }

        $this->userId = (int) $payload['user_id'];
        $this->userPayload = $payload;
    }

    /**
     * Récupère le corps JSON de la requête.
     */
    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Vérifie que l'utilisateur possède un rôle global autorisé.
     */
    protected function requireGlobalRole(array $roles): void
    {
        if (!$this->userPayload || !in_array($this->userPayload['global_role'] ?? '', $roles, true)) {
            $this->error('Permissions insuffisantes.', 403);
        }
    }
}
