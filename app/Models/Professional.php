<?php

namespace App\Models;

use App\Core\Model;

class Professional extends Model
{
    protected string $table = 'professionals';
    protected bool $tenantScoped = true;
    protected bool $softDelete = true;

    public function getActiveWithServices(): array
    {
        $tenantId = $this->getTenantId();

        // Query única com LEFT JOIN — elimina N+1 queries do loop original.
        // JSON_OBJECT é robusto: trata NULL corretamente e não quebra com nomes que contenham '|'.
        $stmt = $this->db->prepare(
            "SELECT p.*,
                    GROUP_CONCAT(
                        JSON_OBJECT(
                            'id',               s.id,
                            'name',             s.name,
                            'duration_minutes', s.duration_minutes,
                            'price',            s.price,
                            'custom_duration',  ps.custom_duration,
                            'custom_price',     ps.custom_price
                        ) ORDER BY s.name ASC SEPARATOR ';;'
                    ) AS services_raw
             FROM professionals p
             LEFT JOIN professional_services ps ON ps.professional_id = p.id AND ps.tenant_id = p.tenant_id
             LEFT JOIN services s ON s.id = ps.service_id AND s.is_active = 1 AND s.deleted_at IS NULL
             WHERE p.tenant_id = ? AND p.is_active = 1 AND p.deleted_at IS NULL
             GROUP BY p.id
             ORDER BY p.sort_order ASC, p.name ASC"
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$prof) {
            $prof['services'] = [];
            if (!empty($prof['services_raw'])) {
                foreach (explode(';;', $prof['services_raw']) as $raw) {
                    $svc = json_decode($raw, true);
                    if (empty($svc['id'])) continue;
                    $prof['services'][] = [
                        'id'               => (int) $svc['id'],
                        'name'             => $svc['name'] ?? '',
                        'duration_minutes' => (int) ($svc['duration_minutes'] ?? 0),
                        'price'            => (float) ($svc['price'] ?? 0),
                        'custom_duration'  => $svc['custom_duration'] !== null ? (int) $svc['custom_duration'] : null,
                        'custom_price'     => $svc['custom_price']    !== null ? (float) $svc['custom_price']  : null,
                    ];
                }
            }
            unset($prof['services_raw']);
        }

        return $rows;
    }

    public function getAllWithServiceCount(): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM professional_services ps WHERE ps.professional_id = p.id AND ps.tenant_id = p.tenant_id) as service_count
             FROM professionals p
             WHERE p.tenant_id = ? AND p.deleted_at IS NULL
             ORDER BY p.is_active DESC, p.sort_order ASC, p.name ASC"
        );
        $stmt->execute([$this->getTenantId()]);
        return $stmt->fetchAll();
    }

    public function getServices(int $professionalId): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, s.name FROM professional_services ps
             JOIN services s ON s.id = ps.service_id
             WHERE ps.professional_id = ? AND ps.tenant_id = ? AND s.deleted_at IS NULL"
        );
        $stmt->execute([$professionalId, $this->getTenantId()]);
        return array_column($stmt->fetchAll(), 'id');
    }

    public function syncServices(int $professionalId, array $serviceIds): void
    {
        $tenantId = $this->getTenantId();
        $stmt = $this->db->prepare(
            "DELETE FROM professional_services WHERE professional_id = ? AND tenant_id = ?"
        );
        $stmt->execute([$professionalId, $tenantId]);

        if (!empty($serviceIds)) {
            $ins = $this->db->prepare(
                "INSERT IGNORE INTO professional_services (tenant_id, professional_id, service_id) VALUES (?, ?, ?)"
            );
            foreach ($serviceIds as $sid) {
                $ins->execute([$tenantId, $professionalId, (int) $sid]);
            }
        }
    }

    /**
     * Calcula a taxa de ocupação do profissional em um período.
     */
    public function getOccupancyRate(int $professionalId, string $dateFrom, string $dateTo): float
    {
        $tenantId = $this->getTenantId();

        $stmt = $this->db->prepare(
            "SELECT SUM(duration_minutes) as booked_minutes
             FROM appointments
             WHERE tenant_id = ? AND professional_id = ?
             AND date BETWEEN ? AND ?
             AND status NOT IN ('cancelled_by_client', 'cancelled_by_business', 'no_show')"
        );
        $stmt->execute([$tenantId, $professionalId, $dateFrom, $dateTo]);
        $booked = (int) ($stmt->fetch()['booked_minutes'] ?? 0);

        $stmt = $this->db->prepare(
            "SELECT SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as available_minutes
             FROM professional_working_hours
             WHERE tenant_id = ? AND professional_id = ? AND is_active = 1"
        );
        $stmt->execute([$tenantId, $professionalId]);
        $weeklyMinutes = (int) ($stmt->fetch()['available_minutes'] ?? 0);

        if ($weeklyMinutes === 0) return 0;

        $days = max(1, (strtotime($dateTo) - strtotime($dateFrom)) / 86400);
        $weeks = $days / 7;
        $totalAvailable = $weeklyMinutes * $weeks;

        return $totalAvailable > 0 ? round(($booked / $totalAvailable) * 100, 1) : 0;
    }
}
