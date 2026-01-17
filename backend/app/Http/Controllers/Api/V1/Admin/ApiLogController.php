<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiLogController extends Controller
{
    /**
     * List API logs with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

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

        // Filter by applicant
        if ($request->has('applicant_id') && $request->applicant_id) {
            $query->where('applicant_id', $request->applicant_id);
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

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ]
        ]);
    }

    /**
     * Get a single API log with full details.
     */
    public function show(Request $request, ApiLog $apiLog): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($apiLog->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        // Load relations
        $apiLog->load(['applicant:id,user_id', 'applicant.user:id,name,email']);

        return response()->json([
            'data' => $apiLog
        ]);
    }

    /**
     * Get available providers for filter dropdown.
     */
    public function providers(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $providers = ApiLog::where('tenant_id', $tenant->id)
            ->distinct()
            ->pluck('provider')
            ->filter()
            ->values();

        return response()->json([
            'data' => $providers
        ]);
    }

    /**
     * Get available services for filter dropdown.
     */
    public function services(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $services = ApiLog::where('tenant_id', $tenant->id)
            ->distinct()
            ->pluck('service')
            ->filter()
            ->values();

        return response()->json([
            'data' => $services
        ]);
    }

    /**
     * Get summary statistics for API logs.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

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

        return response()->json([
            'data' => [
                'today' => [
                    'total' => $totalToday,
                    'successful' => $successfulToday,
                    'failed' => $failedToday,
                ],
                'by_provider' => $byProvider,
                'avg_duration_ms' => round($avgDuration ?? 0),
                'total_cost_this_month' => (float) $totalCost,
            ]
        ]);
    }
}
