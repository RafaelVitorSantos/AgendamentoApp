<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AppointmentService;

class PublicBookingController extends Controller
{
    private ?array $tenant = null;

    public function show(string $slug): void
    {
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) {
            http_response_code(404);
            $this->view('errors.404');
            return;
        }

        $db = Database::getInstance();
        $tenantId = (int) $tenant['id'];

        // Serviços agendáveis online
        $stmt = $db->prepare(
            "SELECT s.id, s.name, s.description, s.duration_minutes, s.price, c.name AS category_name
             FROM services s
             LEFT JOIN service_categories c ON c.id = s.category_id
             WHERE s.tenant_id = ? AND s.is_active = 1 AND s.allow_online_booking = 1
             ORDER BY c.name, s.name"
        );
        $stmt->execute([$tenantId]);
        $services = $stmt->fetchAll();

        // Unidades ativas
        $stmt = $db->prepare(
            "SELECT id, name, address_street, address_city, address_state, phone
             FROM units WHERE tenant_id = ? AND is_active = 1 ORDER BY name"
        );
        $stmt->execute([$tenantId]);
        $units = $stmt->fetchAll();

        $this->view('public.booking', [
            'tenant'   => $tenant,
            'services' => $services,
            'units'    => $units,
            'pageTitle' => ($tenant['trade_name'] ?: $tenant['company_name']) . ' — Agendar',
        ]);
    }

    /**
     * API: profissionais para um serviço
     */
    public function apiProfessionals(string $slug): void
    {
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) { $this->json(['error' => 'Não encontrado'], 404); return; }

        $serviceId = (int) $this->input('service_id');
        $db        = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT DISTINCT p.id, p.name, p.bio, p.color
             FROM professionals p
             INNER JOIN professional_services ps ON ps.professional_id = p.id
             WHERE p.tenant_id = ? AND p.is_active = 1
               AND (ps.service_id = ? OR ? = 0)
             ORDER BY p.name"
        );
        $stmt->execute([(int) $tenant['id'], $serviceId, $serviceId]);
        $this->json($stmt->fetchAll());
    }

    /**
     * API: slots disponíveis
     */
    public function apiSlots(string $slug): void
    {
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) { $this->json(['error' => 'Não encontrado'], 404); return; }

        $professionalId = (int) $this->input('professional_id');
        $serviceId      = (int) $this->input('service_id');
        $date           = $this->input('date');
        $unitId         = (int) $this->input('unit_id', 0);

        if (!$professionalId || !$serviceId || !$date) {
            $this->json(['error' => 'Parâmetros obrigatórios.'], 400);
            return;
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT duration_minutes FROM services WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$serviceId, (int) $tenant['id']]);
        $service = $stmt->fetch();

        if (!$service) {
            $this->json(['error' => 'Serviço não encontrado.'], 404);
            return;
        }

        $_SESSION['tenant_id'] = (int) $tenant['id'];
        $svc   = new AppointmentService();
        $raw   = $svc->getAvailableSlots($professionalId, $unitId, $date, (int) $service['duration_minutes']);

        // Retorna apenas os horários de início como strings para o front
        $slots = array_map(fn($s) => is_array($s) ? $s['start'] : $s, $raw);

        $this->json(['slots' => $slots]);
    }

    /**
     * Salvar agendamento público
     */
    public function store(string $slug): void
    {
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) { $this->json(['error' => 'Não encontrado'], 404); return; }

        $tenantId = (int) $tenant['id'];

        $name  = trim($this->input('client_name', ''));
        $phone = trim($this->input('client_phone', ''));
        $email = trim($this->input('client_email', ''));

        if (!$name || !$phone) {
            flash('error', 'Nome e telefone são obrigatórios.');
            back();
        }

        $professionalId = (int) $this->input('professional_id');
        $serviceId      = (int) $this->input('service_id');
        $date           = $this->input('date');
        $startTime      = $this->input('start_time');
        $unitId         = (int) $this->input('unit_id', 0);

        if (!$professionalId || !$serviceId || !$date || !$startTime) {
            flash('error', 'Preencha todos os campos obrigatórios.');
            back();
        }

        $db = Database::getInstance();

        // Busca ou cria cliente
        $stmt = $db->prepare("SELECT id FROM clients WHERE tenant_id = ? AND phone = ? LIMIT 1");
        $stmt->execute([$tenantId, $phone]);
        $client = $stmt->fetch();

        if (!$client) {
            $db->prepare(
                "INSERT INTO clients (tenant_id, name, phone, email, source, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 'online', NOW(), NOW())"
            )->execute([$tenantId, $name, $phone, $email ?: null]);
            $clientId = (int) $db->lastInsertId();
        } else {
            $clientId = (int) $client['id'];
        }

        // Busca serviço
        $stmt = $db->prepare("SELECT duration_minutes, price FROM services WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$serviceId, $tenantId]);
        $service = $stmt->fetch();

        if (!$service) {
            flash('error', 'Serviço não disponível.');
            back();
        }

        $_SESSION['tenant_id'] = $tenantId;

        $svc    = new AppointmentService();
        $result = $svc->create([
            'unit_id'          => $unitId ?: 1,
            'client_id'        => $clientId,
            'professional_id'  => $professionalId,
            'service_id'       => $serviceId,
            'date'             => $date,
            'start_time'       => $startTime . ':00',
            'duration_minutes' => (int) $service['duration_minutes'],
            'price'            => (float) $service['price'],
            'source'           => 'online',
            'notes'            => $this->input('notes'),
            'created_by'       => null,
        ]);

        if (!$result['success']) {
            flash('error', $result['error'] ?? 'Não foi possível criar o agendamento.');
            back();
        }

        // Redireciona para página de confirmação
        redirect(url('book/' . $slug . '/confirm/' . $result['id']));
    }

    /**
     * Página de confirmação
     */
    public function confirm(string $slug, string $appointmentId): void
    {
        $tenant = $this->resolveTenant($slug);
        if (!$tenant) { http_response_code(404); $this->view('errors.404'); return; }

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT a.*, c.name AS client_name, p.name AS professional_name,
                    s.name AS service_name, s.duration_minutes
             FROM appointments a
             LEFT JOIN clients c ON c.id = a.client_id
             JOIN professionals p ON p.id = a.professional_id
             JOIN services s ON s.id = a.service_id
             WHERE a.id = ? AND a.tenant_id = ?"
        );
        $stmt->execute([(int) $appointmentId, (int) $tenant['id']]);
        $appointment = $stmt->fetch();

        $this->view('public.confirm', [
            'tenant'      => $tenant,
            'appointment' => $appointment,
            'pageTitle'   => 'Agendamento Confirmado',
        ]);
    }

    private function resolveTenant(string $slug): ?array
    {
        if ($this->tenant) return $this->tenant;

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT * FROM tenants WHERE slug = ? AND status IN ('trial','active') AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$slug]);
        $this->tenant = $stmt->fetch() ?: null;
        return $this->tenant;
    }
}
