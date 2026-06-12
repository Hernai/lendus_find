<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\StaffAccount;
use App\Models\Tenant;
use App\Models\TenantRoleModuleOverride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Configuración de módulos del backoffice por tenant.
 *
 * Solo el SUPER_ADMIN global (tenant_id = NULL, role = SUPER_ADMIN) puede
 * acceder. La validación se hace al inicio de cada método porque el
 * middleware `permission:canConfigureTenant` no distingue entre admin
 * per-tenant y super admin global.
 *
 * El catálogo de módulos vive en el frontend
 * (`frontend/src/constants/admin-modules.ts`); este endpoint solo
 * persiste las EXCEPCIONES a los defaults. El frontend envía la matriz
 * completa, el backend hace upsert y delete de las filas no recibidas.
 */
class TenantModuleController extends Controller
{
    use ApiResponses;

    private const ALLOWED_ROLES = [
        StaffAccount::ROLE_ANALYST,
        StaffAccount::ROLE_SUPERVISOR,
        StaffAccount::ROLE_ADMIN,
    ];

    /**
     * GET /v2/staff/tenants/{id}/modules
     * Devuelve los overrides actuales del tenant.
     */
    public function index(Request $request, string $id): JsonResponse
    {
        if ($error = $this->guardSuperAdmin($request)) {
            return $error;
        }

        $tenant = Tenant::findOrFail($id);

        $overrides = TenantRoleModuleOverride::query()
            ->where('tenant_id', $tenant->id)
            ->get(['role', 'module_key', 'enabled'])
            ->map(fn ($o) => [
                'role' => $o->role,
                'module_key' => $o->module_key,
                'enabled' => (bool) $o->enabled,
            ])
            ->values()
            ->all();

        return $this->success([
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
            ],
            'overrides' => $overrides,
        ]);
    }

    /**
     * PUT /v2/staff/tenants/{id}/modules
     * Reemplaza el conjunto de overrides del tenant con el body recibido.
     *
     * Body esperado:
     *   { "overrides": [{role, module_key, enabled}, ...] }
     *
     * Filas no incluidas en `overrides` se ELIMINAN — la matriz UI envía
     * solo las celdas que difieren del default, y borrar el override
     * equivale a "restaurar default".
     */
    public function update(Request $request, string $id): JsonResponse
    {
        if ($error = $this->guardSuperAdmin($request)) {
            return $error;
        }

        $tenant = Tenant::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'overrides' => 'present|array',
            'overrides.*.role' => ['required', 'string', 'in:' . implode(',', self::ALLOWED_ROLES)],
            'overrides.*.module_key' => 'required|string|max:64',
            'overrides.*.enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError('Datos inválidos', $validator->errors()->toArray());
        }

        $payload = $request->input('overrides', []);

        // Borrar combinaciones que no llegan + upsert de las que sí.
        $keysToKeep = collect($payload)
            ->map(fn ($o) => $o['role'] . '|' . $o['module_key'])
            ->all();

        TenantRoleModuleOverride::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->each(function (TenantRoleModuleOverride $row) use ($keysToKeep) {
                if (!in_array($row->role . '|' . $row->module_key, $keysToKeep, true)) {
                    $row->delete();
                }
            });

        foreach ($payload as $o) {
            TenantRoleModuleOverride::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'role' => $o['role'],
                    'module_key' => $o['module_key'],
                ],
                [
                    'enabled' => (bool) $o['enabled'],
                ],
            );
        }

        return $this->success([
            'tenant_id' => $tenant->id,
            'overrides_count' => count($payload),
        ], 'Overrides actualizados');
    }

    /**
     * Niega acceso si el usuario autenticado NO es super admin global.
     * Devuelve respuesta 403 si está prohibido, null si está OK.
     */
    private function guardSuperAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();
        $isSuperAdmin = $user instanceof StaffAccount
            && $user->tenant_id === null
            && $user->isSuperAdmin();

        if (!$isSuperAdmin) {
            return $this->forbidden('Solo el super administrador puede configurar módulos.');
        }
        return null;
    }
}
