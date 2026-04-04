<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Routeur HTTP.
 * Gère l'enregistrement des routes et le dispatch vers les contrôleurs.
 */
class Router
{
    private array $routes = [];

    /**
     * Enregistre une route GET.
     */
    public function get(string $path, string $controller, string $method): self
    {
        $this->routes[] = ['GET', $path, $controller, $method];
        return $this;
    }

    /**
     * Enregistre une route POST.
     */
    public function post(string $path, string $controller, string $method): self
    {
        $this->routes[] = ['POST', $path, $controller, $method];
        return $this;
    }

    /**
     * Enregistre une route PUT.
     */
    public function put(string $path, string $controller, string $method): self
    {
        $this->routes[] = ['PUT', $path, $controller, $method];
        return $this;
    }

    /**
     * Enregistre une route DELETE.
     */
    public function delete(string $path, string $controller, string $method): self
    {
        $this->routes[] = ['DELETE', $path, $controller, $method];
        return $this;
    }

    /**
     * Dispatche la requête courante vers le contrôleur correspondant.
     */
    public function dispatch(?string $requestMethod = null, ?string $requestUri = null): void
    {
        $requestMethod = $requestMethod ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($requestUri ?? ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        // Support PUT/DELETE via champ _method
        if ($requestMethod === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'DELETE'], true)) {
                $requestMethod = $override;
            }
        }

        foreach ($this->routes as [$method, $path, $controller, $action]) {
            if ($method !== $requestMethod) {
                continue;
            }

            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                try {
                    $controllerInstance = new $controller();
                    $ref = new \ReflectionMethod($controller, $action);
                    $args = [];

                    foreach ($ref->getParameters() as $param) {
                        $name = $param->getName();
                        if (isset($params[$name])) {
                            $args[] = $params[$name];
                        } elseif ($param->isDefaultValueAvailable()) {
                            $args[] = $param->getDefaultValue();
                        }
                    }

                    $controllerInstance->$action(...$args);
                } catch (\Throwable $e) {
                    $this->sendError(500, $e);
                }
                return;
            }
        }

        $this->sendError(404);
    }

    /**
     * Envoie une page d'erreur HTTP.
     */
    private function sendError(int $code, ?\Throwable $exception = null): void
    {
        @http_response_code($code);

        $title = match ($code) {
            404 => 'Page non trouvée',
            500 => 'Erreur interne du serveur',
            default => 'Erreur',
        };

        $message = match ($code) {
            404 => 'La page que vous recherchez n\'existe pas.',
            500 => 'Une erreur est survenue.',
            default => 'Une erreur inattendue est survenue.',
        };

        $debug = '';
        if ($exception && (getenv('APP_DEBUG') === 'true')) {
            $debug = $exception->getMessage() . "\n" . $exception->getTraceAsString();
        }

        $viewPath = __DIR__ . '/../Views/errors/error.php';
        if (file_exists($viewPath)) {
            extract(compact('code', 'title', 'message', 'debug'));
            include $viewPath;
        } else {
            echo "<h1>$code — $title</h1><p>$message</p>";
            if ($debug) {
                echo "<pre>" . htmlspecialchars($debug, ENT_QUOTES, 'UTF-8') . "</pre>";
            }
        }
    }
}
