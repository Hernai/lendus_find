<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\StaffAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $tenant = $staff->tenant;

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
        $tenant = $staff->tenant;

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
        $tenant = $staff->tenant;

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
        /** @var StaffAccount $staff */
        $staff = $request->user();
        $tenant = $staff->tenant;

        // Get stats for today
        $today = now()->startOfDay();

        $totalToday = ApiLog::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $today)
            ->count();

        $successfulToday = ApiLog::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $today)
            ->where('success', true)
            ->count();

        $failedToday = ApiLog::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $today)
            ->where('success', false)
            ->count();

        // Get stats by provider (last 7 days)
        $lastWeek = now()->subDays(7);
        $byProvider = ApiLog::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $lastWeek)
            ->selectRaw('provider, COUNT(*) as total, SUM(CASE WHEN success = true THEN 1 ELSE 0 END) as successful')
            ->groupBy('provider')
            ->get()
            ->map(fn($row) => [
                'provider' => $row->provider,
                'total' => $row->total,
                'successful' => $row->successful,
                'failed' => $row->total - $row->successful,
            ]);

        // Average response time (last 7 days)
        $avgDuration = ApiLog::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $lastWeek)
            ->whereNotNull('duration_ms')
            ->avg('duration_ms');

        // Total cost (this month)
        $thisMonth = now()->startOfMonth();
        $totalCost = ApiLog::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $thisMonth)
            ->sum('cost');

        return $this->success([
            'today' => [
                'total' => $totalToday,
                'successful' => $successfulToday,
                'failed' => $failedToday,
            ],
            'by_provider' => $byProvider,
            'avg_duration_ms' => round($avgDuration ?? 0),
            'total_cost_this_month' => (float) $totalCost,
        ]);
    }
}
