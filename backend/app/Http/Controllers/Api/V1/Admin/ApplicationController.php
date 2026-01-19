<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\PaymentFrequency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddApplicationNoteRequest;
use App\Http\Requests\Admin\AssignApplicationRequest;
use App\Http\Requests\Admin\CounterOfferRequest;
use App\Http\Requests\Admin\UpdateApplicationStatusRequest;
use App\Http\Resources\Admin\ApplicationDetailResource;
use App\Http\Resources\Admin\ApplicationListResource;
use App\Models\Application;
use App\Models\ApplicationNote;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for managing applications in admin panel.
 *
 * Handles listing, viewing, status changes, counter-offers, assignment, and notes.
 * Document and verification operations are in separate controllers.
 *
 * @see ApplicationDocumentController for document operations
 * @see ApplicationVerificationController for verification operations
 */
class ApplicationController extends Controller
{
    /**
     * List all applications with filters.
     * Agents only see applications assigned to them.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $user = $request->user();

        $query = Application::where('tenant_id', $tenant->id)
            ->with([
                'applicant:id,first_name,last_name_1,last_name_2,full_name,phone,email,curp',
                'product:id,name,type',
                'assignedAgent:id,name',
            ]);

        // Filter by user's permission to view all applications
        if (!$user->canViewAllApplications()) {
            $query->where('assigned_to', $user->id);
        }

        $this->applyFilters($query, $request, $tenant);
        $this->applySorting($query, $request);

        $perPage = min($request->input('per_page', 20), 100);
        $applications = $query->paginate($perPage);

        return response()->json([
            'data' => ApplicationListResource::collection($applications->items()),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ]
        ]);
    }

    /**
     * Get a specific application with full details.
     */
    public function show(Request $request, Application $application): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $user = $request->user();

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $application->load([
            'applicant.addresses',
            'applicant.currentEmployment',
            'applicant.bankAccounts',
            'applicant.dataVerifications.verifier:id,name',
            'product',
            'documents',
            'references',
            'notes.user:id,name',
            'assignedAgent:id,name,email',
        ]);

        return response()->json([
            'data' => new ApplicationDetailResource($application),
            'allowed_statuses' => $this->getAllowedStatusesForUser($user),
        ]);
    }

    /**
     * Update application status.
     */
    public function updateStatus(UpdateApplicationStatusRequest $request, Application $application): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $newStatus = $request->status;
        $reason = $request->input('reason', $request->rejection_reason);
        $user = $request->user();

        // Restricted statuses that require approval permission
        $restrictedStatuses = [
            ApplicationStatus::APPROVED->value,
            ApplicationStatus::REJECTED->value,
            ApplicationStatus::CANCELLED->value,
            ApplicationStatus::DISBURSED->value,
            ApplicationStatus::ACTIVE->value,
            ApplicationStatus::COMPLETED->value,
            ApplicationStatus::DEFAULT->value,
        ];

        if (in_array($newStatus, $restrictedStatuses) && !$user->canApproveRejectApplications()) {
            return response()->json([
                'message' => 'No tienes permiso para cambiar a este estado',
                'error' => 'Forbidden',
            ], 403);
        }

        // Validate status transitions
        $transitionError = $this->validateStatusTransition($application, $newStatus, $request);
        if ($transitionError) {
            return $transitionError;
        }

        $application->changeStatus($newStatus, $reason, $user->id);

        // Log status change
        $this->logStatusChange($request, $application, $newStatus);

        return response()->json([
            'message' => 'Estatus actualizado',
            'data' => new ApplicationListResource($application->fresh()->load(['applicant', 'product']))
        ]);
    }

    /**
     * Create a counter-offer.
     */
    public function counterOffer(CounterOfferRequest $request, Application $application): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        if (!in_array($application->status, [
            ApplicationStatus::IN_REVIEW,
            ApplicationStatus::DOCS_PENDING,
        ])) {
            return response()->json([
                'message' => 'Solo se puede hacer contraoferta a solicitudes en revisión'
            ], 400);
        }

        $application->createCounterOffer(
            $request->amount,
            $request->term_months,
            $request->interest_rate,
            $request->payment_frequency,
            $request->reason,
            $request->user()->id
        );

        return response()->json([
            'message' => 'Contraoferta creada',
            'data' => new ApplicationListResource($application->fresh()->load(['applicant', 'product']))
        ]);
    }

    /**
     * Assign application to an agent.
     */
    public function assign(AssignApplicationRequest $request, Application $application): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        // Validar que el usuario asignado pertenezca al tenant
        $assignedTo = User::where('tenant_id', $tenant->id)
            ->find($request->user_id);

        if (!$assignedTo) {
            return response()->json(['message' => 'Usuario no encontrado en este tenant'], 404);
        }

        $application->assigned_to = $request->user_id;
        $application->assigned_at = now();
        $application->save();

        // Broadcast assignment
        event(new \App\Events\ApplicationAssigned(
            $application,
            $assignedTo,
            $request->user()
        ));

        // Add to timeline
        $application->changeStatus(
            $application->status->value,
            'Assigned to agent',
            $request->user()->id
        );

        return response()->json([
            'message' => 'Solicitud asignada',
            'data' => new ApplicationListResource($application->fresh()->load(['applicant', 'product', 'assignedAgent']))
        ]);
    }

    /**
     * Add a note to an application.
     */
    public function addNote(AddApplicationNoteRequest $request, Application $application): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $note = ApplicationNote::create([
            'tenant_id' => $tenant->id,
            'application_id' => $application->id,
            'user_id' => $request->user()->id,
            'content' => $request->content,
            'is_internal' => $request->input('is_internal', true),
        ]);

        // Add to application timeline
        $history = $application->status_history ?? [];
        $history[] = [
            'action' => 'NOTE_ADDED',
            'note_preview' => mb_substr($request->content, 0, 50) . (mb_strlen($request->content) > 50 ? '...' : ''),
            'is_internal' => $request->input('is_internal', true),
            'user_id' => $request->user()->id,
            'timestamp' => now()->toIso8601String(),
        ];
        $application->status_history = $history;
        $application->save();

        return response()->json([
            'message' => 'Nota agregada',
            'data' => [
                'id' => $note->id,
                'content' => $note->content,
                'author' => $request->user()->name,
                'is_internal' => $note->is_internal,
                'created_at' => $note->created_at->toIso8601String(),
            ]
        ], 201);
    }

    /**
     * Get API call logs for an application's applicant.
     */
    public function getApiLogs(Request $request, Application $application): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($application->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $applicant = $application->applicant;
        if (!$applicant) {
            return response()->json(['data' => []], 200);
        }

        $logs = \App\Models\ApiLog::where('applicant_id', $applicant->id)
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $formattedLogs = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'provider' => $log->provider,
                'service' => $log->service,
                'endpoint' => $log->endpoint,
                'method' => $log->method,
                'response_status' => $log->response_status,
                'success' => $log->success,
                'error_code' => $log->error_code,
                'error_message' => $log->error_message,
                'duration_ms' => $log->duration_ms,
                'request_payload' => $log->request_payload,
                'response_body' => $log->response_body,
                'created_at' => $log->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $formattedLogs
        ]);
    }

    /**
     * Apply filters to application query.
     */
    private function applyFilters($query, Request $request, $tenant): void
    {
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->input('search')) {
            // Escapar wildcards SQL y limitar longitud
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $search = mb_substr($search, 0, 100);
            $tenantId = $tenant->id;
            $query->where(function ($q) use ($search, $tenantId) {
                $q->where('folio', 'LIKE', "%{$search}%")
                    ->orWhereHas('applicant', function ($q) use ($search, $tenantId) {
                        $q->where('tenant_id', $tenantId)
                            ->where(function ($subQ) use ($search) {
                                $subQ->where('curp', 'LIKE', "%{$search}%")
                                    ->orWhere('first_name', 'LIKE', "%{$search}%")
                                    ->orWhere('last_name_1', 'LIKE', "%{$search}%")
                                    ->orWhere('last_name_2', 'LIKE', "%{$search}%")
                                    ->orWhere('full_name', 'LIKE', "%{$search}%")
                                    ->orWhere('phone', 'LIKE', "%{$search}%")
                                    ->orWhere('email', 'LIKE', "%{$search}%");
                            });
                    });
            });
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($assignedTo = $request->input('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }

        if ($assignment = $request->input('assignment')) {
            if ($assignment === 'unassigned') {
                $query->whereNull('assigned_to');
            } elseif ($assignment === 'assigned') {
                $query->whereNotNull('assigned_to');
            }
        }

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($request->boolean('stale')) {
            $activeStatuses = [
                ApplicationStatus::SUBMITTED,
                ApplicationStatus::IN_REVIEW,
                ApplicationStatus::DOCS_PENDING,
                ApplicationStatus::CORRECTIONS_PENDING,
            ];
            $query->whereIn('status', $activeStatuses)
                  ->where('updated_at', '<', now()->subHours(8));
        }
    }

    /**
     * Apply sorting to application query.
     */
    private function applySorting($query, Request $request): void
    {
        $allowedSortColumns = ['created_at', 'updated_at', 'status', 'folio', 'requested_amount'];
        $sortBy = $request->input('sort_by', 'created_at');
        $sortBy = in_array($sortBy, $allowedSortColumns, true) ? $sortBy : 'created_at';
        $sortOrder = $request->input('sort_order', 'desc');
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc'], true) ? $sortOrder : 'desc';
        $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * Get allowed status options based on user permissions.
     */
    private function getAllowedStatusesForUser($user): array
    {
        $allStatuses = [
            ['value' => ApplicationStatus::IN_REVIEW->value, 'label' => ApplicationStatus::IN_REVIEW->label()],
            ['value' => ApplicationStatus::DOCS_PENDING->value, 'label' => ApplicationStatus::DOCS_PENDING->label()],
            ['value' => ApplicationStatus::CORRECTIONS_PENDING->value, 'label' => ApplicationStatus::CORRECTIONS_PENDING->label()],
            ['value' => ApplicationStatus::COUNTER_OFFERED->value, 'label' => ApplicationStatus::COUNTER_OFFERED->label()],
            ['value' => ApplicationStatus::APPROVED->value, 'label' => ApplicationStatus::APPROVED->label()],
            ['value' => ApplicationStatus::REJECTED->value, 'label' => ApplicationStatus::REJECTED->label()],
            ['value' => ApplicationStatus::CANCELLED->value, 'label' => ApplicationStatus::CANCELLED->label()],
            ['value' => ApplicationStatus::DISBURSED->value, 'label' => ApplicationStatus::DISBURSED->label()],
            ['value' => ApplicationStatus::ACTIVE->value, 'label' => ApplicationStatus::ACTIVE->label()],
            ['value' => ApplicationStatus::COMPLETED->value, 'label' => ApplicationStatus::COMPLETED->label()],
            ['value' => ApplicationStatus::DEFAULT->value, 'label' => ApplicationStatus::DEFAULT->label()],
        ];

        if ($user->canApproveRejectApplications()) {
            return $allStatuses;
        }

        $restrictedStatuses = [
            ApplicationStatus::APPROVED->value,
            ApplicationStatus::REJECTED->value,
            ApplicationStatus::CANCELLED->value,
            ApplicationStatus::DISBURSED->value,
            ApplicationStatus::ACTIVE->value,
            ApplicationStatus::COMPLETED->value,
            ApplicationStatus::DEFAULT->value,
        ];

        return array_values(array_filter($allStatuses, fn($status) =>
            !in_array($status['value'], $restrictedStatuses)
        ));
    }

    /**
     * Validate status transition rules.
     */
    private function validateStatusTransition(Application $application, string $newStatus, Request $request): ?JsonResponse
    {
        if ($newStatus === ApplicationStatus::DISBURSED->value) {
            if ($application->status->value !== ApplicationStatus::APPROVED->value) {
                return response()->json([
                    'message' => 'Solo las solicitudes aprobadas pueden ser desembolsadas'
                ], 400);
            }
            $application->disbursement_reference = $request->disbursement_reference;
        }

        if ($newStatus === ApplicationStatus::ACTIVE->value) {
            if ($application->status->value !== ApplicationStatus::DISBURSED->value) {
                return response()->json([
                    'message' => 'Solo las solicitudes desembolsadas pueden marcarse como activas'
                ], 400);
            }
        }

        if (in_array($newStatus, [ApplicationStatus::COMPLETED->value, ApplicationStatus::DEFAULT->value])) {
            if ($application->status->value !== ApplicationStatus::ACTIVE->value) {
                return response()->json([
                    'message' => 'Solo las solicitudes activas pueden marcarse como completadas o en mora'
                ], 400);
            }
        }

        return null;
    }

    /**
     * Log status change to audit log.
     */
    private function logStatusChange(Request $request, Application $application, string $newStatus): void
    {
        $metadata = $request->attributes->get('metadata', []);
        AuditLog::log(
            AuditAction::APPLICATION_UPDATED->value,
            null,
            array_merge($metadata, [
                'user_id' => $request->user()->id,
                'applicant_id' => $application->applicant_id,
                'application_id' => $application->id,
                'new_status' => $newStatus,
            ])
        );
    }
}
