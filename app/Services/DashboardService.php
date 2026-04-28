<?php

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;
use App\Core\TenantContext;

/**
 * Dados agregados para o dashboard da empresa.
 * Métricas em tempo real têm TTL curto (60s).
 * Rankings e dados históricos têm TTL maior (300s).
 */
class DashboardService
{
    private int   $tenantId;
    private Cache $cache;

    public function __construct()
    {
        $this->tenantId = TenantContext::require();
        $this->cache    = Cache::getInstance();
    }

    public function getTodayStats(): array
    {
        $key = "dashboard:today_stats:{$this->tenantId}:" . date('Y-m-d');

        return $this->cache->remember($key, 60, function () {
            $db    = Database::getInstance();
            $today = date('Y-m-d');

            $stmt = $db->prepare(
                "SELECT
                    COUNT(*) as total_appointments,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status IN ('scheduled','confirmed') THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_shows,
                    SUM(CASE WHEN status LIKE 'cancelled%' THEN 1 ELSE 0 END) as cancelled
                 FROM appointments WHERE tenant_id = ? AND date = ?"
            );
            $stmt->execute([$this->tenantId, $today]);
            return $stmt->fetch();
        });
    }

    public function getTodayRevenue(): array
    {
        $key = "dashboard:today_revenue:{$this->tenantId}:" . date('Y-m-d');

        return $this->cache->remember($key, 60, function () {
            $db    = Database::getInstance();
            $today = date('Y-m-d');

            $stmt = $db->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN type='income' AND status='paid' THEN amount ELSE 0 END), 0) as income,
                    COALESCE(SUM(CASE WHEN type='expense' AND status='paid' THEN amount ELSE 0 END), 0) as expense
                 FROM financial_transactions WHERE tenant_id = ? AND reference_date = ?"
            );
            $stmt->execute([$this->tenantId, $today]);
            return $stmt->fetch();
        });
    }

    public function getOccupancyRate(): float
    {
        $key = "dashboard:occupancy:{$this->tenantId}:" . date('Y-m-d');

        return $this->cache->remember($key, 120, function () {
            $db        = Database::getInstance();
            $today     = date('Y-m-d');
            $dayOfWeek = (int) date('w');

            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)), 0) as total
                 FROM professional_working_hours
                 WHERE tenant_id = ? AND day_of_week = ? AND is_active = 1"
            );
            $stmt->execute([$this->tenantId, $dayOfWeek]);
            $available = (int) $stmt->fetchColumn();

            if ($available === 0) return 0.0;

            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(duration_minutes), 0) as total
                 FROM appointments
                 WHERE tenant_id = ? AND date = ?
                 AND status NOT IN ('cancelled_by_client', 'cancelled_by_business', 'no_show')"
            );
            $stmt->execute([$this->tenantId, $today]);
            $booked = (int) $stmt->fetchColumn();

            return round(min(($booked / $available) * 100, 100), 1);
        });
    }

    public function getUpcomingAppointments(int $limit = 10): array
    {
        // Próximos agendamentos mudam com frequência — TTL curto
        $key = "dashboard:upcoming:{$this->tenantId}:" . date('Y-m-d-H-i');

        return $this->cache->remember($key, 30, function () use ($limit) {
            $db    = Database::getInstance();
            $now   = date('H:i:s');
            $today = date('Y-m-d');

            $stmt = $db->prepare(
                "SELECT a.*, c.name as client_name, c.phone as client_phone,
                        p.name as professional_name, p.color as professional_color,
                        s.name as service_name
                 FROM appointments a
                 LEFT JOIN clients c ON c.id = a.client_id
                 JOIN professionals p ON p.id = a.professional_id
                 JOIN services s ON s.id = a.service_id
                 WHERE a.tenant_id = ? AND a.date = ?
                 AND a.status IN ('scheduled', 'confirmed', 'in_progress')
                 AND a.start_time >= ?
                 ORDER BY a.start_time ASC LIMIT ?"
            );
            $stmt->execute([$this->tenantId, $today, $now, $limit]);
            return $stmt->fetchAll();
        });
    }

    public function getTopServices(int $limit = 5): array
    {
        // Ranking mensal — atualiza a cada 5 minutos
        $key = "dashboard:top_services:{$this->tenantId}:" . date('Y-m');

        return $this->cache->remember($key, 300, function () use ($limit) {
            $db         = Database::getInstance();
            $monthStart = date('Y-m-01');

            $stmt = $db->prepare(
                "SELECT s.name, COUNT(a.id) as total, SUM(a.price) as revenue
                 FROM appointments a
                 JOIN services s ON s.id = a.service_id
                 WHERE a.tenant_id = ? AND a.date >= ?
                 AND a.status NOT IN ('cancelled_by_client', 'cancelled_by_business')
                 GROUP BY s.id, s.name
                 ORDER BY total DESC LIMIT ?"
            );
            $stmt->execute([$this->tenantId, $monthStart, $limit]);
            return $stmt->fetchAll();
        });
    }

    public function getWeeklyRevenue(): array
    {
        $key = "dashboard:weekly_revenue:{$this->tenantId}:" . date('Y-W');

        return $this->cache->remember($key, 300, function () {
            $db        = Database::getInstance();
            $weekStart = date('Y-m-d', strtotime('monday this week'));

            $stmt = $db->prepare(
                "SELECT DATE(reference_date) as day,
                        SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income,
                        SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense
                 FROM financial_transactions
                 WHERE tenant_id = ? AND reference_date >= ? AND status = 'paid'
                 GROUP BY DATE(reference_date)
                 ORDER BY day ASC"
            );
            $stmt->execute([$this->tenantId, $weekStart]);
            return $stmt->fetchAll();
        });
    }

    public function getPendingConfirmations(): int
    {
        $key = "dashboard:pending:{$this->tenantId}:" . date('Y-m-d-H');

        return $this->cache->remember($key, 60, function () {
            $db    = Database::getInstance();
            $today = date('Y-m-d');

            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM appointments
                 WHERE tenant_id = ? AND date >= ? AND status = 'scheduled'"
            );
            $stmt->execute([$this->tenantId, $today]);
            return (int) $stmt->fetchColumn();
        });
    }

    public function getTodayBirthdays(): array
    {
        $key = "dashboard:birthdays:{$this->tenantId}:" . date('Y-m-d');

        return $this->cache->remember($key, 3600, function () {
            $db       = Database::getInstance();
            $monthDay = date('m-d');

            $stmt = $db->prepare(
                "SELECT id, name, phone FROM clients
                 WHERE tenant_id = ? AND deleted_at IS NULL
                 AND DATE_FORMAT(birth_date, '%m-%d') = ?"
            );
            $stmt->execute([$this->tenantId, $monthDay]);
            return $stmt->fetchAll();
        });
    }

    /**
     * Invalida todo o cache do dashboard (chamar após criar/cancelar agendamento).
     */
    public function invalidate(): void
    {
        $today = date('Y-m-d');
        $keys  = [
            "dashboard:today_stats:{$this->tenantId}:{$today}",
            "dashboard:today_revenue:{$this->tenantId}:{$today}",
            "dashboard:occupancy:{$this->tenantId}:{$today}",
            "dashboard:pending:{$this->tenantId}:" . date('Y-m-d-H'),
        ];
        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
    }
}
