<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\ApplicantAccount;
use App\Models\Application;
use App\Models\Product;
use App\Services\ApplicationEventService;
use App\Services\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Applicant Application Controller (v2).
 *
 * Handles loan applications for authenticated applicants using the new
 * normalized Application model.
 */
class ApplicationController extends Controller
{
    use ApiResponses;

    public function __construct(
        private ApplicationService $service,
        private ApplicationEventService $eventService
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
            return $this->badRequest('PROFILE_INCOMPLETE', 'Debes completar tu perfil antes de ver solicitudes.');
        }

        $status = $request->query('status');
        $applications = $this->service->getForPerson($account->person, $status);

        return $this->success([
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
            // Accept both 'amount' and 'requested_amount' for compatibility
            'amount' => 'required_without:requested_amount|numeric|min:1000',
            'requested_amount' => 'required_without:amount|numeric|min:1000',
            'term_months' => 'required|integer|min:1|max:120',
            // Purpose is optional, defaults to 'PERSONAL'
            'purpose' => 'nullable|string|max:50',
            'purpose_description' => 'nullable|string|max:500',
            // Accept both 'frequency' and 'payment_frequency' for compatibility
            'frequency' => 'nullable|string|in:WEEKLY,BIWEEKLY,MONTHLY',
            'payment_frequency' => 'nullable|string|in:WEEKLY,BIWEEKLY,MONTHLY',
            // Allow passing simulation data for reference
            'simulation_data' => 'nullable|array',
        ]);

        // Normalize field names (frontend uses requested_amount/payment_frequency)
        $validated['amount'] = $validated['amount'] ?? $validated['requested_amount'];
        $validated['frequency'] = $validated['frequency'] ?? $validated['payment_frequency'] ?? 'MONTHLY';
        $validated['purpose'] = $validated['purpose'] ?? 'PERSONAL';

        /** @var ApplicantAccount $account */
        $account = $request->user();

        if (!$account->person) {
            return $this->badRequest('PROFILE_INCOMPLETE', 'Debes completar tu perfil antes de solicitar un crédito.');
        }

        $tenant = $account->tenant;
        $product = Product::findOrFail($validated['product_id']);

        // Verify product belongs to tenant
        if ($product->tenant_id !== $tenant->id) {
            return $this->notFound('El producto seleccionado no está disponible.');
        }

        // Verify product limits
        if (!$product->isAmountValid($validated['amount'])) {
            return $this->error('INVALID_AMOUNT', "El monto debe estar entre {$product->min_amount} y {$product->max_amount}.", 422);
        }

        if (!$product->isTermValid($validated['term_months'])) {
            return $this->error('INVALID_TERM', "El plazo debe estar entre {$product->min_term_months} y {$product->max_term_months} meses.", 422);
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

        // Record event for timeline
        $this->eventService->recordApplicationCreated(
            $application,
            $account->id,
            $request
        );

        return $this->created($this->formatApplication($application), 'Solicitud creada exitosamente.');
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

        // Use person relationship to get person_id (more reliable than person_id column)
        $person = $account->getPersonOrFind();
        if (!$person) {
            return $this->notFound('No tienes un perfil asociado.');
        }

        $application = Application::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where('person_id', $person->id)
            ->with(['product', 'statusHistory'])
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        return $this->success($this->formatApplicationDetail($application));
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

        // Use person relationship to get person_id (more reliable than person_id column)
        $person = $account->getPersonOrFind();
        if (!$person) {
            return $this->notFound('No tienes un perfil asociado.');
        }

        $application = Application::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where('person_id', $person->id)
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        if (!$application->canBeEdited()) {
            return $this->badRequest('NOT_EDITABLE', 'Esta solicitud no puede ser modificada.');
        }

        // Verify product limits if amount/term changed
        $product = $application->product;
        $newAmount = $validated['amount'] ?? $application->requested_amount;
        $newTerm = $validated['term_months'] ?? $application->requested_term_months;

        if (!$product->isAmountValid($newAmount)) {
            return $this->error('INVALID_AMOUNT', "El monto debe estar entre {$product->min_amount} y {$product->max_amount}.", 422);
        }

        if (!$product->isTermValid($newTerm)) {
            return $this->error('INVALID_TERM', "El plazo debe estar entre {$product->min_term_months} y {$product->max_term_months} meses.", 422);
        }

        $application = $this->service->updateLoanTerms($application, $validated);

        return $this->success($this->formatApplication($application), 'Solicitud actualizada exitosamente.');
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

        // Use person relationship to get person_id
        $person = $account->getPersonOrFind();
        if (!$person) {
            return $this->notFound('No tienes un perfil asociado.');
        }

        $application = Application::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where('person_id', $person->id)
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        // Validate before submission
        $errors = $this->service->validateForSubmission($application);
        if (!empty($errors)) {
            return $this->validationError('La solicitud está incompleta.', $errors);
        }

        try {
            $application = $this->service->submit(
                $application,
                $account,
                $request->ip(),
                $request->userAgent()
            );

            // Record event for timeline
            $this->eventService->recordApplicationSubmitted(
                $application,
                $account->id,
                $request
            );

            return $this->success($this->formatApplication($application), 'Solicitud enviada exitosamente.');
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest('SUBMISSION_FAILED', $e->getMessage());
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

        // Use person relationship to get person_id
        $person = $account->getPersonOrFind();
        if (!$person) {
            return $this->notFound('No tienes un perfil asociado.');
        }

        $application = Application::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where('person_id', $person->id)
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        if (!$application->canBeCancelled()) {
            return $this->badRequest('NOT_CANCELLABLE', 'Esta solicitud no puede ser cancelada.');
        }

        $application = $this->service->cancel(
            $application,
            $account->id,
            ApplicantAccount::class,
            $validated['reason'] ?? 'Cancelado por el solicitante'
        );

        return $this->success($this->formatApplication($application), 'Solicitud cancelada exitosamente.');
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

        // Use person relationship to get person_id
        $person = $account->getPersonOrFind();
        if (!$person) {
            return $this->notFound('No tienes un perfil asociado.');
        }

        $application = Application::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where('person_id', $person->id)
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        if (!$application->has_counter_offer) {
            return $this->badRequest('NO_COUNTER_OFFER', 'No hay una contraoferta pendiente.');
        }

        $application = $this->service->respondToCounterOffer(
            $application,
            $account,
            $validated['accepted']
        );

        $message = $validated['accepted']
            ? 'Contraoferta aceptada. Tu crédito ha sido aprobado.'
            : 'Contraoferta rechazada. Tu solicitud ha sido cancelada.';

        return $this->success($this->formatApplication($application), $message);
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

        // Use person relationship to get person_id
        $person = $account->getPersonOrFind();
        if (!$person) {
            return $this->notFound('No tienes un perfil asociado.');
        }

        $application = Application::where('id', $id)
            ->where('tenant_id', $account->tenant_id)
            ->where('person_id', $person->id)
            ->first();

        if (!$application) {
            return $this->notFound('Solicitud no encontrada.');
        }

        $history = $this->service->getStatusHistory($application);

        return $this->success([
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
    private function formatApplication(Application $app): array
    {
        // Get opening commission from product (percentage * requested amount)
        $openingCommissionRate = $app->product?->opening_commission ?? 0;
        $openingCommissionAmount = $app->requested_amount * ($openingCommissionRate / 100);

        // Get default payment frequency from product
        $defaultFrequency = $app->product?->payment_frequencies[0] ?? 'MONTHLY';

        // Check for rejected items (data fields and documents)
        $rejectionInfo = $this->getRejectionInfo($app);

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
            'payment_frequency' => $defaultFrequency,
            'monthly_payment' => $app->monthly_payment,
            'interest_rate' => $app->interest_rate,
            'opening_commission' => $openingCommissionAmount,
            'total_interest' => $app->total_interest ?? 0,
            'total_amount' => $app->total_amount ?? 0,
            'cat' => $app->cat ?? 0,
            'purpose' => $app->purpose,
            'has_counter_offer' => $app->has_counter_offer,
            'counter_offer' => $app->counter_offer,
            'approved_amount' => $app->approved_amount,
            'approved_term_months' => $app->approved_term_months,
            'created_at' => $app->created_at?->toIso8601String(),
            'submitted_at' => $app->submitted_at?->toIso8601String(),
            'decision_at' => $app->decision_at?->toIso8601String(),
            // Rejection info for correction UI
            'has_rejected_items' => $rejectionInfo['has_rejected_items'],
            'rejected_fields_count' => $rejectionInfo['rejected_fields_count'],
            'rejected_documents_count' => $rejectionInfo['rejected_documents_count'],
        ];
    }

    /**
     * Get rejection info for an application (rejected fields and documents).
     */
    private function getRejectionInfo(Application $app): array
    {
        // Count rejected fields from verification_checklist
        $checklist = $app->verification_checklist ?? [];
        $rejectedFieldsCount = 0;
        foreach ($checklist as $fieldData) {
            if (isset($fieldData['status']) && strtoupper($fieldData['status']) === 'REJECTED') {
                $rejectedFieldsCount++;
            }
        }

        // Count rejected documents (both from application and person)
        $rejectedDocsCount = $app->documents()
            ->where('status', \App\Enums\DocumentStatus::REJECTED)
            ->whereNull('replaced_at')
            ->count();

        // Also check person's documents if person is loaded
        if ($app->person_id) {
            $personRejectedDocs = \App\Models\Document::where('documentable_type', \App\Models\Person::class)
                ->where('documentable_id', $app->person_id)
                ->where('status', \App\Enums\DocumentStatus::REJECTED)
                ->whereNull('replaced_at')
                ->count();
            $rejectedDocsCount += $personRejectedDocs;
        }

        return [
            'has_rejected_items' => ($rejectedFieldsCount + $rejectedDocsCount) > 0,
            'rejected_fields_count' => $rejectedFieldsCount,
            'rejected_documents_count' => $rejectedDocsCount,
        ];
    }

    /**
     * Format application for detail view.
     */
    private function formatApplicationDetail(Application $app): array
    {
        $data = $this->formatApplication($app);

        // Additional detail fields
        $data['purpose_description'] = $app->purpose_description;
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
