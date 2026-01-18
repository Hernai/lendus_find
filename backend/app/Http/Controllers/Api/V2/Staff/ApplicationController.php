<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Controller;
use App\Models\ApplicationV2;
use App\Models\StaffAccount;
use App\Services\ApplicationV2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff Application Controller (v2).
 *
 * Handles loan application management for staff members using the new
 * normalized ApplicationV2 model.
 */
class ApplicationController extends Controller
{
    public function __construct(
        private ApplicationV2Service $service
    ) {}

    /**
     * List applications with filters.
     *
     * GET /v2/staff/applications
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|string',
            'applicant_type' => 'nullable|string|in:individual,company',
            'assigned_to' => 'nullable|uuid',
            'unassigned' => 'nullable|boolean',
            'risk_level' => 'nullable|string',
            'product_id' => 'nullable|uuid',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'search' => 'nullable|string|max:100',
            'sort_by' => 'nullable|string|in:created_at,submitted_at,requested_amount,status',
            'sort_dir' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $applications = $this->service->list(
            $staff->tenant,
            $validated,
            $validated['per_page'] ?? 20
        );

        return response()->json([
            'applications' => $applications->map(fn($app) => $this->formatApplication($app)),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
        ]);
    }

    /**
     * Get dashboard statistics.
     *
     * GET /v2/staff/applications/statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $stats = $this->service->getStatistics(
            $staff->tenant,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null
        );

        return response()->json($stats);
    }

    /**
     * Get unassigned applications.
     *
     * GET /v2/staff/applications/unassigned
     */
    public function unassigned(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $applications = $this->service->getUnassigned($staff->tenant);

        return response()->json([
            'applications' => $applications->map(fn($app) => $this->formatApplication($app)),
        ]);
    }

    /**
     * Get applications assigned to current staff.
     *
     * GET /v2/staff/applications/my-queue
     */
    public function myQueue(Request $request): JsonResponse
    {
        $status = $request->query('status');

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $applications = $this->service->getAssignedTo($staff, $status);

        return response()->json([
            'applications' => $applications->map(fn($app) => $this->formatApplication($app)),
        ]);
    }

    /**
     * Show application details.
     *
     * GET /v2/staff/applications/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->with(['product', 'person', 'company', 'assignedTo', 'statusHistory'])
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        return response()->json([
            'application' => $this->formatApplicationDetail($application),
        ]);
    }

    /**
     * Assign application to staff member.
     *
     * POST /v2/staff/applications/{id}/assign
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => 'required|uuid|exists:staff_accounts,id',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $assignee = StaffAccount::where('id', $validated['staff_id'])
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$assignee) {
            return response()->json([
                'error' => 'STAFF_NOT_FOUND',
                'message' => 'El analista seleccionado no existe.',
            ], 404);
        }

        $application = $this->service->assign($application, $assignee, $staff);

        return response()->json([
            'message' => 'Solicitud asignada exitosamente.',
            'application' => $this->formatApplication($application),
        ]);
    }

    /**
     * Change application status.
     *
     * POST /v2/staff/applications/{id}/status
     */
    public function changeStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:' . implode(',', array_keys(ApplicationV2::statuses())),
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        try {
            $application = $this->service->changeStatus(
                $application,
                $validated['status'],
                $staff,
                $validated['notes'] ?? null
            );

            return response()->json([
                'message' => 'Estado actualizado exitosamente.',
                'application' => $this->formatApplication($application),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'STATUS_CHANGE_FAILED',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Approve application.
     *
     * POST /v2/staff/applications/{id}/approve
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:1000',
            'term_months' => 'nullable|integer|min:1|max:120',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        if (!$application->canBeApproved()) {
            return response()->json([
                'error' => 'NOT_APPROVABLE',
                'message' => 'Esta solicitud no puede ser aprobada en su estado actual.',
            ], 400);
        }

        $application = $this->service->approve(
            $application,
            $staff,
            $validated['amount'] ?? null,
            $validated['term_months'] ?? null,
            $validated['interest_rate'] ?? null,
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Solicitud aprobada exitosamente.',
            'application' => $this->formatApplication($application),
        ]);
    }

    /**
     * Reject application.
     *
     * POST /v2/staff/applications/{id}/reject
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        if (!$application->canBeRejected()) {
            return response()->json([
                'error' => 'NOT_REJECTABLE',
                'message' => 'Esta solicitud no puede ser rechazada en su estado actual.',
            ], 400);
        }

        $application = $this->service->reject(
            $application,
            $staff,
            $validated['reason'],
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Solicitud rechazada.',
            'application' => $this->formatApplication($application),
        ]);
    }

    /**
     * Send counter offer.
     *
     * POST /v2/staff/applications/{id}/counter-offer
     */
    public function sendCounterOffer(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000',
            'term_months' => 'required|integer|min:1|max:120',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'reason' => 'nullable|string|max:500',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $application = $this->service->sendCounterOffer(
            $application,
            $staff,
            [
                'amount' => $validated['amount'],
                'term_months' => $validated['term_months'],
                'interest_rate' => $validated['interest_rate'] ?? null,
            ],
            $validated['reason'] ?? null
        );

        return response()->json([
            'message' => 'Contraoferta enviada al solicitante.',
            'application' => $this->formatApplication($application),
        ]);
    }

    /**
     * Update verification checklist.
     *
     * PATCH /v2/staff/applications/{id}/verification
     */
    public function updateVerification(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'checks' => 'required|array',
            'checks.*' => 'boolean',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $application = $this->service->updateVerification($application, $validated['checks']);

        return response()->json([
            'message' => 'Checklist actualizado.',
            'verification_checklist' => $application->verification_checklist,
        ]);
    }

    /**
     * Set risk assessment.
     *
     * POST /v2/staff/applications/{id}/risk-assessment
     */
    public function setRiskAssessment(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'level' => 'required|string|in:' . implode(',', [
                ApplicationV2::RISK_LOW,
                ApplicationV2::RISK_MEDIUM,
                ApplicationV2::RISK_HIGH,
                ApplicationV2::RISK_VERY_HIGH,
            ]),
            'data' => 'nullable|array',
        ]);

        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $application = $this->service->setRiskAssessment(
            $application,
            $validated['level'],
            $validated['data'] ?? null
        );

        return response()->json([
            'message' => 'Evaluación de riesgo actualizada.',
            'risk_level' => $application->risk_level,
            'risk_data' => $application->risk_data,
        ]);
    }

    /**
     * Get status history.
     *
     * GET /v2/staff/applications/{id}/history
     */
    public function history(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $staff->tenant_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        $history = $this->service->getStatusHistory($application);

        return response()->json([
            'history' => $history->map(fn($h) => [
                'from_status' => $h->from_status,
                'from_status_label' => $h->from_status_label,
                'to_status' => $h->to_status,
                'to_status_label' => $h->to_status_label,
                'changed_by' => $h->changed_by_name,
                'notes' => $h->notes,
                'created_at' => $h->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Format application for list view.
     */
    private function formatApplication(ApplicationV2 $app): array
    {
        return [
            'id' => $app->id,
            'status' => $app->status,
            'status_label' => $app->status_label,
            'applicant_type' => $app->applicant_type,
            'applicant_name' => $app->is_individual
                ? $app->person?->full_name
                : $app->company?->legal_name,
            'applicant_rfc' => $app->is_individual
                ? $app->person?->rfc
                : $app->company?->rfc,
            'product' => [
                'id' => $app->product_id,
                'name' => $app->product?->name,
                'type' => $app->product?->type,
            ],
            'requested_amount' => $app->requested_amount,
            'requested_term_months' => $app->requested_term_months,
            'monthly_payment' => $app->monthly_payment,
            'risk_level' => $app->risk_level,
            'assigned_to' => $app->assignedTo ? [
                'id' => $app->assignedTo->id,
                'name' => $app->assignedTo->profile?->full_name ?? $app->assignedTo->email,
            ] : null,
            'created_at' => $app->created_at?->toIso8601String(),
            'submitted_at' => $app->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * Format application for detail view.
     */
    private function formatApplicationDetail(ApplicationV2 $app): array
    {
        $data = $this->formatApplication($app);

        $data['interest_rate'] = $app->interest_rate;
        $data['total_interest'] = $app->total_interest;
        $data['total_amount'] = $app->total_amount;
        $data['cat'] = $app->cat;
        $data['purpose'] = $app->purpose;
        $data['purpose_description'] = $app->purpose_description;

        $data['approved_amount'] = $app->approved_amount;
        $data['approved_term_months'] = $app->approved_term_months;
        $data['approved_interest_rate'] = $app->approved_interest_rate;
        $data['rejection_reason'] = $app->rejection_reason;
        $data['decision_notes'] = $app->decision_notes;
        $data['decision_at'] = $app->decision_at?->toIso8601String();
        $data['decision_by'] = $app->decision_by;

        $data['has_counter_offer'] = $app->has_counter_offer;
        $data['counter_offer'] = $app->counter_offer;
        $data['counter_offer_accepted'] = $app->counter_offer_accepted;

        $data['verification_checklist'] = $app->verification_checklist;
        $data['risk_data'] = $app->risk_data;
        $data['snapshot_data'] = $app->snapshot_data;

        $data['external_id'] = $app->external_id;
        $data['external_system'] = $app->external_system;
        $data['synced_at'] = $app->synced_at?->toIso8601String();

        $data['person'] = $app->person ? [
            'id' => $app->person->id,
            'full_name' => $app->person->full_name,
            'curp' => $app->person->curp,
            'rfc' => $app->person->rfc,
            'email' => $app->person->email,
            'phone' => $app->person->phone,
        ] : null;

        $data['company'] = $app->company ? [
            'id' => $app->company->id,
            'legal_name' => $app->company->legal_name,
            'trade_name' => $app->company->trade_name,
            'rfc' => $app->company->rfc,
        ] : null;

        $data['status_history'] = $app->statusHistory->map(fn($h) => [
            'from_status' => $h->from_status,
            'to_status' => $h->to_status,
            'changed_by' => $h->changed_by_name,
            'notes' => $h->notes,
            'created_at' => $h->created_at->toIso8601String(),
        ]);

        return $data;
    }
}
