<?php

/**
 * AgendaPRO SaaS — Front Controller.
 * Todas as requisições passam por aqui via .htaccess.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Router;
use App\Middleware\SecurityHeadersMiddleware;

// Headers de segurança em todas as requisições
(new SecurityHeadersMiddleware())->handle();

// Inicializa router e carrega rotas
$router = new Router();
require_once BASE_PATH . '/routes/web.php';

// Despacha a requisição
$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'];

try {
    $router->dispatch($method, $uri);
} catch (\Throwable $e) {
    if (config('app.debug')) {
        http_response_code(500);
        echo '<h1>Erro</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        require_once BASE_PATH . '/resources/views/errors/500.php';
    }
}
