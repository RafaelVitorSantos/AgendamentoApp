<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\TenantContext;

class ReportController extends Controller
{
    public function index(): void
    {
        $this->authorize('reports.view');

        $period = $this->input('period', 'month');
        [$dateFrom, $dateTo] = $this->resolvePeriod($period);
        $tenantId = TenantContext::require();
        $db = Database::getInstance();

        // Faturamento total e agendamentos
        $stmt = $db->prepare(
            "SELECT
                COUNT(*) as total_appointments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status IN ('cancelled_by_client','cancelled_by_business') THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_shows,
                SUM(CASE WHEN status = 'completed' THEN price ELSE 0 END) as revenue
             FROM appointments
             WHERE tenant_id = ? AND date BETWEEN ? AND ?"
        );
        $stmt->execute([$tenantId, $dateFrom, $dateTo]);
        $overview = $stmt->fetch();

        // Top 5 serviços
        $stmt = $db->prepare(
            "SELECT s.name, COUNT(a.id) as total, SUM(a.price) as revenue
             FROM appointments a
             JOIN services s ON s.id = a.service_id
             WHERE a.tenant_id = ? AND a.date BETWEEN ? AND ? AND a.status = 'completed'
             GROUP BY a.service_id, s.name
             ORDER BY total DESC LIMIT 5"
        );
        $stmt->execute([$tenantId, $dateFrom, $dateTo]);
        $topServices = $stmt->fetchAll();

        // Top 5 profissionais
        $stmt = $db->prepare(
            "SELECT p.name, COUNT(a.id) as total, SUM(a.price) as revenue
             FROM appointments a
             JOIN professionals p ON p.id = a.professional_id
             WHERE a.tenant_id = ? AND a.date BETWEEN ? AND ? AND a.status = 'completed'
             GROUP BY a.professional_id, p.name
             ORDER BY total DESC LIMIT 5"
        );
        $stmt->execute([$tenantId, $dateFrom, $dateTo]);
        $topProfessionals = $stmt->fetchAll();

        // Atendimentos por dia (para gráfico)
        $stmt = $db->prepare(
            "SELECT date, COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
             FROM appointments
             WHERE tenant_id = ? AND date BETWEEN ? AND ?
             GROUP BY date ORDER BY date ASC"
        );
        $stmt->execute([$tenantId, $dateFrom, $dateTo]);
        $byDay = $stmt->fetchAll();

        // Top 5 clientes
        $stmt = $db->prepare(
            "SELECT c.name, COUNT(a.id) as visits, SUM(a.price) as spent
             FROM appointments a
             JOIN clients c ON c.id = a.client_id
             WHERE a.tenant_id = ? AND a.date BETWEEN ? AND ? AND a.status = 'completed'
             GROUP BY a.client_id, c.name
             ORDER BY visits DESC LIMIT 5"
        );
        $stmt->execute([$tenantId, $dateFrom, $dateTo]);
        $topClients = $stmt->fetchAll();

        // Horários mais movimentados (agrupado por hora)
        $stmt = $db->prepare(
            "SELECT HOUR(start_time) as hour, COUNT(*) as total
             FROM appointments
             WHERE tenant_id = ? AND date BETWEEN ? AND ? AND status = 'completed'
             GROUP BY HOUR(start_time) ORDER BY total DESC LIMIT 8"
        );
        $stmt->execute([$tenantId, $dateFrom, $dateTo]);
        $busyHours = $stmt->fetchAll();

        $this->render('reports.index', [
            'overview'         => $overview,
            'topServices'      => $topServices,
            'topProfessionals' => $topProfessionals,
            'topClients'       => $topClients,
            'byDay'            => $byDay,
            'busyHours'        => $busyHours,
            'period'           => $period,
            'dateFrom'         => $dateFrom,
            'dateTo'           => $dateTo,
            'pageTitle'        => 'Relatórios',
        ]);
    }

    private function resolvePeriod(string $period): array
    {
        return match ($period) {
            'week'      => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))],
            'month'     => [date('Y-m-01'), date('Y-m-t')],
            'last_month'=> [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
            'quarter'   => [date('Y-m-01', strtotime('-2 months')), date('Y-m-d')],
            'year'      => [date('Y-01-01'), date('Y-12-31')],
            default     => [date('Y-m-01'), date('Y-m-t')],
        };
    }
}
