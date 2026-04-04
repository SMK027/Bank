<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contrôleur abstrait de base.
 * Fournit les méthodes communes à tous les contrôleurs.
 */
abstract class Controller
{
    /**
     * Affiche une vue avec les données fournies.
     */
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        extract($data);

        // Message flash
        $flash = Session::getFlash();

        ob_start();
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        }
        $content = ob_get_clean();

        $layoutPath = __DIR__ . '/../Views/layouts/' . $layout . '.php';
        if (file_exists($layoutPath)) {
            include $layoutPath;
        } else {
            echo $content;
        }
    }

    /**
     * Redirige vers une URL.
     */
    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Définit un message flash en session.
     */
    protected function setFlash(string $type, string $message): void
    {
        Session::set('flash', ['type' => $type, 'message' => $message]);
    }

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
     * Retourne une réponse JSON (alias avec exit).
     */
    protected function jsonResponse(array $data, int $status = 200): void
    {
        $this->json($data, $status);
    }

    /**
     * Vérifie si la requête est AJAX.
     */
    protected function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    /**
     * Exige que l'utilisateur soit connecté.
     */
    protected function requireAuth(): void
    {
        if (!Session::get('user_id')) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Authentification requise.'], 401);
            }
            $this->setFlash('danger', 'Vous devez être connecté.');
            $this->redirect('/login');
        }
    }

    /**
     * Exige un rôle global spécifique.
     */
    protected function requireGlobalRole(array $roles): void
    {
        $this->requireAuth();
        $role = Session::get('global_role');
        if (!in_array($role, $roles, true)) {
            $this->setFlash('danger', 'Accès non autorisé.');
            $this->redirect('/');
            exit;
        }
    }

    /**
     * Vérifie si l'utilisateur connecté est modérateur.
     */
    protected function isModerator(): bool
    {
        return Session::get('global_role') === 'moderator';
    }

    /**
     * Exige que l'utilisateur soit modérateur.
     */
    protected function requireModerator(): void
    {
        $this->requireGlobalRole(['moderator']);
    }

    /**
     * Récupère et filtre les données POST.
     */
    protected function getPostData(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            $value = $_POST[$key] ?? '';
            $data[$key] = is_string($value) ? trim($value) : $value;
        }
        return $data;
    }

    /**
     * Vérifie le token CSRF.
     */
    protected function validateCSRF(): void
    {
        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'message' => 'Token de sécurité invalide.'], 403);
            }
            $this->setFlash('danger', 'Token de sécurité invalide. Veuillez réessayer.');
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    /**
     * Retourne l'ID de l'utilisateur connecté.
     */
    protected function getCurrentUserId(): ?int
    {
        $id = Session::get('user_id');
        return $id ? (int) $id : null;
    }
}
