<?php

namespace App\Observers;

use App\Http\Controllers\Api\V2\Public\ConfigController;
use App\Http\Controllers\Api\V2\Staff\ConfigController as StaffConfigController;
use App\Http\Controllers\Api\V2\Staff\IntegrationController;
use App\Models\NotificationTemplate;
use App\Models\TenantApiConfig;
use App\Models\TenantBranding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Invalida el cache del endpoint publico /v2/config cuando cambian los
 * modelos que aportan datos a su payload.
 *
 * Modelos observados (registrados en AppServiceProvider::boot):
 * - Product           — listado de productos por tenant
 * - TenantApiConfig   — feature flags `has_kyc_provider`, etc.
 * - TenantBranding    — colores, logos, fuentes
 *
 * Para el modelo Tenant la invalidacion vive en Tenant::booted() porque
 * tambien limpia `tenant:lookup:*` (acoplado al IdentifyTenant middleware).
 */
class V2ConfigCacheObserver
{
    public function saved(Model $model): void
    {
        $this->forget($model);
    }

    public function deleted(Model $model): void
    {
        $this->forget($model);
    }

    public function restored(Model $model): void
    {
        $this->forget($model);
    }

    private function forget(Model $model): void
    {
        $tenantId = $model->tenant_id ?? null;
        if (! $tenantId) {
            return;
        }

        // /v2/config y /v2/public/manifest dependen de Product/TenantApiConfig/
        // TenantBranding/Tenant.
        Cache::forget(ConfigController::cacheKey($tenantId));
        Cache::forget("v2:manifest:{$tenantId}");

        // /v2/staff/integrations cachea por tenant. Solo TenantApiConfig
        // afecta ese payload.
        if ($model instanceof TenantApiConfig) {
            Cache::forget(IntegrationController::listCacheKey($tenantId));
        }

        // /v2/staff/config muestra branding + api_configs + tenant info.
        // Cambios en TenantApiConfig o TenantBranding invalidan el cache.
        if ($model instanceof TenantApiConfig || $model instanceof TenantBranding) {
            Cache::forget(StaffConfigController::showCacheKey($tenantId));
        }

        // /v2/staff/notification-templates cachea por tenant+filtros. La
        // clave incluye un md5 de filtros, asi que no podemos enumerar
        // individualmente — usamos forget de todas las variantes via
        // bombardeo: NotificationTemplate guarda cualquier hash de filtros
        // posible. Si Redis fuera el cache store y soportara SCAN, podriamos
        // hacer SCAN MATCH "notification-templates:index:{tenantId}:*".
        // Por simplicidad y para Redis con KEYS prohibido en prod, usamos
        // una clave "version" — incrementarla invalida implicitamente todo.
        if ($model instanceof NotificationTemplate) {
            Cache::increment("notification-templates:version:{$tenantId}");
        }
    }
}
