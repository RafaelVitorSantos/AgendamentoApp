<?php

namespace App\Middleware;

use App\Core\TenantContext;

/**
 * Middleware de tenant.
 * Garante que o tenant está identificado e ativo.
 * Camada primária de isolamento de dados.
 */
class TenantMiddleware
{
    public function handle(): void
    {
        $tenantId = $_SESSION['tenant_id'] ?? null;

        if (!$tenantId) {
            http_response_code(403);
            echo json_encode(['error' => 'Tenant não identificado.']);
            exit;
        }

        TenantContext::set((int) $tenantId);

        $tenantData = TenantContext::getData();

        if (!$tenantData) {
            session_destroy();
            redirect(url('login'));
        }

        if ($tenantData['status'] === 'suspended') {
            redirect(url('conta-suspensa'));
        }

        if ($tenantData['status'] === 'cancelled') {
            redirect(url('conta-cancelada'));
        }
    }
}
