<?php

namespace App\Services\Calendar;

use App\Core\Database;

/**
 * Gerador de feeds iCalendar (RFC 5545).
 *
 * Implementa:
 *  - VCALENDAR / VEVENT / VALARM
 *  - Conversão local → UTC para DTSTART/DTEND
 *  - Line folding (RFC 5545 §3.1 — max 75 octetos por linha)
 *  - Escaping de valores TEXT (vírgula, ponto-e-vírgula, barra)
 *  - STATUS mapeado do status interno do agendamento
 *  - UID globalmente único: {id}-{tenantUuid}@agendapro
 */
class ICalService
{
    private const PRODID  = '-//AgendaPRO//Agendamento SaaS//PT-BR';
    private const VERSION = '2.0';

    /**
     * Gera um VCALENDAR com todos os agendamentos futuros e passados (180d)
     * associados ao token informado.
     */
    public function generateFeed(array $tokenRow): string
    {
        $db   = Database::getInstance();
        $rows = $this->fetchAppointments($db, $tokenRow);
        $tz   = $this->resolveTenantTimezone($db, (int) $tokenRow['tenant_id']);

        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:' . self::VERSION;
        $lines[] = 'PRODID:' . self::PRODID;
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $lines[] = 'X-WR-CALNAME:AgendaPRO — ' . ($tokenRow['professional_name'] ?? $tokenRow['company_name'] ?? 'Agenda');
        $lines[] = 'X-WR-TIMEZONE:' . $tz;
        $lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT1H';
        $lines[] = 'X-PUBLISHED-TTL:PT1H';

        foreach ($rows as $row) {
            $lines = array_merge($lines, $this->buildVEvent($row, $tz, $tokenRow['tenant_uuid']));
        }

        $lines[] = 'END:VCALENDAR';

        // Junta com CRLF e aplica line folding
        return implode("\r\n", array_map([$this, 'fold'], $lines)) . "\r\n";
    }

    /**
     * Gera um único VEVENT para exportação/convite.
     */
    public function generateSingleEvent(array $appointment, string $tenantUuid, string $tz): string
    {
        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:' . self::VERSION;
        $lines[] = 'PRODID:' . self::PRODID;
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:REQUEST';
        $lines = array_merge($lines, $this->buildVEvent($appointment, $tz, $tenantUuid));
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", array_map([$this, 'fold'], $lines)) . "\r\n";
    }

    // ── Consulta de dados ────────────────────────────────────────────────────

    private function fetchAppointments(\PDO $db, array $tokenRow): array
    {
        $tenantId = (int) $tokenRow['tenant_id'];
        $profId   = $tokenRow['professional_id'] ? (int) $tokenRow['professional_id'] : null;
        $since    = date('Y-m-d', strtotime('-180 days'));
        $until    = date('Y-m-d', strtotime('+365 days'));

        $where  = "a.tenant_id = ? AND a.date BETWEEN ? AND ?";
        $params = [$tenantId, $since, $until];

        if ($profId) {
            $where   .= " AND a.professional_id = ?";
            $params[] = $profId;
        }

        $stmt = $db->prepare(
            "SELECT
                a.id, a.date, a.start_time, a.end_time, a.status,
                a.notes, a.updated_at, a.created_at,
                c.name AS client_name, c.email AS client_email,
                p.name AS professional_name, p.email AS professional_email,
                s.name AS service_name,
                u.name AS unit_name,
                CONCAT_WS(', ', NULLIF(u.address_street,''), NULLIF(u.address_city,''), NULLIF(u.address_state,'')) AS unit_address,
                t.uuid AS tenant_uuid, t.trade_name AS company_name, t.timezone
             FROM appointments a
             LEFT JOIN clients       c ON c.id = a.client_id
             LEFT JOIN professionals p ON p.id = a.professional_id
             LEFT JOIN services      s ON s.id = a.service_id
             LEFT JOIN units         u ON u.id = a.unit_id
             LEFT JOIN tenants       t ON t.id = a.tenant_id
             WHERE {$where}
             AND a.status NOT IN ('rescheduled')
             ORDER BY a.date ASC, a.start_time ASC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function resolveTenantTimezone(\PDO $db, int $tenantId): string
    {
        $stmt = $db->prepare("SELECT timezone FROM tenants WHERE id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        return $stmt->fetchColumn() ?: 'America/Sao_Paulo';
    }

    // ── Construção do VEVENT ─────────────────────────────────────────────────

    private function buildVEvent(array $a, string $tz, string $tenantUuid): array
    {
        $dtStart = $this->toUtc($a['date'] . ' ' . $a['start_time'], $tz);
        $dtEnd   = $this->toUtc($a['date'] . ' ' . $a['end_time'],   $tz);
        $dtStamp = $this->toUtc($a['updated_at'] ?? $a['created_at'] ?? date('Y-m-d H:i:s'), $tz);
        $created = $this->toUtc($a['created_at'] ?? date('Y-m-d H:i:s'), $tz);
        $uid     = sprintf('%d-%s@agendapro', $a['id'], substr($tenantUuid, 0, 8));

        $status  = $this->mapStatus($a['status']);
        $summary = $this->esc($a['service_name'] ?? 'Atendimento');

        if (!empty($a['professional_name'])) {
            $summary .= ' c/ ' . $this->esc($a['professional_name']);
        }

        $desc = implode('\n', array_filter([
            $a['service_name'] ?? null,
            $a['professional_name'] ? 'Profissional: ' . $a['professional_name'] : null,
            $a['unit_name'] ? 'Local: ' . $a['unit_name'] : null,
            !empty($a['notes']) ? 'Obs: ' . $a['notes'] : null,
        ]));

        $location = implode(', ', array_filter([
            $a['unit_name'] ?? null,
            $a['unit_address'] ?? null,
        ]));

        $lines = [];
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:'          . $uid;
        $lines[] = 'DTSTAMP:'      . $dtStamp;
        $lines[] = 'DTSTART:'      . $dtStart;
        $lines[] = 'DTEND:'        . $dtEnd;
        $lines[] = 'CREATED:'      . $created;
        $lines[] = 'LAST-MODIFIED:' . $dtStamp;
        $lines[] = 'SUMMARY:'      . $summary;
        $lines[] = 'STATUS:'       . $status;
        $lines[] = 'SEQUENCE:0';
        $lines[] = 'TRANSP:OPAQUE';

        if ($desc) {
            $lines[] = 'DESCRIPTION:' . $this->esc($desc);
        }
        if ($location) {
            $lines[] = 'LOCATION:' . $this->esc($location);
        }
        if (!empty($a['professional_email'])) {
            $lines[] = 'ORGANIZER;CN=' . $this->esc($a['professional_name'] ?? 'Profissional')
                     . ':mailto:' . $a['professional_email'];
        }
        if (!empty($a['client_email'])) {
            $lines[] = 'ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED'
                     . ';CN=' . $this->esc($a['client_name'] ?? 'Cliente')
                     . ':mailto:' . $a['client_email'];
        }

        // VALARM: notificação 60 min antes
        if (!in_array($a['status'], ['cancelled_by_client', 'cancelled_by_business', 'no_show'], true)) {
            $lines[] = 'BEGIN:VALARM';
            $lines[] = 'TRIGGER:-PT60M';
            $lines[] = 'ACTION:DISPLAY';
            $lines[] = 'DESCRIPTION:Lembrete: ' . ($a['service_name'] ?? 'Atendimento') . ' em 1 hora';
            $lines[] = 'END:VALARM';
        }

        $lines[] = 'END:VEVENT';
        return $lines;
    }

    // ── Utilitários ──────────────────────────────────────────────────────────

    /**
     * Converte datetime local (tenant timezone) para UTC no formato iCal: 20260428T150000Z
     */
    private function toUtc(string $localDatetime, string $tz): string
    {
        try {
            $dt = new \DateTimeImmutable($localDatetime, new \DateTimeZone($tz));
            return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        } catch (\Exception) {
            return gmdate('Ymd\THis\Z');
        }
    }

    /**
     * Mapeia status interno → STATUS do iCal (RFC 5545 §3.8.1.11)
     */
    private function mapStatus(string $status): string
    {
        return match ($status) {
            'confirmed', 'in_progress', 'completed' => 'CONFIRMED',
            'cancelled_by_client', 'cancelled_by_business' => 'CANCELLED',
            'no_show' => 'CANCELLED',
            default   => 'TENTATIVE',
        };
    }

    /**
     * Escapa caracteres especiais em valores TEXT do iCal (RFC 5545 §3.3.11)
     */
    private function esc(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace(';',  '\;',  $value);
        $value = str_replace(',',  '\,',  $value);
        // \n literal → \\n iCal (quebra de linha dentro de texto)
        $value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);
        return $value;
    }

    /**
     * Line folding RFC 5545 §3.1: quebra linhas > 75 octetos.
     * Linha de continuação começa com um espaço.
     */
    private function fold(string $line): string
    {
        // Converte para bytes para contar octetos (não caracteres)
        if (strlen($line) <= 75) {
            return $line;
        }

        $result = '';
        $bytes  = str_split($line); // split by byte
        $count  = 0;

        foreach ($bytes as $byte) {
            if ($count >= 75) {
                $result .= "\r\n ";
                $count   = 1; // o espaço de continuação conta
            }
            $result .= $byte;
            $count++;
        }

        return $result;
    }
}
