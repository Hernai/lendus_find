<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Calienta el opcache de un worker PHP-FPM tocando todas las clases que
 * estan en el path critico del login y del flujo autenticado normal.
 *
 * Problema que resuelve:
 *
 *   El opcache es per-proceso y per-archivo. Un worker que solo ha servido
 *   `/health` tiene compilado lo del HealthController, pero NO tiene las
 *   clases de Sanctum, AuthController, Hash, AuditLog, MetadataService, etc.
 *   Cuando llega su primer hit a /login o a un endpoint autenticado, paga
 *   ~1.5-2s compilando cientos de archivos PHP.
 *
 *   Con pm.max_children=16, un cron que solo pegue a /health deja a los
 *   workers "warmes-para-health" pero "cold-para-todo-lo-demas".
 *
 * Solucion:
 *
 *   Este endpoint hace `class_exists()` sobre cada clase del path login +
 *   autenticado. `class_exists()` dispara el autoloader de Composer y eso
 *   compila los archivos a opcache. Despues de ejecutarse, ese worker tiene
 *   en su opcache TODO lo que necesita para servir login + endpoints staff
 *   en ~30ms en lugar de 2s.
 *
 *   El cron `/etc/cron.d/lendus-apifind-warmup` pega N veces secuenciales
 *   a este endpoint (N >= pm.max_children) para warmear todos los workers.
 *
 * Seguridad:
 *
 *   Restringido por IP allowlist al loopback (127.0.0.1) y LAN. El handler
 *   NO hace queries DB ni escribe nada — solo carga clases. No tiene
 *   efectos secundarios.
 */
class WarmupController extends Controller
{
    private array $allowed = [
        '127.0.0.1',
        '51.195.6.177', // IP publica del server (loopback efectivo cuando entra por vhost)
        '::1',
        '192.168.0.0/24',
    ];

    /**
     * Clases del path critico que queremos en opcache de cada worker.
     *
     * Incluye:
     *   - Auth controllers (staff + applicant)
     *   - Sanctum (token resolution, guard)
     *   - Models del flujo auth (StaffAccount, StaffProfile, ApplicantAccount, Tenant, etc.)
     *   - Audit log (model + job)
     *   - Metadata + middlewares del request
     *   - Common controllers de los endpoints mas hit
     *
     * Mantener sincronizada cuando se agreguen controllers nuevos al hot path.
     */
    private array $warmClasses = [
        // Auth core
        \App\Http\Controllers\Api\V2\Staff\AuthController::class,
        \App\Http\Controllers\Api\V2\Applicant\AuthController::class,
        \App\Models\StaffAccount::class,
        \App\Models\StaffProfile::class,
        \App\Models\ApplicantAccount::class,
        \App\Models\Tenant::class,
        \App\Models\TenantBranding::class,
        \App\Models\TenantApiConfig::class,
        \App\Models\CachedPersonalAccessToken::class,

        // Sanctum
        \Laravel\Sanctum\PersonalAccessToken::class,
        \Laravel\Sanctum\Sanctum::class,
        \Laravel\Sanctum\Guard::class,
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,

        // Audit + jobs
        \App\Models\AuditLog::class,
        \App\Jobs\PersistAuditLogJob::class,
        \App\Enums\AuditAction::class,

        // Middlewares del path
        \App\Http\Middleware\IdentifyTenant::class,
        \App\Http\Middleware\CaptureMetadata::class,
        \App\Http\Middleware\RequireStaff::class,
        \App\Http\Middleware\LogClientRequest::class,
        \App\Http\Middleware\DebugQueryLog::class,

        // Services del path
        \App\Services\MetadataService::class,

        // Controllers staff mas hit
        \App\Http\Controllers\Api\V2\Staff\ApplicationController::class,
        \App\Http\Controllers\Api\V2\Staff\UserController::class,
        \App\Http\Controllers\Api\V2\Staff\ProductController::class,
        \App\Http\Controllers\Api\V2\Staff\ConfigController::class,
        \App\Services\ApplicationService::class,

        // Traits compartidos
        \App\Http\Controllers\Api\V2\Traits\ApiResponses::class,
        \App\Traits\HasTenant::class,
        \App\Traits\HasUuid::class,
        \App\Traits\HasAuditFields::class,
    ];

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->ipAllowed($request->ip())) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $t0 = microtime(true);
        $loaded = 0;
        $failed = [];

        // 1) Declarar las clases. class_exists() dispara el autoloader Composer
        //    y compila a opcache.
        foreach ($this->warmClasses as $class) {
            try {
                if (class_exists($class) || interface_exists($class) || trait_exists($class)) {
                    $loaded++;
                }
            } catch (\Throwable $e) {
                $failed[] = ['class' => $class, 'error' => $e->getMessage()];
            }
        }

        // 2) Ejecutar lo del path real para forzar compile de clases internas
        //    de Laravel (Symfony Container resolutions, Validator factory,
        //    Hash driver, etc.) que NO se cargan con class_exists del controller
        //    pero SI se necesitan en login real. Sin efectos secundarios.
        try {
            // bcrypt: warmea el Hash driver + extension bcrypt
            $dummyHash = \Illuminate\Support\Facades\Hash::make('warmup-only-' . random_int(0, 999));
            \Illuminate\Support\Facades\Hash::check('warmup-only-x', $dummyHash);

            // Validator: warmea reglas comunes del login (email/required/string)
            \Illuminate\Support\Facades\Validator::make(
                ['email' => 'x@example.com', 'password' => 'x'],
                ['email' => 'required|email', 'password' => 'required|string']
            )->fails();

            // Cache + Redis: warmea el cache store y la conexion Predis/PhpRedis
            \Illuminate\Support\Facades\Cache::has('warmup:probe');

            // Container: resuelve servicios que el login usa
            app(\App\Services\MetadataService::class);
            app(\Illuminate\Auth\AuthManager::class)->guard('sanctum');

            // JSON encode/decode (Laravel response uses it intensively)
            json_encode(['probe' => 1]);
            json_decode('{"probe":1}', true);
        } catch (\Throwable $e) {
            $failed[] = ['stage' => 'runtime-warmup', 'error' => $e->getMessage()];
        }

        $durationMs = (int) round((microtime(true) - $t0) * 1000);

        return response()->json([
            'ok' => true,
            'loaded' => $loaded,
            'failed' => $failed,
            'duration_ms' => $durationMs,
            'worker_pid' => getmypid(),
        ]);
    }

    private function ipAllowed(?string $ip): bool
    {
        if (! $ip) return false;

        foreach ($this->allowed as $rule) {
            if (str_contains($rule, '/')) {
                if ($this->ipInCidr($ip, $rule)) return true;
            } elseif ($ip === $rule) {
                return true;
            }
        }
        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            return ($ipLong & $mask) === ($subnetLong & $mask);
        }
        return false;
    }
}
