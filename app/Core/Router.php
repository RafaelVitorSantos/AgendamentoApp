<?php

namespace App\Core;

/**
 * Roteador simples baseado em regex.
 * Suporta GET, POST, PUT, PATCH, DELETE e middleware.
 */
class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    public function addGlobalMiddleware(string $middlewareClass): void
    {
        $this->globalMiddleware[] = $middlewareClass;
    }

    public function group(array $options, callable $callback): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        if (isset($options['prefix'])) {
            $this->prefix .= '/' . trim($options['prefix'], '/');
        }

        if (isset($options['middleware'])) {
            $middlewares = is_array($options['middleware'])
                ? $options['middleware']
                : [$options['middleware']];
            $this->groupMiddleware = array_merge($this->groupMiddleware, $middlewares);
        }

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function get(string $uri, string|array $action, array $middleware = []): void
    {
        $this->addRoute('GET', $uri, $action, $middleware);
    }

    public function post(string $uri, string|array $action, array $middleware = []): void
    {
        $this->addRoute('POST', $uri, $action, $middleware);
    }

    public function put(string $uri, string|array $action, array $middleware = []): void
    {
        $this->addRoute('PUT', $uri, $action, $middleware);
    }

    public function patch(string $uri, string|array $action, array $middleware = []): void
    {
        $this->addRoute('PATCH', $uri, $action, $middleware);
    }

    public function delete(string $uri, string|array $action, array $middleware = []): void
    {
        $this->addRoute('DELETE', $uri, $action, $middleware);
    }

    private function addRoute(string $method, string $uri, string|array $action, array $middleware): void
    {
        $uri = $this->prefix . '/' . trim($uri, '/');
        $uri = '/' . trim($uri, '/');

        if (is_string($action)) {
            [$controller, $method_name] = explode('@', $action);
            $action = [$controller, $method_name];
        }

        $allMiddleware = array_merge($this->groupMiddleware, $middleware);

        $this->routes[] = [
            'method'     => $method,
            'uri'        => $uri,
            'action'     => $action,
            'middleware'  => $allMiddleware,
        ];
    }

    public function dispatch(string $method, string $uri): mixed
    {
        $method = strtoupper($method);

        // Suporte a _method para formulários HTML
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        $uri = '/' . trim(parse_url($uri, PHP_URL_PATH), '/');

        // Remove base path da URL (ex: /AgendamentoApp)
        $basePath = parse_url(config('app.url', ''), PHP_URL_PATH) ?? '';
        if ($basePath && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
            $uri = '/' . ltrim($uri, '/');
        }

        // Quando a app está em subpasta, Apache pode passar /public ou /public/qualquer-coisa
        if (str_starts_with($uri, '/public')) {
            $uri = substr($uri, 7); // remove '/public'
            $uri = '/' . ltrim($uri, '/');
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->uriToRegex($route['uri']);

            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Executa middleware global
                foreach ($this->globalMiddleware as $mw) {
                    $instance = new $mw();
                    $instance->handle();
                }

                // Executa middleware da rota
                foreach ($route['middleware'] as $mw) {
                    $instance = new $mw();
                    $instance->handle();
                }

                [$controllerClass, $methodName] = $route['action'];
                $controller = new $controllerClass();

                return call_user_func_array([$controller, $methodName], $params);
            }
        }

        http_response_code(404);
        require_once BASE_PATH . '/resources/views/errors/404.php';
        return null;
    }

    private function uriToRegex(string $uri): string
    {
        // Converte {param} para grupo nomeado
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $uri);
        // Converte {param?} para grupo nomeado opcional
        $pattern = preg_replace('/\{([a-zA-Z_]+)\?\}/', '(?P<$1>[^/]*)?', $pattern);
        return '#^' . $pattern . '$#';
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
