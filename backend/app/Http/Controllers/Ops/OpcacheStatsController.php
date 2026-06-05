<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Devuelve el estado de OPCache para health-checks y monitoreo.
 *
 * Se expone en `/ops/opcache-stats` sin auth pero restringido por IP
 * allowlist (127.0.0.1 + LAN de Jenkins). Documentado en deploy-ops
 * skill seccion 22.5.
 */
class OpcacheStatsController extends Controller
{
    /**
     * IPs/rangos autorizados. Cualquier otra fuente recibe 403.
     * Se aceptan IPs exactas o CIDR.
     */
    private array $allowed = [
        '127.0.0.1',
        '::1',
        // LAN interna donde corre Jenkins. Ajustar al rango real.
        '192.168.0.0/24',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->ipAllowed($request->ip())) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        if (! function_exists('opcache_get_status')) {
            return response()->json([
                'opcache_enabled' => false,
                'reason' => 'opcache extension not loaded',
            ]);
        }

        // false = sin scripts cacheados detallados (ahorra memoria al serializar).
        $status = opcache_get_status(false);

        if ($status === false) {
            return response()->json([
                'opcache_enabled' => false,
                'reason' => 'opcache_get_status returned false',
            ]);
        }

        return response()->json($status);
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
