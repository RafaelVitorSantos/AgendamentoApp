<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Client;
use App\Models\Unit;

class QueueController extends Controller
{
    public function index(): void
    {
        $this->authorize('appointments.view');

        $db = Database::getInstance();
        $tenantId = $this->tenantId();
        $unitId   = (int) $this->input('unit_id', $_SESSION['default_unit_id'] ?? 0);

        // Busca unidades do tenant
        $unitsStmt = $db->prepare("SELECT id, name FROM units WHERE tenant_id = ? AND is_active = 1 ORDER BY is_default DESC, name ASC");
        $unitsStmt->execute([$tenantId]);
        $units = $unitsStmt->fetchAll();

        if (!$unitId && !empty($units)) {
            $unitId = (int) $units[0]['id'];
        }

        // Fila ativa (não finalizada/cancelada)
        $queue = [];
        if ($unitId) {
            $stmt = $db->prepare(
                "SELECT q.*,
                        COALESCE(c.name, q.client_name) AS display_name,
                        p.name AS professional_name, p.color AS professional_color,
                        s.name AS service_name
                 FROM service_queue q
                 LEFT JOIN clients c ON c.id = q.client_id
                 LEFT JOIN professionals p ON p.id = q.professional_id
                 LEFT JOIN services s ON s.id = q.service_id
                 WHERE q.tenant_id = ? AND q.unit_id = ?
                   AND DATE(q.created_at) = CURDATE()
                   AND q.status NOT IN ('completed','cancelled','no_show')
                 ORDER BY q.priority DESC, q.position ASC, q.created_at ASC"
            );
            $stmt->execute([$tenantId, $unitId]);
            $queue = $stmt->fetchAll();
        }

        // Estatísticas do dia
        $stats = $this->getDayStats($tenantId, $unitId);

        $professionalModel = new Professional();
        $serviceModel      = new Service();

        $this->render('queue.index', [
            'pageTitle'    => 'Fila de Atendimento',
            'queue'        => $queue,
            'units'        => $units,
            'currentUnit'  => $unitId,
            'stats'        => $stats,
            'professionals' => $professionalModel->all(['is_active' => 1], 'name ASC'),
            'services'     => $serviceModel->all(['is_active' => 1], 'name ASC'),
            'clients'      => (new Client())->all(['is_active' => 1], 'name ASC'),
        ]);
    }

    public function store(): void
    {
        $this->authorize('appointments.create');

        $db = Database::getInstance();
        $tenantId = $this->tenantId();
        $unitId   = (int) $this->input('unit_id');

        if (!$unitId) {
            flash('error', 'Unidade obrigatória.');
            back();
        }

        // Próxima posição
        $stmt = $db->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM service_queue WHERE tenant_id = ? AND unit_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$tenantId, $unitId]);
        $position = (int) $stmt->fetchColumn();

        $clientId = $this->input('client_id') ? (int) $this->input('client_id') : null;
        $clientName = $clientId ? null : ($this->input('client_name') ?: 'Walk-in');

        $stmt = $db->prepare(
            "INSERT INTO service_queue
             (tenant_id, unit_id, client_id, client_name, professional_id, service_id, position, priority, status, notes, checked_in_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'waiting', ?, NOW(), NOW(), NOW())"
        );
        $stmt->execute([
            $tenantId,
            $unitId,
            $clientId,
            $clientName,
            $this->input('professional_id') ? (int) $this->input('professional_id') : null,
            $this->input('service_id') ? (int) $this->input('service_id') : null,
            $position,
            (int) $this->input('priority', 0),
            $this->input('notes') ?: null,
        ]);

        flash('success', 'Cliente adicionado à fila.');
        redirect(url('queue?unit_id=' . $unitId));
    }

    public function updateStatus(string $id): void
    {
        $this->authorize('appointments.edit');

        $db = Database::getInstance();
        $tenantId = $this->tenantId();
        $status   = $this->input('status');

        $allowed = ['waiting', 'called', 'in_progress', 'completed', 'cancelled', 'no_show'];
        if (!in_array($status, $allowed, true)) {
            flash('error', 'Status inválido.');
            back();
        }

        $timestamps = [];
        $now = date('Y-m-d H:i:s');
        if ($status === 'called')      $timestamps['called_at']    = $now;
        if ($status === 'in_progress') $timestamps['started_at']   = $now;
        if ($status === 'completed')   $timestamps['completed_at'] = $now;

        $setClauses = "status = ?";
        $params     = [$status];
        foreach ($timestamps as $col => $val) {
            $setClauses .= ", {$col} = ?";
            $params[] = $val;
        }
        $params[] = (int) $id;
        $params[] = $tenantId;

        $db->prepare("UPDATE service_queue SET {$setClauses}, updated_at = NOW() WHERE id = ? AND tenant_id = ?")
           ->execute($params);

        if ($this->isJson()) {
            $this->json(['success' => true]);
            return;
        }

        back();
    }

    public function remove(string $id): void
    {
        $this->authorize('appointments.cancel');

        $db = Database::getInstance();
        $db->prepare("UPDATE service_queue SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND tenant_id = ?")
           ->execute([(int) $id, $this->tenantId()]);

        flash('success', 'Removido da fila.');
        back();
    }

    private function getDayStats(int $tenantId, int $unitId): array
    {
        if (!$unitId) return ['total' => 0, 'waiting' => 0, 'in_progress' => 0, 'completed' => 0];

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT status, COUNT(*) as cnt FROM service_queue
             WHERE tenant_id = ? AND unit_id = ? AND DATE(created_at) = CURDATE()
             GROUP BY status"
        );
        $stmt->execute([$tenantId, $unitId]);
        $rows = $stmt->fetchAll();

        $stats = ['total' => 0, 'waiting' => 0, 'called' => 0, 'in_progress' => 0, 'completed' => 0];
        foreach ($rows as $row) {
            $stats[$row['status']] = (int) $row['cnt'];
            $stats['total'] += (int) $row['cnt'];
        }
        return $stats;
    }

    private function isJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
}
