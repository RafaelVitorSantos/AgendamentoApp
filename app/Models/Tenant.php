<?php

namespace App\Models;

use App\Core\Model;

class Tenant extends Model
{
    protected string $table = 'tenants';
    protected bool $tenantScoped = false; // Tabela global
    protected bool $softDelete = true;

    public function findBySlug(string $slug): ?array
    {
        $result = $this->where('slug', $slug);
        return $result[0] ?? null;
    }

    public function findByEmail(string $email): ?array
    {
        $result = $this->where('email', $email);
        return $result[0] ?? null;
    }
}
