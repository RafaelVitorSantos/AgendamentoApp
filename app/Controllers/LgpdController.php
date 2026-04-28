<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\AuditService;

/**
 * Endpoints de conformidade LGPD.
 *
 * Direitos implementados:
 *  - Art. 18 III — Acesso aos dados (export)
 *  - Art. 18 IV  — Anonimização (right to be forgotten)
 */
class LgpdController extends Controller
{
    /**
     * Exporta todos os dados pessoais de um cliente em JSON (portabilidade).
     * GET /clients/{id}/export
     */
    public function exportClient(string $id): void
    {
        $this->authorize('clients.view');

        $clientId = (int) $id;
        $tenantId = TenantContext::require();
        $db       = Database::getInstance();

        $client = $this->fetchClient($db, $clientId, $tenantId);
        if (!$client) {
            http_response_code(404);
            $this->json(['error' => 'Cliente não encontrado.'], 404);
            return;
        }

        $export = [
            'exported_at'  => date('c'),
            'tenant_id'    => $tenantId,
            'data_subject' => 'client',
            'personal_data' => [
                'id'              => $client['id'],
                'name'            => $client['name'],
                'email'           => $client['email'],
                'phone'           => $client['phone'],
                'birthdate'       => $client['birthdate'] ?? null,
                'address'         => $this->buildAddress($client),
                'lgpd_consent'    => (bool) ($client['lgpd_consent'] ?? false),
                'lgpd_consent_at' => $client['lgpd_consent_at'] ?? null,
                'created_at'      => $client['created_at'],
            ],
            'appointments' => $this->fetchClientAppointments($db, $clientId, $tenantId),
            'financial'    => $this->fetchClientFinancial($db, $clientId, $tenantId),
            'reviews'      => $this->fetchClientReviews($db, $clientId, $tenantId),
        ];

        AuditService::log('lgpd_export', 'client', $clientId, null, null);

        $filename = 'dados-cliente-' . $clientId . '-' . date('Ymd') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Anonimiza os dados pessoais de um cliente (right to be forgotten).
     * POST /clients/{id}/anonymize
     *
     * Preserva registros históricos (appointments, financial) mas remove PII.
     */
    public function anonymizeClient(string $id): void
    {
        $this->authorize('clients.delete');

        $clientId = (int) $id;
        $tenantId = TenantContext::require();
        $db       = Database::getInstance();

        $client = $this->fetchClient($db, $clientId, $tenantId);
        if (!$client) {
            $this->json(['error' => 'Cliente não encontrado.'], 404);
            return;
        }

        if (!empty($client['deleted_at'])) {
            $this->json(['error' => 'Cliente já foi anonimizado.'], 409);
            return;
        }

        $db->beginTransaction();
        try {
            $anonymousName = 'Cliente Anonimizado #' . $clientId;

            $stmt = $db->prepare(
                "UPDATE clients SET
                    name            = ?,
                    email           = NULL,
                    phone           = ?,
                    birthdate       = NULL,
                    address_street  = NULL,
                    address_number  = NULL,
                    address_complement = NULL,
                    address_district   = NULL,
                    address_city    = NULL,
                    address_state   = NULL,
                    address_zip     = NULL,
                    notes           = NULL,
                    tags            = NULL,
                    lgpd_consent    = 0,
                    deleted_at      = NOW(),
                    updated_at      = NOW()
                 WHERE id = ? AND tenant_id = ?"
            );
            $stmt->execute([
                $anonymousName,
                'anonimizado-' . $clientId,
                $clientId,
                $tenantId,
            ]);

            AuditService::log('lgpd_anonymize', 'client', $clientId, $client, null);

            $db->commit();

            $this->json(['success' => true, 'message' => 'Dados do cliente anonimizados com sucesso.']);
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('LGPD anonymize error: ' . $e->getMessage());
            $this->json(['error' => 'Erro ao anonimizar dados. Tente novamente.'], 500);
        }
    }

    private function fetchClient(\PDO $db, int $clientId, int $tenantId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$clientId, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    private function buildAddress(array $client): ?string
    {
        $parts = array_filter([
            $client['address_street'] ?? null,
            $client['address_number'] ?? null,
            $client['address_complement'] ?? null,
            $client['address_district'] ?? null,
            $client['address_city'] ?? null,
            $client['address_state'] ?? null,
            $client['address_zip'] ?? null,
        ]);
        return $parts ? implode(', ', $parts) : null;
    }

    private function fetchClientAppointments(\PDO $db, int $clientId, int $tenantId): array
    {
        $stmt = $db->prepare(
            "SELECT a.date, a.start_time, a.end_time, a.status, a.price,
                    s.name AS service, p.name AS professional
             FROM appointments a
             JOIN services s ON s.id = a.service_id
             JOIN professionals p ON p.id = a.professional_id
             WHERE a.client_id = ? AND a.tenant_id = ?
             ORDER BY a.date DESC, a.start_time DESC"
        );
        $stmt->execute([$clientId, $tenantId]);
        return $stmt->fetchAll();
    }

    private function fetchClientFinancial(\PDO $db, int $clientId, int $tenantId): array
    {
        $stmt = $db->prepare(
            "SELECT ft.date, ft.amount, ft.payment_method, ft.status, fc.name AS category
             FROM financial_transactions ft
             LEFT JOIN financial_categories fc ON fc.id = ft.category_id
             WHERE ft.client_id = ? AND ft.tenant_id = ?
             ORDER BY ft.date DESC"
        );
        $stmt->execute([$clientId, $tenantId]);
        return $stmt->fetchAll();
    }

    private function fetchClientReviews(\PDO $db, int $clientId, int $tenantId): array
    {
        $stmt = $db->prepare(
            "SELECT rating, comment, created_at
             FROM reviews
             WHERE client_id = ? AND tenant_id = ?
             ORDER BY created_at DESC"
        );
        $stmt->execute([$clientId, $tenantId]);
        return $stmt->fetchAll();
    }
}
