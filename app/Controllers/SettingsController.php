<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\AuditService;

class SettingsController extends Controller
{
    public function index(): void
    {
        $this->authorize('settings.manage');

        $db       = Database::getInstance();
        $tenantId = TenantContext::require();
        $userId   = $this->userId();

        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();

        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $stmt = $db->prepare(
            "SELECT p.*, s.status as sub_status, s.current_period_end, s.billing_cycle
             FROM subscriptions s JOIN plans p ON p.id = s.plan_id
             WHERE s.tenant_id = ? AND s.status IN ('active','trialing')
             ORDER BY s.created_at DESC LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        $subscription = $stmt->fetch();

        $this->render('settings.index', [
            'tenant'       => $tenant,
            'user'         => $user,
            'subscription' => $subscription,
            'tab'          => $this->input('tab', 'company'),
            'pageTitle'    => 'Configurações',
        ]);
    }

    public function updateCompany(): void
    {
        $this->authorize('settings.manage');

        $errors = $this->validate([
            'company_name' => 'required|min:2|max:255',
            'email'        => 'required|email',
        ]);

        if (!empty($errors)) {
            flash('errors', $errors);
            back();
        }

        $db       = Database::getInstance();
        $tenantId = TenantContext::require();

        $stmt = $db->prepare(
            "UPDATE tenants SET
                company_name = ?, trade_name = ?, email = ?, phone = ?,
                document_number = ?, timezone = ?, primary_color = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([
            $this->input('company_name'),
            $this->input('trade_name') ?: $this->input('company_name'),
            $this->input('email'),
            $this->input('phone'),
            $this->input('document_number'),
            $this->input('timezone', 'America/Sao_Paulo'),
            $this->input('primary_color', '#4F46E5'),
            $tenantId,
        ]);

        AuditService::log('update', 'tenants', $tenantId);
        flash('success', 'Dados da empresa atualizados!');
        redirect(url('settings?tab=company'));
    }

    public function updatePassword(): void
    {
        $this->authorize('settings.manage');

        $current = $this->input('current_password');
        $new     = $this->input('new_password');
        $confirm = $this->input('confirm_password');

        if (empty($current) || empty($new)) {
            flash('error', 'Preencha a senha atual e a nova senha.');
            redirect(url('settings?tab=account'));
        }

        if ($new !== $confirm) {
            flash('error', 'As novas senhas não conferem.');
            redirect(url('settings?tab=account'));
        }

        if (strlen($new) < 8) {
            flash('error', 'A nova senha deve ter pelo menos 8 caracteres.');
            redirect(url('settings?tab=account'));
        }

        $db     = Database::getInstance();
        $userId = $this->userId();

        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['password_hash'])) {
            flash('error', 'Senha atual incorreta.');
            redirect(url('settings?tab=account'));
        }

        $hash = password_hash($new, PASSWORD_ARGON2ID);
        $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hash, $userId]);

        flash('success', 'Senha alterada com sucesso!');
        redirect(url('settings?tab=account'));
    }

    public function updateProfile(): void
    {
        $this->authorize('settings.manage');

        $errors = $this->validate(['name' => 'required|min:2']);
        if (!empty($errors)) {
            flash('errors', $errors);
            back();
        }

        $db     = Database::getInstance();
        $userId = $this->userId();

        $stmt = $db->prepare("UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$this->input('name'), $this->input('phone'), $userId]);

        $_SESSION['user_name'] = $this->input('name');

        flash('success', 'Perfil atualizado!');
        redirect(url('settings?tab=account'));
    }
}
