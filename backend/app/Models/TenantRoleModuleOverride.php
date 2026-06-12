<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override granular de un módulo del backoffice para un (tenant, rol).
 *
 * Solo se crea fila cuando hay una EXCEPCIÓN al default declarado en el
 * catálogo del frontend (`frontend/src/constants/admin-modules.ts`).
 * Si la fila no existe, gana el default.
 *
 * No usa HasTenant porque el SUPER_ADMIN global necesita listar y editar
 * overrides de cualquier tenant — el filtrado se hace explícito en el
 * endpoint correspondiente.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $role         ANALYST | SUPERVISOR | ADMIN
 * @property string $module_key   Ej. 'reports', 'products', 'users'
 * @property bool   $enabled
 */
class TenantRoleModuleOverride extends Model
{
    use HasUuid;

    protected $table = 'tenant_role_module_overrides';

    protected $fillable = [
        'tenant_id',
        'role',
        'module_key',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Devuelve un mapa `{ "ROLE.module_key": bool }` con TODOS los overrides
     * de un tenant. Útil para resolver permisos efectivos al inicio de la
     * sesión sin disparar N queries.
     */
    public static function mapForTenant(string $tenantId): array
    {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->get(['role', 'module_key', 'enabled'])
            ->mapWithKeys(fn ($o) => ["{$o->role}.{$o->module_key}" => (bool) $o->enabled])
            ->all();
    }
}
