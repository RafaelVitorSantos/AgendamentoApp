<?php

namespace App\Middleware;

/**
 * Middleware de autenticação.
 * Verifica se o usuário está logado e a sessão é válida.
 */
class AuthMiddleware
{
    public function handle(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['_intended_url'] = $_SERVER['REQUEST_URI'];
            redirect(url('login'));
        }

        if (!isset($_SESSION['tenant_id'])) {
            session_destroy();
            redirect(url('login'));
        }

        // Valida fingerprint para detectar sequestro de sessão
        if (isset($_SESSION['_fingerprint'])) {
            $currentFingerprint = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            if (!hash_equals($_SESSION['_fingerprint'], $currentFingerprint)) {
                session_destroy();
                redirect(url('login?expired=1'));
            }
        }

        // Verifica timeout de inatividade (30 minutos)
        $maxInactivity = 30 * 60;
        if (isset($_SESSION['_last_activity'])) {
            if (time() - $_SESSION['_last_activity'] > $maxInactivity) {
                session_destroy();
                redirect(url('login?expired=1'));
            }
        }
        $_SESSION['_last_activity'] = time();

        // Verifica timeout absoluto (8 horas)
        $maxLifetime = 8 * 60 * 60;
        if (isset($_SESSION['_login_time'])) {
            if (time() - $_SESSION['_login_time'] > $maxLifetime) {
                session_destroy();
                redirect(url('login?expired=1'));
            }
        }
    }
}
