<?php

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\RateLimiter;
use App\Services\AuthService;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS  = 5;
    private const DECAY_SECONDS = 60;

    public function showLogin(): void
    {
        if (isset($_SESSION['user_id'])) {
            redirect(url('dashboard'));
        }
        $this->view('auth.login');
    }

    public function login(): void
    {
        $errors = $this->validate([
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (!empty($errors)) {
            flash('errors', $errors);
            flash('error', 'Verifique os campos e tente novamente.');
            back();
        }

        $email   = $this->input('email');
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $limiter = new RateLimiter();
        $key     = 'login:' . $ip . ':' . strtolower($email);

        if ($limiter->tooManyAttempts($key, self::MAX_ATTEMPTS, self::DECAY_SECONDS)) {
            $wait = $limiter->availableIn($key, self::DECAY_SECONDS);
            flash('error', "Muitas tentativas. Aguarde {$wait} segundo(s) antes de tentar novamente.");
            flash('old_email', $email);
            redirect(url('login'));
            return;
        }

        $auth = new AuthService();
        $user = $auth->attempt($email, $this->input('password'));

        if (!$user) {
            $limiter->hit($key);
            $remaining = $limiter->remaining($key, self::MAX_ATTEMPTS, self::DECAY_SECONDS);
            $msg = $remaining > 0
                ? "Email ou senha incorretos. {$remaining} tentativa(s) restante(s)."
                : 'Email ou senha incorretos. Conta temporariamente bloqueada.';
            flash('error', $msg);
            flash('old_email', $email);
            redirect(url('login'));
            return;
        }

        $limiter->clear($key);

        $intended = $_SESSION['_intended_url'] ?? url('dashboard');
        unset($_SESSION['_intended_url']);

        redirect($intended);
    }

    public function logout(): void
    {
        $auth = new AuthService();
        $auth->logout();
        redirect(url('login'));
    }
}
