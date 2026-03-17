<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Professional;
use App\Models\Unit;

class ScheduleBlockController extends Controller
{
    public function index(): void
    {
        $this->authorize('appointments.edit');

        $db       = Database::getInstance();
        $tenantId = $this->tenantId();

        $stmt = $db->prepare(
            "SELECT sb.*, p.name AS professional_name, u.name AS unit_name
             FROM schedule_blocks sb
             LEFT JOIN professionals p ON p.id = sb.professional_id
             LEFT JOIN units u ON u.id = sb.unit_id
             WHERE sb.tenant_id = ?
               AND sb.end_datetime >= NOW()
             ORDER BY sb.start_datetime ASC
             LIMIT 100"
        );
        $stmt->execute([$tenantId]);
        $blocks = $stmt->fetchAll();

        $professionalModel = new Professional();
        $unitModel         = new Unit();

        $this->render('schedule_blocks.index', [
            'pageTitle'     => 'Bloqueios de Horário',
            'blocks'        => $blocks,
            'professionals' => $professionalModel->all(['is_active' => 1], 'name ASC'),
            'units'         => $unitModel->all(['is_active' => 1], 'name ASC'),
        ]);
    }

    public function create(): void
    {
        $this->authorize('appointments.edit');

        $professionalModel = new Professional();
        $unitModel         = new Unit();

        $this->render('schedule_blocks.form', [
            'pageTitle'     => 'Novo Bloqueio',
            'block'         => null,
            'professionals' => $professionalModel->all(['is_active' => 1], 'name ASC'),
            'units'         => $unitModel->all(['is_active' => 1], 'name ASC'),
        ]);
    }

    public function store(): void
    {
        $this->authorize('appointments.edit');

        $errors = $this->validate([
            'title' => 'required',
        ]);

        if (!empty($errors)) {
            flash('errors', $errors);
            back();
        }

        $db       = Database::getInstance();
        $tenantId = $this->tenantId();
        $isAllDay = (bool) $this->input('is_all_day');

        if ($isAllDay) {
            $startDate = $this->input('start_date');
            $endDate   = $this->input('end_date');
            if (!$startDate || !$endDate) {
                flash('error', 'Informe a data inicial e a data final para bloqueio em dia(s) inteiro(s).');
                back();
            }
            $start = $startDate . ' 00:00:00';
            $end   = $endDate . ' 23:59:59';
        } else {
            $start = $this->input('start_datetime');
            $end   = $this->input('end_datetime');
            if (!$start || !$end) {
                flash('error', 'Informe o início e o fim do bloqueio.');
                back();
            }
            if (strlen($start) === 16) {
                $start .= ':00';
            }
            if (strlen($end) === 16) {
                $end .= ':00';
            }
        }

        if ($end <= $start) {
            flash('error', 'A data/hora de fim deve ser posterior ao início.');
            back();
        }

        $db->prepare(
            "INSERT INTO schedule_blocks
             (tenant_id, professional_id, unit_id, title, start_datetime, end_datetime, is_all_day, notes, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        )->execute([
            $tenantId,
            $this->input('professional_id') ?: null,
            $this->input('unit_id') ?: null,
            $this->input('title'),
            $start,
            $end,
            $isAllDay ? 1 : 0,
            $this->input('notes') ?: null,
            $this->userId(),
        ]);

        flash('success', 'Bloqueio criado com sucesso!');
        redirect(url('schedule-blocks'));
    }

    public function destroy(string $id): void
    {
        $this->authorize('appointments.edit');

        $db = Database::getInstance();
        $db->prepare("DELETE FROM schedule_blocks WHERE id = ? AND tenant_id = ?")
           ->execute([(int) $id, $this->tenantId()]);

        flash('success', 'Bloqueio removido.');
        back();
    }

    /**
     * API: bloqueios para o FullCalendar (JSON).
     */
    public function calendarEvents(): void
    {
        $this->authorize('appointments.view');

        $start    = $this->input('start', date('Y-m-d'));
        $end      = $this->input('end', date('Y-m-d', strtotime('+7 days')));
        $profId   = $this->input('professional_id');
        $db       = Database::getInstance();
        $tenantId = $this->tenantId();

        $sql    = "SELECT sb.*, p.name AS professional_name
                   FROM schedule_blocks sb
                   LEFT JOIN professionals p ON p.id = sb.professional_id
                   WHERE sb.tenant_id = ?
                     AND sb.start_datetime < ? AND sb.end_datetime > ?";
        $params = [$tenantId, $end . ' 23:59:59', $start . ' 00:00:00'];

        if ($profId) {
            $sql .= " AND (sb.professional_id = ? OR sb.professional_id IS NULL)";
            $params[] = (int) $profId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $events = array_map(fn($r) => [
            'id'              => 'block_' . $r['id'],
            'title'           => '🚫 ' . $r['title'] . ($r['professional_name'] ? ' (' . $r['professional_name'] . ')' : ''),
            'start'           => $r['start_datetime'],
            'end'             => $r['end_datetime'],
            'allDay'          => (bool) $r['is_all_day'],
            'backgroundColor' => '#374151',
            'borderColor'     => '#111827',
            'display'         => 'background',
            'extendedProps'   => ['type' => 'block', 'notes' => $r['notes']],
        ], $rows);

        $this->json($events);
    }
}
