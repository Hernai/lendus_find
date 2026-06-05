<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Pre-popula el cache de los endpoints publicos cacheables despues de un
 * deploy o un cache:clear.
 *
 * Razon: el primer request a /v2/config o /v2/public/manifest tras
 * `php artisan cache:clear` paga ~1s (queries + serializacion + ETag
 * md5). Si el deploy script corre este warmup, el primer usuario real
 * encuentra todo ya cacheado.
 *
 * Diseno: invocamos el controller via el kernel HTTP, no llamamos
 * `$controller->index()` directamente. Eso garantiza que pase por todo el
 * stack de middleware (tenant resolver, metadata, etag) — asi el cache se
 * puebla con exactamente el payload que veran los usuarios.
 *
 * Uso:
 *   php artisan cache:warmup                  # todos los tenants activos
 *   php artisan cache:warmup --tenant=demo    # un tenant especifico
 *   php artisan cache:warmup --quiet          # silencioso (para CI)
 */
class WarmupCache extends Command
{
    protected $signature = 'cache:warmup
                            {--tenant= : Slug o UUID de un tenant especifico}
                            {--force : Refresca aunque el cache este caliente}';

    protected $description = 'Pre-popula el cache de endpoints publicos por tenant.';

    public function handle(Kernel $kernel): int
    {
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->warn('No hay tenants para procesar.');
            return self::SUCCESS;
        }

        $this->info("Calentando cache para {$tenants->count()} tenant(s)...");

        $endpoints = [
            ['GET', '/api/v2/config', 'v2:config'],
            ['GET', '/api/v2/public/manifest', 'v2:manifest'],
        ];

        $ok = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            foreach ($endpoints as [$method, $path, $cacheKeyPrefix]) {
                $key = "{$cacheKeyPrefix}:{$tenant->id}";

                if ($this->option('force')) {
                    Cache::forget($key);
                }

                try {
                    $request = Request::create($path, $method);
                    $request->headers->set('X-Tenant-ID', $tenant->slug);
                    $request->headers->set('Accept', 'application/json');

                    $response = $kernel->handle($request);
                    $kernel->terminate($request, $response);

                    if ($response->getStatusCode() === 200) {
                        $ok++;
                        $this->line("  <fg=green>OK</> {$tenant->slug} {$path}");
                    } else {
                        $failed++;
                        $this->line("  <fg=red>{$response->getStatusCode()}</> {$tenant->slug} {$path}");
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->line("  <fg=red>ERR</> {$tenant->slug} {$path} ({$e->getMessage()})");
                }
            }
        }

        $this->newLine();
        $this->info("Listo: {$ok} ok, {$failed} fallidos");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveTenants()
    {
        $filter = $this->option('tenant');

        $query = Tenant::query()
            ->withoutGlobalScope('tenant')
            ->where('is_active', true);

        if ($filter) {
            // Si parece UUID, busca por id; sino por slug. Evita el cast
            // implicito de Postgres que truena con "invalid uuid syntax".
            $isUuid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $filter);
            if ($isUuid) {
                $query->where('id', $filter);
            } else {
                $query->where('slug', $filter);
            }
        }

        return $query->get();
    }
}
