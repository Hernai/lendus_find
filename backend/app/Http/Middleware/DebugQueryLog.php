<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Instrumentacion en vivo de queries SQL por request.
 *
 * Se activa de dos formas:
 * 1. Variable env DEBUG_QUERIES=1 — todos los requests loguean queries.
 *    Util en staging para diagnosticar latencia general.
 * 2. Header X-Debug-Queries: 1 — solo el request marcado loguea queries.
 *    Util en prod para diagnosticar UN endpoint sin saturar logs.
 *
 * Por seguridad, el header solo se honra si el IP esta en allowlist
 * (config('app.debug_queries_allowed_ips')). En prod sin esa whitelist
 * el header se ignora.
 *
 * Output: storage/logs/queries.log con una linea por request:
 *   [timestamp] METHOD /path TOTAL_TIMEms (N queries, M slow >100ms)
 *     duration_ms | SQL truncado | bindings
 *
 * NUNCA dejar DEBUG_QUERIES=1 en prod permanente — el overhead de
 * registrar listeners de DB es ~5-10% por request.
 */
class DebugQueryLog
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldLog($request)) {
            return $next($request);
        }

        $queries = [];
        $startedAt = microtime(true);

        DB::listen(function ($query) use (&$queries) {
            $queries[] = [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'time' => $query->time, // ms
                'connection' => $query->connectionName,
            ];
        });

        $response = $next($request);

        $totalMs = (int) round((microtime(true) - $startedAt) * 1000);
        $queryCount = count($queries);
        $queryTotalMs = (int) array_sum(array_column($queries, 'time'));
        $slowCount = count(array_filter($queries, fn ($q) => $q->time ?? $q['time'] > 100));

        $lines = [];
        $lines[] = sprintf(
            '%s %s -> %d  total=%dms  queries=%d (sum=%dms, slow=%d)',
            $request->method(),
            $request->path(),
            $response->getStatusCode(),
            $totalMs,
            $queryCount,
            $queryTotalMs,
            $slowCount
        );

        foreach ($queries as $i => $q) {
            $sql = preg_replace('/\s+/', ' ', $q['sql']);
            if (strlen($sql) > 200) {
                $sql = substr($sql, 0, 200) . '...';
            }
            $marker = $q['time'] > 100 ? ' ⚠' : '';
            $lines[] = sprintf(
                '  [%d] %6.1fms%s  %s',
                $i + 1,
                $q['time'],
                $marker,
                $sql
            );
        }

        Log::channel($this->logChannel())->info(implode(PHP_EOL, $lines));

        // Si el cliente pidio diagnostico via header, devolvemos el resumen
        // en X-Debug-Queries-Summary para inspeccion rapida sin tail logs.
        if ($request->headers->has('X-Debug-Queries')) {
            $response->headers->set(
                'X-Debug-Queries-Summary',
                "queries={$queryCount} sum={$queryTotalMs}ms total={$totalMs}ms slow={$slowCount}"
            );
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        // Vía config (no env directo) para que funcione con config:cache en prod.
        if (config('app.debug_queries', false)) {
            return true;
        }

        if (! $request->headers->has('X-Debug-Queries')) {
            return false;
        }

        // Header requested, check IP allowlist
        $allowed = config('app.debug_queries_allowed_ips', []);
        if (empty($allowed)) {
            return false;
        }

        return in_array($request->ip(), $allowed, true);
    }

    private function logChannel(): string
    {
        // Si existe un channel 'queries' en config/logging.php, usarlo.
        // Si no, cae a 'single' (storage/logs/laravel.log).
        return config('logging.channels.queries') ? 'queries' : 'single';
    }
}
