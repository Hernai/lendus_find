<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\StaffAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Staff API Log Controller (v2).
 *
 * Handles API log viewing for staff users.
 */
class ApiLogController extends Controller
{
    use ApiResponses;
    /**
     * List API logs with filtering and pagination.
     *
     * GET /v2/staff/api-logs
     */
    public function index(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        // Resolvemos el tenant del request (header X-Tenant-ID) en vez de
        // $staff->tenant porque el super admin global tiene tenant_id NULL.
        // RequireStaff ya valida cross-tenant para staff per-tenant.
        $tenant = app('tenant');

        $query = ApiLog::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc');

        // Filter by provider
        if ($request->has('provider') && $request->provider !== 'all') {
            $query->where('provider', $request->provider);
        }

        // Filter by service
        if ($request->has('service') && $request->service) {
            $query->where('service', 'like', '%' . $request->service . '%');
        }

        // Filter by success/failure
        if ($request->has('success') && $request->success !== 'all') {
            $query->where('success', $request->success === 'true');
        }

        // Filter by date range
        if ($request->has('from_date') && $request->from_date) {
            $query->where('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date') && $request->to_date) {
            $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
        }

        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $logs = $query->paginate($perPage);

        return $this->success([
            'logs' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'from' => $logs->firstItem(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'to' => $logs->lastItem(),
                'total' => $logs->total(),
            ]
        ]);
    }

    /**
     * Get a single API log with full details.
     *
     * GET /v2/staff/api-logs/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        // Resolvemos el tenant del request (header X-Tenant-ID) en vez de
        // $staff->tenant porque el super admin global tiene tenant_id NULL.
        // RequireStaff ya valida cross-tenant para staff per-tenant.
        $tenant = app('tenant');

        $apiLog = ApiLog::where('tenant_id', $tenant->id)
            ->where('id', $id)
            ->firstOrFail();

        return $this->success([
            'log' => $apiLog
        ]);
    }

    /**
     * Get available providers for filter dropdown.
     *
     * GET /v2/staff/api-logs/providers
     */
    public function providers(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        // Resolvemos el tenant del request (header X-Tenant-ID) en vez de
        // $staff->tenant porque el super admin global tiene tenant_id NULL.
        // RequireStaff ya valida cross-tenant para staff per-tenant.
        $tenant = app('tenant');

        $providers = ApiLog::where('tenant_id', $tenant->id)
            ->distinct()
            ->pluck('provider')
            ->filter()
            ->values();

        return $this->success([
            'providers' => $providers
        ]);
    }

    /**
     * Get summary statistics for API logs.
     *
     * GET /v2/staff/api-logs/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        // Cache 60s. Los stats no necesitan ser real-time exactos; refrescar
        // cada minuto absorbe el grueso del trafico (un dashboard que abre
        // 10 staff a la vez solo dispara 1 query stack en lugar de 10x7).
        $payload = Cache::remember(
            "api-logs:stats:{$tenant->id}",
            60,
            fn () => $this->buildStats($tenant->id)
        );

        return $this->success($payload);
    }

    private function buildStats(string $tenantId): array
    {
        $today = now()->startOfDay();
        $lastWeek = now()->subDays(7);
        $thisMonth = now()->startOfMonth();

        // Antes: 3 COUNT separados para today (total / successful / failed).
        // Ahora: 1 query con FILTER (PostgreSQL nativo). Ahorra ~560ms de
        // RTT a la DB remota (2 queries que ya no se hacen).
        $todayRow = ApiLog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $today)
            ->selectRaw('
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE success = true) as successful,
                COUNT(*) FILTER (WHERE success = false) as failed
            ')
            ->first();

        // Por proveedor (1 query).
        $byProvider = ApiLog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $lastWeek)
            ->selectRaw('provider, COUNT(*) as total, COUNT(*) FILTER (WHERE success = true) as successful')
            ->groupBy('provider')
            ->get()
            ->map(fn ($row) => [
                'provider' => $row->provider,
                'total' => (int) $row->total,
                'successful' => (int) $row->successful,
                'failed' => (int) $row->total - (int) $row->successful,
            ]);

        // Antes: 1 query avg + 1 query sum, ambos sobre rangos parecidos.
        // Ahora: 1 query unica que devuelve ambas metricas.
        $aggregates = ApiLog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $thisMonth)
            ->selectRaw('
                AVG(duration_ms) FILTER (WHERE created_at >= ? AND duration_ms IS NOT NULL) as avg_duration,
                SUM(cost) as total_cost
            ', [$lastWeek])
            ->first();

        return [
            'today' => [
                'total' => (int) ($todayRow->total ?? 0),
                'successful' => (int) ($todayRow->successful ?? 0),
                'failed' => (int) ($todayRow->failed ?? 0),
            ],
            'by_provider' => $byProvider,
            'avg_duration_ms' => (int) round($aggregates->avg_duration ?? 0),
            'total_cost_this_month' => (float) ($aggregates->total_cost ?? 0),
        ];
    }
}
