<?php

namespace App\Models;

use App\Core\Model;

class Client extends Model
{
    protected string $table = 'clients';
    protected bool $tenantScoped = true;
    protected bool $softDelete = true;

    /**
     * Busca por telefone (para WhatsApp/identificação rápida).
     */
    public function findByPhone(string $phone): ?array
    {
        $clean = preg_replace('/\D/', '', $phone);
        $stmt = $this->db->prepare(
            "SELECT * FROM clients
             WHERE tenant_id = ? AND REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', '') LIKE ?
             AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$this->getTenantId(), "%{$clean}"]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Top clientes por número de visitas.
     */
    public function getTopByVisits(int $limit = 10, string $period = '90 days'): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.name, c.phone, c.total_visits, c.total_spent, c.last_visit_at,
                    COUNT(a.id) as period_visits
             FROM clients c
             LEFT JOIN appointments a ON a.client_id = c.id 
                AND a.status = 'completed' 
                AND a.date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
             WHERE c.tenant_id = ? AND c.deleted_at IS NULL
             GROUP BY c.id
             ORDER BY period_visits DESC
             LIMIT ?"
        );
        $stmt->execute([$this->getTenantId(), $limit]);
        return $stmt->fetchAll();
    }
}
