<?php

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Services\AuthService;

class RegisterController extends Controller
{
    public function showRegister(): void
    {
        if (isset($_SESSION['user_id'])) {
            redirect(url('dashboard'));
        }
        $this->view('auth.register');
    }

    public function register(): void
    {
        $errors = $this->validate([
            'company_name' => 'required|min:3|max:255',
            'name'         => 'required|min:3|max:255',
            'email'        => 'required|email',
            'phone'        => 'required|min:10',
            'password'     => 'required|min:8',
        ]);

        if (!empty($errors)) {
            flash('errors', $errors);
            flash('error', 'Verifique os campos e tente novamente.');
            back();
        }

        $password = $this->input('password');
        $passwordConfirm = $this->input('password_confirmation');

        if ($password !== $passwordConfirm) {
            flash('error', 'As senhas não conferem.');
            back();
        }

        // Verifica se email já existe
        $auth = new AuthService();
        $existing = (new \App\Models\User())->findByEmail($this->input('email'));

        if ($existing) {
            flash('error', 'Este email já está cadastrado.');
            back();
        }

        try {
            $result = $auth->register(
                [
                    'company_name' => $this->input('company_name'),
                    'phone'        => $this->input('phone'),
                ],
                [
                    'name'     => $this->input('name'),
                    'email'    => $this->input('email'),
                    'password' => $password,
                ]
            );

            // Auto-login após registro
            $auth->attempt($this->input('email'), $password);

            flash('success', 'Empresa cadastrada com sucesso! Configure seus dados.');
            redirect(url('dashboard'));
        } catch (\Exception $e) {
            error_log('Registration failed: ' . $e->getMessage());
            flash('error', 'Erro ao criar conta. Tente novamente.');
            back();
        }
    }
}
