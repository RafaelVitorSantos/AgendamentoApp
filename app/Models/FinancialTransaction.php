<?php

namespace App\Models;

use App\Core\Model;

class FinancialTransaction extends Model
{
    protected string $table = 'financial_transactions';
    protected bool $tenantScoped = true;

    public function getFiltered(string $type = '', string $month = '', int $limit = 50, int $offset = 0): array
    {
        $tenantId = $this->getTenantId();
        $sql = "SELECT ft.*, fc.name as category_name, fc.type as category_type, fc.color as category_color
                FROM financial_transactions ft
                LEFT JOIN financial_categories fc ON fc.id = ft.category_id
                WHERE ft.tenant_id = ?";
        $params = [$tenantId];

        if ($type) {
            $sql .= " AND ft.type = ?";
            $params[] = $type;
        }

        if ($month) {
            $sql .= " AND DATE_FORMAT(ft.reference_date, '%Y-%m') = ?";
            $params[] = $month;
        }

        $sql .= " ORDER BY ft.reference_date DESC, ft.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getSummary(string $month = ''): array
    {
        $tenantId = $this->getTenantId();
        $where = "WHERE tenant_id = ? AND status != 'cancelled'";
        $params = [$tenantId];

        if ($month) {
            $where .= " AND DATE_FORMAT(reference_date, '%Y-%m') = ?";
            $params[] = $month;
        }

        $stmt = $this->db->prepare(
            "SELECT
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
                COUNT(CASE WHEN type = 'income' THEN 1 END) as count_income,
                COUNT(CASE WHEN type = 'expense' THEN 1 END) as count_expense
             FROM financial_transactions {$where}"
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        return [
            'income'        => (float)($row['total_income'] ?? 0),
            'expense'       => (float)($row['total_expense'] ?? 0),
            'balance'       => (float)($row['total_income'] ?? 0) - (float)($row['total_expense'] ?? 0),
            'count_income'  => (int)($row['count_income'] ?? 0),
            'count_expense' => (int)($row['count_expense'] ?? 0),
        ];
    }

    public function getMonthlyChart(int $months = 6): array
    {
        $tenantId = $this->getTenantId();
        $stmt = $this->db->prepare(
            "SELECT
                DATE_FORMAT(reference_date, '%Y-%m') as month,
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
             FROM financial_transactions
             WHERE tenant_id = ? AND status != 'cancelled'
               AND reference_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(reference_date, '%Y-%m')
             ORDER BY month ASC"
        );
        $stmt->execute([$tenantId, $months]);
        return $stmt->fetchAll();
    }

    public function getCategories(string $type = ''): array
    {
        $tenantId = $this->getTenantId();
        $sql = "SELECT * FROM financial_categories WHERE tenant_id = ? AND is_active = 1";
        $params = [$tenantId];
        if ($type) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        $sql .= " ORDER BY type ASC, name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countFiltered(string $type = '', string $month = ''): int
    {
        $tenantId = $this->getTenantId();
        $sql = "SELECT COUNT(*) FROM financial_transactions WHERE tenant_id = ?";
        $params = [$tenantId];
        if ($type) { $sql .= " AND type = ?"; $params[] = $type; }
        if ($month) { $sql .= " AND DATE_FORMAT(reference_date, '%Y-%m') = ?"; $params[] = $month; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
