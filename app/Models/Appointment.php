<?php

namespace App\Models;

use App\Core\Model;

class Appointment extends Model
{
    protected string $table = 'appointments';
    protected bool $tenantScoped = true;

    /**
     * Agendamentos de uma data específica com joins.
     */
    public function getByDate(string $date, ?int $unitId = null, ?int $professionalId = null): array
    {
        $sql = "SELECT a.*, 
                       c.name as client_name, c.phone as client_phone,
                       p.name as professional_name, p.color as professional_color,
                       s.name as service_name, s.duration_minutes as service_duration
                FROM appointments a
                LEFT JOIN clients c ON c.id = a.client_id
                JOIN professionals p ON p.id = a.professional_id
                JOIN services s ON s.id = a.service_id
                WHERE a.tenant_id = ? AND a.date = ?";

        $params = [$this->getTenantId(), $date];

        if ($unitId) {
            $sql .= " AND a.unit_id = ?";
            $params[] = $unitId;
        }

        if ($professionalId) {
            $sql .= " AND a.professional_id = ?";
            $params[] = $professionalId;
        }

        $sql .= " ORDER BY a.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Verifica conflito de horário para um profissional.
     * Dois agendamentos se sobrepõem só se: início do novo < fim do existente E fim do novo > início do existente.
     * Assim, um agendamento que termina às 14:00 permite o próximo começar às 14:00 (sem sobreposição).
     */
    public function hasConflict(int $professionalId, string $date, string $startTime, string $endTime, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM appointments
                WHERE tenant_id = ?
                AND professional_id = ?
                AND date = ?
                AND status NOT IN ('cancelled_by_client', 'cancelled_by_business', 'no_show')
                AND start_time < ? AND end_time > ?";

        $params = [$this->getTenantId(), $professionalId, $date, $endTime, $startTime];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Estatísticas do dia para o dashboard.
     */
    public function getDayStats(string $date): array
    {
        $tenantId = $this->getTenantId();

        $stmt = $this->db->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('scheduled','confirmed','in_progress','completed') THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_shows,
                SUM(CASE WHEN status LIKE 'cancelled%' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'completed' THEN price ELSE 0 END) as revenue
             FROM appointments
             WHERE tenant_id = ? AND date = ?"
        );
        $stmt->execute([$tenantId, $date]);
        return $stmt->fetch();
    }
}
