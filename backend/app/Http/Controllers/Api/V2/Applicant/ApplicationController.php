<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Http\Controllers\Controller;
use App\Models\ApplicantAccount;
use App\Models\ApplicationV2;
use App\Models\Product;
use App\Services\ApplicationV2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Applicant Application Controller (v2).
 *
 * Handles loan applications for authenticated applicants using the new
 * normalized ApplicationV2 model.
 */
class ApplicationController extends Controller
{
    public function __construct(
        private ApplicationV2Service $service
    ) {}

    /**
     * List applicant's applications.
     *
     * GET /v2/applicant/applications
     */
    public function index(Request $request): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();

        if (!$account->person) {
            return response()->json([
                'error' => 'PROFILE_INCOMPLETE',
                'message' => 'Debes completar tu perfil antes de ver solicitudes.',
            ], 400);
        }

        $status = $request->query('status');
        $applications = $this->service->getForPerson($account->person, $status);

        return response()->json([
            'applications' => $applications->map(fn($app) => $this->formatApplication($app)),
        ]);
    }

    /**
     * Create a new application.
     *
     * POST /v2/applicant/applications
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'amount' => 'required|numeric|min:1000',
            'term_months' => 'required|integer|min:1|max:120',
            'purpose' => 'required|string|max:50',
            'purpose_description' => 'nullable|string|max:500',
            'frequency' => 'nullable|string|in:WEEKLY,BIWEEKLY,MONTHLY',
        ]);

        /** @var ApplicantAccount $account */
        $account = $request->user();

        if (!$account->person) {
            return response()->json([
                'error' => 'PROFILE_INCOMPLETE',
                'message' => 'Debes completar tu perfil antes de solicitar un crédito.',
            ], 400);
        }

        $tenant = $account->tenant;
        $product = Product::findOrFail($validated['product_id']);

        // Verify product belongs to tenant
        if ($product->tenant_id !== $tenant->id) {
            return response()->json([
                'error' => 'PRODUCT_NOT_FOUND',
                'message' => 'El producto seleccionado no está disponible.',
            ], 404);
        }

        // Verify product limits
        if (!$product->isAmountValid($validated['amount'])) {
            return response()->json([
                'error' => 'INVALID_AMOUNT',
                'message' => "El monto debe estar entre {$product->min_amount} y {$product->max_amount}.",
            ], 422);
        }

        if (!$product->isTermValid($validated['term_months'])) {
            return response()->json([
                'error' => 'INVALID_TERM',
                'message' => "El plazo debe estar entre {$product->min_term_months} y {$product->max_term_months} meses.",
            ], 422);
        }

        $application = $this->service->createForPerson(
            $tenant,
            $account->person,
            $product,
            [
                'amount' => $validated['amount'],
                'term_months' => $validated['term_months'],
                'purpose' => $validated['purpose'],
                'purpose_description' => $validated['purpose_description'] ?? null,
                'frequency' => $validated['frequency'] ?? 'MONTHLY',
            ],
            $account
        );

        return response()->json([
            'message' => 'Solicitud creada exitosamente.',
            'application' => $this->formatApplication($application),
        ], 201);
    }

    /**
     * Show application details.
     *
     * GET /v2/applicant/applications/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where('person_id', $account->person_id)
            ->with(['product', 'statusHistory'])
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
     * Update draft application.
     *
     * PATCH /v2/applicant/applications/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:1000',
            'term_months' => 'sometimes|integer|min:1|max:120',
            'purpose' => 'sometimes|string|max:50',
            'purpose_description' => 'nullable|string|max:500',
            'frequency' => 'nullable|string|in:WEEKLY,BIWEEKLY,MONTHLY',
        ]);

        /** @var ApplicantAccount $account */
        $account = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where('person_id', $account->person_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        if (!$application->canBeEdited()) {
            return response()->json([
                'error' => 'NOT_EDITABLE',
                'message' => 'Esta solicitud no puede ser modificada.',
            ], 400);
        }

        // Verify product limits if amount/term changed
        $product = $application->product;
        $newAmount = $validated['amount'] ?? $application->requested_amount;
        $newTerm = $validated['term_months'] ?? $application->requested_term_months;

        if (!$product->isAmountValid($newAmount)) {
            return response()->json([
                'error' => 'INVALID_AMOUNT',
                'message' => "El monto debe estar entre {$product->min_amount} y {$product->max_amount}.",
            ], 422);
        }

        if (!$product->isTermValid($newTerm)) {
            return response()->json([
                'error' => 'INVALID_TERM',
                'message' => "El plazo debe estar entre {$product->min_term_months} y {$product->max_term_months} meses.",
            ], 422);
        }

        $application = $this->service->updateLoanTerms($application, $validated);

        return response()->json([
            'message' => 'Solicitud actualizada exitosamente.',
            'application' => $this->formatApplication($application),
        ]);
    }

    /**
     * Submit application for review.
     *
     * POST /v2/applicant/applications/{id}/submit
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where('person_id', $account->person_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        // Validate before submission
        $errors = $this->service->validateForSubmission($application);
        if (!empty($errors)) {
            return response()->json([
                'error' => 'VALIDATION_FAILED',
                'message' => 'La solicitud está incompleta.',
                'errors' => $errors,
            ], 422);
        }

        try {
            $application = $this->service->submit(
                $application,
                $account,
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'message' => 'Solicitud enviada exitosamente.',
                'application' => $this->formatApplication($application),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'SUBMISSION_FAILED',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel application.
     *
     * POST /v2/applicant/applications/{id}/cancel
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        /** @var ApplicantAccount $account */
        $account = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where('person_id', $account->person_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        if (!$application->canBeCancelled()) {
            return response()->json([
                'error' => 'NOT_CANCELLABLE',
                'message' => 'Esta solicitud no puede ser cancelada.',
            ], 400);
        }

        $application = $this->service->cancel(
            $application,
            $account->id,
            ApplicantAccount::class,
            $validated['reason'] ?? 'Cancelado por el solicitante'
        );

        return response()->json([
            'message' => 'Solicitud cancelada exitosamente.',
            'application' => $this->formatApplication($application),
        ]);
    }

    /**
     * Respond to counter offer.
     *
     * POST /v2/applicant/applications/{id}/counter-offer/respond
     */
    public function respondToCounterOffer(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'accepted' => 'required|boolean',
        ]);

        /** @var ApplicantAccount $account */
        $account = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where('person_id', $account->person_id)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => 'NOT_FOUND',
                'message' => 'Solicitud no encontrada.',
            ], 404);
        }

        if (!$application->has_counter_offer) {
            return response()->json([
                'error' => 'NO_COUNTER_OFFER',
                'message' => 'No hay una contraoferta pendiente.',
            ], 400);
        }

        $application = $this->service->respondToCounterOffer(
            $application,
            $account,
            $validated['accepted']
        );

        $message = $validated['accepted']
            ? 'Contraoferta aceptada. Tu crédito ha sido aprobado.'
            : 'Contraoferta rechazada. Tu solicitud ha sido cancelada.';

        return response()->json([
            'message' => $message,
            'application' => $this->formatApplication($application),
        ]);
    }

    /**
     * Get status history.
     *
     * GET /v2/applicant/applications/{id}/history
     */
    public function history(Request $request, string $id): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();

        $application = ApplicationV2::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where('person_id', $account->person_id)
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
            'product' => [
                'id' => $app->product_id,
                'name' => $app->product?->name,
                'type' => $app->product?->type,
            ],
            'requested_amount' => $app->requested_amount,
            'requested_term_months' => $app->requested_term_months,
            'monthly_payment' => $app->monthly_payment,
            'interest_rate' => $app->interest_rate,
            'purpose' => $app->purpose,
            'has_counter_offer' => $app->has_counter_offer,
            'counter_offer' => $app->counter_offer,
            'approved_amount' => $app->approved_amount,
            'approved_term_months' => $app->approved_term_months,
            'created_at' => $app->created_at?->toIso8601String(),
            'submitted_at' => $app->submitted_at?->toIso8601String(),
            'decision_at' => $app->decision_at?->toIso8601String(),
        ];
    }

    /**
     * Format application for detail view.
     */
    private function formatApplicationDetail(ApplicationV2 $app): array
    {
        $data = $this->formatApplication($app);

        $data['purpose_description'] = $app->purpose_description;
        $data['total_interest'] = $app->total_interest;
        $data['total_amount'] = $app->total_amount;
        $data['cat'] = $app->cat;
        $data['rejection_reason'] = $app->rejection_reason;
        $data['decision_notes'] = $app->decision_notes;
        $data['expires_at'] = $app->expires_at?->toIso8601String();

        $data['status_history'] = $app->statusHistory->map(fn($h) => [
            'from_status' => $h->from_status,
            'to_status' => $h->to_status,
            'notes' => $h->notes,
            'created_at' => $h->created_at->toIso8601String(),
        ]);

        return $data;
    }
}
