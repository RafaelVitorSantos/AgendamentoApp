<?php

namespace App\Core;

/**
 * Contexto do tenant ativo.
 * Armazena e fornece o tenant_id para toda a aplicação.
 * Thread-safe via variável estática de classe.
 */
class TenantContext
{
    private static ?int $tenantId = null;
    private static ?array $tenantData = null;

    public static function set(int $tenantId): void
    {
        self::$tenantId = $tenantId;
        self::$tenantData = null; // Reseta cache
    }

    public static function get(): ?int
    {
        return self::$tenantId ?? ($_SESSION['tenant_id'] ?? null);
    }

    public static function require(): int
    {
        $id = self::get();
        if ($id === null) {
            throw new \RuntimeException('Tenant não identificado no contexto.');
        }
        return $id;
    }

    /**
     * Carrega e cacheia dados do tenant.
     */
    public static function getData(): ?array
    {
        if (self::$tenantData === null && self::get() !== null) {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ? AND deleted_at IS NULL LIMIT 1");
            $stmt->execute([self::get()]);
            self::$tenantData = $stmt->fetch() ?: null;
        }
        return self::$tenantData;
    }

    /**
     * Retorna configuração específica do tenant.
     */
    public static function setting(string $key, mixed $default = null): mixed
    {
        $data = self::getData();
        if (!$data) return $default;

        $settings = json_decode($data['settings'] ?? '{}', true) ?? [];
        return $settings[$key] ?? $default;
    }

    public static function clear(): void
    {
        self::$tenantId = null;
        self::$tenantData = null;
    }
}
