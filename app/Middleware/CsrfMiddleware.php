<?php

namespace App\Middleware;

/**
 * Middleware de proteção CSRF.
 * Valida token em toda requisição POST/PUT/PATCH/DELETE.
 */
class CsrfMiddleware
{
    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return;
        }

        $token = $_POST['_csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? null;

        if (!$token || !isset($_SESSION['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'], $token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF inválido.']);
            exit;
        }
    }
}
