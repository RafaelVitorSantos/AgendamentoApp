<?php

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Services\AuthService;

class LoginController extends Controller
{
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

        $auth = new AuthService();
        $user = $auth->attempt($this->input('email'), $this->input('password'));

        if (!$user) {
            flash('error', 'Email ou senha incorretos.');
            flash('old_email', $this->input('email'));
            redirect(url('login'));
            return;
        }

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
