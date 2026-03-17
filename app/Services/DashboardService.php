<?php

namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext;

/**
 * Dados agregados para o dashboard da empresa.
 */
class DashboardService
{
    private int $tenantId;

    public function __construct()
    {
        $this->tenantId = TenantContext::require();
    }

    public function getTodayStats(): array
    {
        $db = Database::getInstance();
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
    }

    public function getTodayRevenue(): array
    {
        $db = Database::getInstance();
        $today = date('Y-m-d');

        $stmt = $db->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN type='income' AND status='paid' THEN amount ELSE 0 END), 0) as income,
                COALESCE(SUM(CASE WHEN type='expense' AND status='paid' THEN amount ELSE 0 END), 0) as expense
             FROM financial_transactions WHERE tenant_id = ? AND reference_date = ?"
        );
        $stmt->execute([$this->tenantId, $today]);
        return $stmt->fetch();
    }

    public function getOccupancyRate(): float
    {
        $db = Database::getInstance();
        $today = date('Y-m-d');
        $dayOfWeek = (int) date('w');

        // Total de minutos disponíveis (soma de todos profissionais)
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)), 0) as total
             FROM professional_working_hours
             WHERE tenant_id = ? AND day_of_week = ? AND is_active = 1"
        );
        $stmt->execute([$this->tenantId, $dayOfWeek]);
        $available = (int) $stmt->fetchColumn();

        if ($available === 0) return 0;

        // Total de minutos agendados
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(duration_minutes), 0) as total
             FROM appointments
             WHERE tenant_id = ? AND date = ?
             AND status NOT IN ('cancelled_by_client', 'cancelled_by_business', 'no_show')"
        );
        $stmt->execute([$this->tenantId, $today]);
        $booked = (int) $stmt->fetchColumn();

        return round(min(($booked / $available) * 100, 100), 1);
    }

    public function getUpcomingAppointments(int $limit = 10): array
    {
        $db = Database::getInstance();
        $now = date('H:i:s');
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
    }

    public function getTopServices(int $limit = 5): array
    {
        $db = Database::getInstance();
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
    }

    public function getWeeklyRevenue(): array
    {
        $db = Database::getInstance();
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
    }

    public function getPendingConfirmations(): int
    {
        $db = Database::getInstance();
        $today = date('Y-m-d');

        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM appointments
             WHERE tenant_id = ? AND date >= ? AND status = 'scheduled'"
        );
        $stmt->execute([$this->tenantId, $today]);
        return (int) $stmt->fetchColumn();
    }

    public function getTodayBirthdays(): array
    {
        $db = Database::getInstance();
        $monthDay = date('m-d');

        $stmt = $db->prepare(
            "SELECT id, name, phone FROM clients
             WHERE tenant_id = ? AND deleted_at IS NULL
             AND DATE_FORMAT(birth_date, '%m-%d') = ?"
        );
        $stmt->execute([$this->tenantId, $monthDay]);
        return $stmt->fetchAll();
    }
}
