<?php

namespace App\Observers;

use App\Http\Controllers\Api\V2\Public\ConfigController;
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
        Cache::forget(ConfigController::cacheKey($tenantId));
        Cache::forget("v2:manifest:{$tenantId}");
    }
}
