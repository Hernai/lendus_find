<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\Application;
use App\Services\Export\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    /**
     * Get dashboard overview data.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        // Applications counts by status
        $statusCounts = Application::where('tenant_id', $tenant->id)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Today's applications
        $todayApplications = Application::where('tenant_id', $tenant->id)
            ->whereDate('created_at', $today)
            ->count();

        // This month's applications
        $monthApplications = Application::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $thisMonth)
            ->count();

        // Pending review (in review + docs pending)
        $pendingReview = ($statusCounts[ApplicationStatus::IN_REVIEW] ?? 0) +
            ($statusCounts[ApplicationStatus::DOCS_PENDING] ?? 0);

        // Total amounts
        $amounts = Application::where('tenant_id', $tenant->id)
            ->selectRaw('
                SUM(CASE WHEN status = ? THEN requested_amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN status = ? THEN approved_amount ELSE 0 END) as approved_amount,
                SUM(CASE WHEN status = ? THEN approved_amount ELSE 0 END) as disbursed_amount
            ', [
                ApplicationStatus::SUBMITTED,
                ApplicationStatus::APPROVED,
                ApplicationStatus::DISBURSED,
            ])
            ->first();

        // Recent applications
        $recentApplications = Application::where('tenant_id', $tenant->id)
            ->with(['applicant:id,personal_data', 'product:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($app) => [
                'id' => $app->id,
                'folio' => $app->folio,
                'applicant_name' => $app->applicant?->full_name ?? 'N/A',
                'product' => $app->product?->name ?? 'N/A',
                'amount' => (float) $app->requested_amount,
                'status' => $app->status,
                'created_at' => $app->created_at->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'summary' => [
                    'total_applications' => array_sum($statusCounts),
                    'today_applications' => $todayApplications,
                    'month_applications' => $monthApplications,
                    'pending_review' => $pendingReview,
                    'approved' => $statusCounts[ApplicationStatus::APPROVED] ?? 0,
                    'disbursed' => $statusCounts[ApplicationStatus::DISBURSED] ?? 0,
                    'rejected' => $statusCounts[ApplicationStatus::REJECTED] ?? 0,
                ],
                'amounts' => [
                    'pending' => (float) ($amounts->pending_amount ?? 0),
                    'approved' => (float) ($amounts->approved_amount ?? 0),
                    'disbursed' => (float) ($amounts->disbursed_amount ?? 0),
                ],
                'by_status' => $statusCounts,
                'recent_applications' => $recentApplications,
            ]
        ]);
    }

    /**
     * Get detailed statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $period = $request->input('period', '30'); // days

        $startDate = now()->subDays((int) $period)->startOfDay();

        // Applications over time
        $applicationsOverTime = Application::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(requested_amount) as amount')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Conversion rates
        $totalSubmitted = Application::where('tenant_id', $tenant->id)
            ->whereIn('status', [
                ApplicationStatus::SUBMITTED,
                ApplicationStatus::IN_REVIEW,
                ApplicationStatus::DOCS_PENDING,
                ApplicationStatus::APPROVED,
                ApplicationStatus::REJECTED,
                ApplicationStatus::DISBURSED,
            ])
            ->count();

        $totalApproved = Application::where('tenant_id', $tenant->id)
            ->whereIn('status', [
                ApplicationStatus::APPROVED,
                ApplicationStatus::DISBURSED,
            ])
            ->count();

        $totalDisbursed = Application::where('tenant_id', $tenant->id)
            ->where('status', ApplicationStatus::DISBURSED)
            ->count();

        // Average processing time (submitted to approved)
        $avgProcessingDays = Application::where('tenant_id', $tenant->id)
            ->whereNotNull('approved_at')
            ->whereNotNull('created_at')
            ->selectRaw('AVG(DATEDIFF(approved_at, created_at)) as avg_days')
            ->value('avg_days');

        // Top products
        $topProducts = Application::where('applications.tenant_id', $tenant->id)
            ->join('products', 'applications.product_id', '=', 'products.id')
            ->select(
                'products.name',
                DB::raw('COUNT(*) as applications'),
                DB::raw('SUM(applications.requested_amount) as total_amount')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('applications')
            ->limit(5)
            ->get();

        // Rejection reasons
        $rejectionReasons = Application::where('tenant_id', $tenant->id)
            ->where('status', ApplicationStatus::REJECTED)
            ->whereNotNull('rejection_reason')
            ->select('rejection_reason', DB::raw('COUNT(*) as count'))
            ->groupBy('rejection_reason')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'applications_over_time' => $applicationsOverTime,
                'conversion' => [
                    'total_submitted' => $totalSubmitted,
                    'total_approved' => $totalApproved,
                    'total_disbursed' => $totalDisbursed,
                    'approval_rate' => $totalSubmitted > 0 ?
                        round(($totalApproved / $totalSubmitted) * 100, 1) : 0,
                    'disbursement_rate' => $totalApproved > 0 ?
                        round(($totalDisbursed / $totalApproved) * 100, 1) : 0,
                ],
                'avg_processing_days' => round($avgProcessingDays ?? 0, 1),
                'top_products' => $topProducts,
                'rejection_reasons' => $rejectionReasons,
            ]
        ]);
    }

    /**
     * Generate applications report.
     */
    public function applicationsReport(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $startDate = $request->input('start_date') ?
            \Carbon\Carbon::parse($request->start_date)->startOfDay() :
            now()->subMonth()->startOfDay();

        $endDate = $request->input('end_date') ?
            \Carbon\Carbon::parse($request->end_date)->endOfDay() :
            now()->endOfDay();

        $status = $request->input('status');

        $query = Application::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['applicant:id,personal_data,curp', 'product:id,name']);

        if ($status) {
            $query->where('status', $status);
        }

        $applications = $query->orderByDesc('created_at')->get();

        $report = $applications->map(fn($app) => [
            'folio' => $app->folio,
            'created_at' => $app->created_at->format('Y-m-d H:i'),
            'applicant' => $app->applicant?->full_name,
            'curp' => $app->applicant?->curp,
            'product' => $app->product?->name,
            'requested_amount' => (float) $app->requested_amount,
            'approved_amount' => (float) ($app->approved_amount ?? 0),
            'term_months' => $app->term_months,
            'status' => $app->status,
            'approved_at' => $app->approved_at?->format('Y-m-d H:i'),
            'disbursed_at' => $app->disbursed_at?->format('Y-m-d H:i'),
        ]);

        $summary = [
            'total' => $applications->count(),
            'total_requested' => $applications->sum('requested_amount'),
            'total_approved' => $applications->sum('approved_amount'),
            'by_status' => $applications->groupBy('status')->map->count(),
        ];

        return response()->json([
            'data' => $report,
            'summary' => $summary,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ]
        ]);
    }

    /**
     * Generate disbursements report.
     */
    public function disbursementsReport(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $startDate = $request->input('start_date') ?
            \Carbon\Carbon::parse($request->start_date)->startOfDay() :
            now()->subMonth()->startOfDay();

        $endDate = $request->input('end_date') ?
            \Carbon\Carbon::parse($request->end_date)->endOfDay() :
            now()->endOfDay();

        $applications = Application::where('tenant_id', $tenant->id)
            ->where('status', ApplicationStatus::DISBURSED)
            ->whereBetween('disbursed_at', [$startDate, $endDate])
            ->with(['applicant:id,personal_data,bank_info', 'product:id,name'])
            ->orderByDesc('disbursed_at')
            ->get();

        $report = $applications->map(fn($app) => [
            'folio' => $app->folio,
            'disbursed_at' => $app->disbursed_at->format('Y-m-d H:i'),
            'applicant' => $app->applicant?->full_name,
            'product' => $app->product?->name,
            'amount' => (float) $app->approved_amount,
            'term_months' => $app->term_months,
            'monthly_payment' => (float) $app->monthly_payment,
            'reference' => $app->disbursement_reference,
            'bank' => $app->applicant?->bank_info['bank_name'] ?? null,
            'clabe' => $app->applicant?->bank_info['clabe'] ?? null,
        ]);

        $summary = [
            'total_disbursements' => $applications->count(),
            'total_amount' => $applications->sum('approved_amount'),
            'by_product' => $applications->groupBy(fn($app) => $app->product?->name ?? 'N/A')
                ->map(fn($group) => [
                    'count' => $group->count(),
                    'amount' => $group->sum('approved_amount'),
                ]),
        ];

        return response()->json([
            'data' => $report,
            'summary' => $summary,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ]
        ]);
    }

    /**
     * Generate portfolio report (active loans).
     */
    public function portfolioReport(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $applications = Application::where('tenant_id', $tenant->id)
            ->where('status', ApplicationStatus::DISBURSED)
            ->with(['applicant:id,first_name,last_name_1,last_name_2,curp', 'product:id,name'])
            ->orderByDesc('disbursed_at')
            ->get();

        $now = now();

        $report = $applications->map(function ($app) use ($now) {
            $disbursedAt = $app->disbursed_at;
            $termMonths = $app->term_months ?? 12;
            $monthsSinceDisbursement = $disbursedAt ? $disbursedAt->diffInMonths($now) : 0;
            $paymentsMade = min($monthsSinceDisbursement, $termMonths);
            $paymentsRemaining = max(0, $termMonths - $paymentsMade);

            // Simple linear amortization for outstanding balance estimate
            $originalAmount = (float) $app->approved_amount;
            $monthlyPrincipal = $originalAmount / $termMonths;
            $outstandingBalance = max(0, $originalAmount - ($monthlyPrincipal * $paymentsMade));

            // Next payment date (assuming monthly payments on same day)
            $nextPaymentDate = $disbursedAt?->copy()->addMonths($paymentsMade + 1);

            // Days past due (if next payment is overdue)
            $daysPastDue = 0;
            if ($nextPaymentDate && $nextPaymentDate->lt($now) && $paymentsRemaining > 0) {
                $daysPastDue = $nextPaymentDate->diffInDays($now);
            }

            return [
                'id' => $app->id,
                'folio' => $app->folio,
                'applicant' => $app->applicant?->full_name ?? 'N/A',
                'curp' => $app->applicant?->curp ?? '',
                'product' => $app->product?->name ?? 'N/A',
                'disbursed_at' => $disbursedAt?->format('Y-m-d'),
                'original_amount' => $originalAmount,
                'outstanding_balance' => round($outstandingBalance, 2),
                'term_months' => $termMonths,
                'payments_made' => $paymentsMade,
                'payments_remaining' => $paymentsRemaining,
                'next_payment_date' => $nextPaymentDate?->format('Y-m-d'),
                'days_past_due' => $daysPastDue,
                'status' => $daysPastDue > 0 ? 'MORA' : ($paymentsRemaining > 0 ? 'VIGENTE' : 'LIQUIDADO'),
                'monthly_payment' => (float) ($app->monthly_payment ?? 0),
                'interest_rate' => (float) ($app->interest_rate ?? 0),
            ];
        });

        // Summary by status
        $vigente = $report->where('status', 'VIGENTE');
        $mora = $report->where('status', 'MORA');
        $liquidado = $report->where('status', 'LIQUIDADO');

        $summary = [
            'total_loans' => $report->count(),
            'total_original_amount' => $report->sum('original_amount'),
            'total_outstanding' => $report->sum('outstanding_balance'),
            'vigente' => [
                'count' => $vigente->count(),
                'amount' => $vigente->sum('outstanding_balance'),
            ],
            'mora' => [
                'count' => $mora->count(),
                'amount' => $mora->sum('outstanding_balance'),
            ],
            'liquidado' => [
                'count' => $liquidado->count(),
            ],
            'avg_days_past_due' => round($mora->avg('days_past_due') ?? 0, 1),
        ];

        return response()->json([
            'data' => $report->values(),
            'summary' => $summary,
        ]);
    }

    /**
     * Export applications report to CSV.
     */
    public function exportApplications(Request $request, ExportService $exportService): StreamedResponse
    {
        $tenant = $request->attributes->get('tenant');

        $startDate = $request->input('start_date') ?
            \Carbon\Carbon::parse($request->start_date)->startOfDay() :
            now()->subMonth()->startOfDay();

        $endDate = $request->input('end_date') ?
            \Carbon\Carbon::parse($request->end_date)->endOfDay() :
            now()->endOfDay();

        $status = $request->input('status');

        $query = Application::where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['applicant', 'product:id,name']);

        if ($status) {
            $query->where('status', $status);
        }

        $applications = $query->orderByDesc('created_at')->get();

        return $exportService->exportApplicationsReport($applications);
    }

    /**
     * Export disbursements report to CSV.
     */
    public function exportDisbursements(Request $request, ExportService $exportService): StreamedResponse
    {
        $tenant = $request->attributes->get('tenant');

        $startDate = $request->input('start_date') ?
            \Carbon\Carbon::parse($request->start_date)->startOfDay() :
            now()->subMonth()->startOfDay();

        $endDate = $request->input('end_date') ?
            \Carbon\Carbon::parse($request->end_date)->endOfDay() :
            now()->endOfDay();

        $applications = Application::where('tenant_id', $tenant->id)
            ->where('status', ApplicationStatus::DISBURSED)
            ->whereBetween('disbursed_at', [$startDate, $endDate])
            ->with(['applicant', 'product:id,name'])
            ->orderByDesc('disbursed_at')
            ->get();

        return $exportService->exportDisbursementsReport($applications);
    }

    /**
     * Export portfolio report to CSV.
     */
    public function exportPortfolio(Request $request, ExportService $exportService): StreamedResponse
    {
        $tenant = $request->attributes->get('tenant');
        $now = now();

        $applications = Application::where('tenant_id', $tenant->id)
            ->where('status', ApplicationStatus::DISBURSED)
            ->with(['applicant', 'product:id,name'])
            ->orderByDesc('disbursed_at')
            ->get();

        $portfolio = $applications->map(function ($app) use ($now) {
            $disbursedAt = $app->disbursed_at;
            $termMonths = $app->term_months ?? 12;
            $monthsSinceDisbursement = $disbursedAt ? $disbursedAt->diffInMonths($now) : 0;
            $paymentsMade = min($monthsSinceDisbursement, $termMonths);
            $paymentsRemaining = max(0, $termMonths - $paymentsMade);

            $originalAmount = (float) $app->approved_amount;
            $monthlyPrincipal = $originalAmount / $termMonths;
            $outstandingBalance = max(0, $originalAmount - ($monthlyPrincipal * $paymentsMade));

            $nextPaymentDate = $disbursedAt?->copy()->addMonths($paymentsMade + 1);
            $daysPastDue = 0;
            if ($nextPaymentDate && $nextPaymentDate->lt($now) && $paymentsRemaining > 0) {
                $daysPastDue = $nextPaymentDate->diffInDays($now);
            }

            return [
                'folio' => $app->folio,
                'applicant' => $app->applicant?->full_name ?? 'N/A',
                'product' => $app->product?->name ?? 'N/A',
                'disbursed_at' => $disbursedAt?->format('Y-m-d') ?? '',
                'original_amount' => number_format($originalAmount, 2, '.', ''),
                'outstanding_balance' => number_format($outstandingBalance, 2, '.', ''),
                'term_months' => $termMonths,
                'payments_made' => $paymentsMade,
                'payments_remaining' => $paymentsRemaining,
                'next_payment_date' => $nextPaymentDate?->format('Y-m-d') ?? '',
                'days_past_due' => $daysPastDue,
                'status' => $daysPastDue > 0 ? 'Mora' : ($paymentsRemaining > 0 ? 'Vigente' : 'Liquidado'),
            ];
        });

        return $exportService->exportPortfolioReport($portfolio);
    }
}
