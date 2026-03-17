<?php

namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;

/**
 * Controla os limites de cada plano de assinatura.
 * Verifica se o tenant pode criar mais recursos.
 */
class PlanLimiter
{
    private ?array $planCache = null;

    public function getPlan(): array
    {
        if ($this->planCache !== null) {
            return $this->planCache;
        }

        $tenantId = TenantContext::require();
        $db = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT p.* FROM plans p
             JOIN subscriptions s ON s.plan_id = p.id
             WHERE s.tenant_id = ? AND s.status IN ('active', 'trialing')
             ORDER BY s.created_at DESC LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        $this->planCache = $stmt->fetch() ?: $this->getFreePlan();

        return $this->planCache;
    }

    private function getFreePlan(): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM plans WHERE slug = 'free' LIMIT 1");
        $stmt->execute();
        return $stmt->fetch();
    }

    public function canCreateProfessional(): bool
    {
        $plan = $this->getPlan();
        if ((int) $plan['max_professionals'] === -1) return true;

        $count = $this->countResource('professionals');
        return $count < (int) $plan['max_professionals'];
    }

    public function canCreateUnit(): bool
    {
        $plan = $this->getPlan();
        if ((int) $plan['max_units'] === -1) return true;

        $count = $this->countResource('units');
        return $count < (int) $plan['max_units'];
    }

    public function canCreateAppointment(): bool
    {
        $plan = $this->getPlan();
        if ((int) $plan['max_appointments_month'] === -1) return true;

        $count = $this->countMonthlyAppointments();
        return $count < (int) $plan['max_appointments_month'];
    }

    public function canCreateClient(): bool
    {
        $plan = $this->getPlan();
        if ((int) $plan['max_clients'] === -1) return true;

        $count = $this->countResource('clients');
        return $count < (int) $plan['max_clients'];
    }

    public function hasFeature(string $feature): bool
    {
        $plan = $this->getPlan();
        return !empty($plan["has_{$feature}"]);
    }

    /**
     * Retorna resumo de uso vs limites.
     */
    public function getUsageSummary(): array
    {
        $plan = $this->getPlan();

        return [
            'plan_name' => $plan['name'],
            'plan_slug' => $plan['slug'],
            'professionals' => [
                'used'  => $this->countResource('professionals'),
                'limit' => (int) $plan['max_professionals'],
            ],
            'units' => [
                'used'  => $this->countResource('units'),
                'limit' => (int) $plan['max_units'],
            ],
            'appointments_month' => [
                'used'  => $this->countMonthlyAppointments(),
                'limit' => (int) $plan['max_appointments_month'],
            ],
            'clients' => [
                'used'  => $this->countResource('clients'),
                'limit' => (int) $plan['max_clients'],
            ],
            'features' => [
                'reports'     => !empty($plan['has_reports']),
                'whatsapp'    => !empty($plan['has_whatsapp']),
                'loyalty'     => !empty($plan['has_loyalty']),
                'financial'   => !empty($plan['has_financial']),
                'commissions' => !empty($plan['has_commissions']),
                'reviews'     => !empty($plan['has_reviews']),
            ],
        ];
    }

    private function countResource(string $table): int
    {
        $tenantId = TenantContext::require();
        $db = Database::getInstance();

        $softDelete = in_array($table, ['professionals', 'clients', 'services', 'units', 'users']);
        $extra = $softDelete ? " AND deleted_at IS NULL" : "";

        $activeCheck = in_array($table, ['professionals', 'units', 'services']) ? " AND is_active = 1" : "";

        $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE tenant_id = ?{$extra}{$activeCheck}");
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    private function countMonthlyAppointments(): int
    {
        $tenantId = TenantContext::require();
        $db = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM appointments
             WHERE tenant_id = ?
             AND YEAR(date) = YEAR(CURDATE())
             AND MONTH(date) = MONTH(CURDATE())
             AND status NOT IN ('cancelled_by_client', 'cancelled_by_business')"
        );
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }
}
