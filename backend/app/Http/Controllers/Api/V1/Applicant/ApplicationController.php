<?php

namespace App\Http\Controllers\Api\V1\Applicant;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\PaymentFrequency;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Reference;
use App\Services\LoanCalculationService;
use App\Transformers\ApplicationTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    public function __construct(
        protected LoanCalculationService $loanCalculator,
        protected ApplicationTransformer $transformer
    ) {}
    /**
     * List all applications for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return response()->json([
                'data' => [],
                'meta' => ['total' => 0]
            ]);
        }

        $applications = $applicant->applications()
            ->with(['product', 'documents'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $applications->map(fn($app) => $this->transformer->toArrayWithPending($app)),
            'meta' => ['total' => $applications->count()]
        ]);
    }

    /**
     * Create a new loan application.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $applicant = $user->applicant;

        if (!$applicant) {
            return response()->json([
                'message' => 'You must complete your profile before applying'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|uuid',
            'requested_amount' => 'required|numeric|min:1000',
            'term_months' => 'required|integer|min:1|max:120',
            'payment_frequency' => ['required', \Illuminate\Validation\Rule::in(PaymentFrequency::values())],
            'purpose' => 'nullable|string|max:50',  // Optional at creation, required at submission
            'purpose_description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find product
        $tenant = $request->attributes->get('tenant');
        $product = Product::where('id', $request->product_id)
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'Producto no encontrado o no disponible'
            ], 404);
        }

        // Validate amount against product limits
        if ($request->requested_amount < $product->min_amount ||
            $request->requested_amount > $product->max_amount) {
            return response()->json([
                'message' => 'Requested amount is outside product limits',
                'errors' => [
                    'requested_amount' => [
                        "Amount must be between {$product->min_amount} and {$product->max_amount}"
                    ]
                ]
            ], 422);
        }

        // Calculate loan terms using centralized service
        $frequency = $request->payment_frequency;
        $interestRate = $product->annual_rate;
        $openingCommissionRate = $product->opening_commission_rate;

        $totalPeriods = $this->loanCalculator->calculateTotalPeriods($request->term_months, $frequency);
        $payment = $this->loanCalculator->calculatePayment(
            $request->requested_amount,
            $interestRate,
            $request->term_months,
            $frequency
        );
        $totalToPay = $this->loanCalculator->calculateTotalToPay($payment, $totalPeriods);
        $openingCommission = $this->loanCalculator->calculateOpeningCommission(
            $request->requested_amount,
            $openingCommissionRate
        );

        // Create application
        $application = Application::create([
            'tenant_id' => $tenant->id,
            'applicant_id' => $applicant->id,
            'product_id' => $product->id,
            'requested_amount' => $request->requested_amount,
            'term_months' => $request->term_months,
            'payment_frequency' => $frequency,
            'interest_rate' => $interestRate,
            'opening_commission' => $openingCommission,
            'monthly_payment' => round($payment, 2),
            'total_to_pay' => round($totalToPay, 2),
            'purpose' => $request->purpose,
            'purpose_description' => $request->purpose_description,
            'status' => ApplicationStatus::DRAFT->value,
            'status_history' => [[
                'from' => null,
                'to' => ApplicationStatus::DRAFT->value,
                'reason' => 'Application created',
                'timestamp' => now()->toIso8601String(),
            ]],
        ]);

        // Log application creation
        $metadata = $request->attributes->get('metadata', []);
        AuditLog::log(
            AuditAction::APPLICATION_CREATED->value,
            null,
            array_merge($metadata, [
                'user_id' => $user->id,
                'applicant_id' => $applicant->id,
                'application_id' => $application->id,
            ])
        );

        return response()->json([
            'message' => 'Solicitud creada',
            'data' => $this->transformer->toArray($application->load('product'))
        ], 201);
    }

    /**
     * Get a specific application.
     */
    public function show(Request $request, Application $application): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $application->applicant_id !== $applicant->id) {
            return response()->json([
                'message' => 'Solicitud no encontrada'
            ], 404);
        }

        $application->load(['product', 'documents', 'references']);

        return response()->json([
            'data' => $this->transformer->toDetailedArray($application)
        ]);
    }

    /**
     * Update an application (only in DRAFT or DOCS_PENDING status).
     */
    public function update(Request $request, Application $application): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $application->applicant_id !== $applicant->id) {
            return response()->json([
                'message' => 'Solicitud no encontrada'
            ], 404);
        }

        if (!$application->isEditable()) {
            return response()->json([
                'message' => 'Application cannot be modified in current status'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'requested_amount' => 'sometimes|numeric|min:1000',
            'term_months' => 'sometimes|integer|min:1|max:120',
            'payment_frequency' => ['sometimes', \Illuminate\Validation\Rule::in(PaymentFrequency::values())],
            'purpose' => 'sometimes|string|max:50',
            'purpose_description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // If amount or terms changed, recalculate using centralized service
        if ($request->has('requested_amount') || $request->has('term_months') || $request->has('payment_frequency')) {
            $amount = $request->input('requested_amount', $application->requested_amount);
            $termMonths = $request->input('term_months', $application->term_months);
            $frequency = $request->input('payment_frequency', $application->payment_frequency);

            $totalPeriods = $this->loanCalculator->calculateTotalPeriods($termMonths, $frequency);
            $payment = $this->loanCalculator->calculatePayment(
                $amount,
                $application->interest_rate,
                $termMonths,
                $frequency
            );

            $application->requested_amount = $amount;
            $application->term_months = $termMonths;
            $application->payment_frequency = $frequency;
            $application->monthly_payment = $payment;
            $application->total_to_pay = $this->loanCalculator->calculateTotalToPay($payment, $totalPeriods);
        }

        if ($request->has('purpose')) {
            $application->purpose = $request->purpose;
        }
        if ($request->has('purpose_description')) {
            $application->purpose_description = $request->purpose_description;
        }

        $application->save();

        // Log application update
        $metadata = $request->attributes->get('metadata', []);
        AuditLog::log(
            AuditAction::APPLICATION_UPDATED->value,
            null,
            array_merge($metadata, [
                'user_id' => $request->user()->id,
                'applicant_id' => $applicant->id,
                'application_id' => $application->id,
            ])
        );

        return response()->json([
            'message' => 'Solicitud actualizada',
            'data' => $this->transformer->toArray($application->fresh()->load('product'))
        ]);
    }

    /**
     * Submit an application for review.
     */
    public function submit(Request $request, Application $application): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $application->applicant_id !== $applicant->id) {
            return response()->json([
                'message' => 'Solicitud no encontrada'
            ], 404);
        }

        if ($application->status !== ApplicationStatus::DRAFT &&
            $application->status !== ApplicationStatus::DOCS_PENDING) {
            return response()->json([
                'message' => 'Application cannot be submitted in current status'
            ], 400);
        }

        // Validate applicant has required data using model helper methods
        $errors = [];
        if (!$applicant->hasCompletePersonalData()) {
            $errors['personal_data'] = ['Personal data is required (name, birth date, gender, CURP)'];
        }
        if (!$applicant->hasAddress()) {
            $errors['address'] = ['Address is required'];
        }
        if (!$applicant->hasEmployment()) {
            $errors['employment'] = ['Employment info is required'];
        }
        if (!$applicant->hasSigned()) {
            $errors['signature'] = ['Signature is required'];
        }

        // Validate application has purpose
        if (empty($application->purpose)) {
            $errors['purpose'] = ['Loan purpose is required'];
        }

        // Validate required documents
        // Normalize document types to handle aliases (RFC = RFC_CONSTANCIA)
        $normalizeDocType = fn($type) => match($type) {
            'RFC' => 'RFC_CONSTANCIA',
            default => $type,
        };

        $requiredDocs = array_map($normalizeDocType, $application->product->required_documents ?? []);
        $uploadedDocTypes = $application->documents()->pluck('type')
            ->map(fn($t) => $t instanceof \App\Enums\DocumentType ? $t->value : $t)
            ->map($normalizeDocType)
            ->toArray();
        $missingDocs = array_diff($requiredDocs, $uploadedDocTypes);

        if (!empty($missingDocs)) {
            $errors['documents'] = ['Missing required documents: ' . implode(', ', $missingDocs)];
        }

        // Validate references (minimum 2)
        $referencesCount = $application->references()->count();
        if ($referencesCount < 2) {
            $errors['references'] = ["At least 2 references required, only {$referencesCount} provided"];
        }

        if (!empty($errors)) {
            return response()->json([
                'message' => 'Application is incomplete',
                'errors' => $errors
            ], 422);
        }

        $application->changeStatus(ApplicationStatus::SUBMITTED->value, 'Application submitted by applicant', $request->user()->id);

        // Log application submission
        $metadata = $request->attributes->get('metadata', []);
        AuditLog::log(
            AuditAction::APPLICATION_SUBMITTED->value,
            null,
            array_merge($metadata, [
                'user_id' => $request->user()->id,
                'applicant_id' => $applicant->id,
                'application_id' => $application->id,
            ])
        );

        return response()->json([
            'message' => 'Solicitud enviada exitosamente',
            'data' => $this->transformer->toArray($application->fresh()->load('product'))
        ]);
    }

    /**
     * Cancel an application.
     */
    public function cancel(Request $request, Application $application): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $application->applicant_id !== $applicant->id) {
            return response()->json([
                'message' => 'Solicitud no encontrada'
            ], 404);
        }

        $cancelableStatuses = [
            ApplicationStatus::DRAFT,
            ApplicationStatus::SUBMITTED,
            ApplicationStatus::IN_REVIEW,
            ApplicationStatus::DOCS_PENDING,
            ApplicationStatus::COUNTER_OFFERED,
        ];

        if (!in_array($application->status, $cancelableStatuses)) {
            return response()->json([
                'message' => 'Application cannot be cancelled in current status'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        $application->changeStatus(
            ApplicationStatus::CANCELLED->value,
            $request->input('reason', 'Cancelled by applicant'),
            $request->user()->id
        );

        return response()->json([
            'message' => 'Solicitud cancelada',
            'data' => $this->transformer->toArray($application->fresh()->load('product'))
        ]);
    }

    /**
     * Get references for an application.
     */
    public function references(Request $request, Application $application): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $application->applicant_id !== $applicant->id) {
            return response()->json([
                'message' => 'Solicitud no encontrada'
            ], 404);
        }

        $references = $application->references()->get();

        return response()->json([
            'data' => $references->map(fn($ref) => [
                'id' => $ref->id,
                'full_name' => $ref->full_name,
                'relationship' => $ref->relationship,
                'phone' => $ref->phone,
                'verified' => $ref->is_verified,
                'created_at' => $ref->created_at->toIso8601String(),
            ])
        ]);
    }

    /**
     * Add a reference to an application.
     */
    public function storeReference(Request $request, Application $application): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant || $application->applicant_id !== $applicant->id) {
            return response()->json([
                'message' => 'Solicitud no encontrada'
            ], 404);
        }

        if (!$application->isEditable() && $application->status !== ApplicationStatus::SUBMITTED) {
            return response()->json([
                'message' => 'Cannot add references in current status'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:200',
            'relationship' => 'required|string|max:50',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string|max:300',
            'years_known' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check max references (typically 3)
        $maxReferences = 3;
        if ($application->references()->count() >= $maxReferences) {
            return response()->json([
                'message' => "Maximum {$maxReferences} references allowed"
            ], 400);
        }

        // Parse full_name into parts if not provided separately
        $fullName = strtoupper(trim($request->full_name));
        $nameParts = preg_split('/\s+/', $fullName, 3);
        $firstName = $nameParts[0] ?? '';
        $lastName1 = $nameParts[1] ?? '';
        $lastName2 = $nameParts[2] ?? '';

        $reference = Reference::create([
            'applicant_id' => $applicant->id,
            'application_id' => $application->id,
            'first_name' => $firstName,
            'last_name_1' => $lastName1,
            'last_name_2' => $lastName2,
            'full_name' => $fullName,
            'relationship' => $request->relationship,
            'phone' => $request->phone,
            'email' => $request->email,
            'type' => 'PERSONAL',
            'is_verified' => false,
        ]);

        return response()->json([
            'message' => 'Reference added',
            'data' => [
                'id' => $reference->id,
                'full_name' => $reference->full_name,
                'relationship' => $reference->relationship,
                'phone' => $reference->phone,
                'verified' => false,
            ]
        ], 201);
    }

}
