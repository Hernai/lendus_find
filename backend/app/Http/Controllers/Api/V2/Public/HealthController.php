<?php

namespace App\Http\Controllers\Api\V2\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * GET /api/v2/public/health
 *
 * Endpoint de health-check para que la app móvil (Capacitor) verifique al
 * arrancar que el backend está disponible. Se cachea 5 segundos para
 * absorber picos de polling.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $data = Cache::remember('health:public', 5, function () {
            return [
                'status' => 'ok',
                'timestamp' => now()->toIso8601String(),
                'db' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
            ];
        });

        $isHealthy = $data['db'] === 'ok' && $data['redis'] === 'ok';

        return response()->json($data, $isHealthy ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo()->query('SELECT 1');
            return 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }

    private function checkRedis(): string
    {
        try {
            Redis::connection()->ping();
            return 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }
}
