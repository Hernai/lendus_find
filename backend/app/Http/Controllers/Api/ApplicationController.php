<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Product;
use App\Models\Reference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
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
            ->with(['product:id,name,type'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $applications->map(fn($app) => $this->formatApplication($app)),
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
            'payment_frequency' => 'required|in:WEEKLY,BIWEEKLY,QUINCENAL,MONTHLY,MENSUAL',
            'purpose' => 'nullable|string|max:50',  // Optional at creation, required at submission
            'purpose_description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
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
                'message' => 'Product not found or not available'
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

        // Calculate loan terms
        $frequency = $request->payment_frequency;
        $periodsPerYear = match ($frequency) {
            'WEEKLY' => 52,
            'BIWEEKLY', 'QUINCENAL' => 24,
            default => 12,
        };

        $totalPeriods = match ($frequency) {
            'WEEKLY' => $request->term_months * 4.33,
            'BIWEEKLY', 'QUINCENAL' => $request->term_months * 2,
            default => $request->term_months,
        };
        $totalPeriods = (int) round($totalPeriods);

        $interestRate = $product->rules['interest_rate'] ?? $product->rules['annual_rate'] ?? 0;
        $periodRate = ($interestRate / 100) / $periodsPerYear;

        // Calculate payment using French amortization
        if ($periodRate > 0) {
            $payment = $request->requested_amount *
                ($periodRate * pow(1 + $periodRate, $totalPeriods)) /
                (pow(1 + $periodRate, $totalPeriods) - 1);
        } else {
            $payment = $request->requested_amount / $totalPeriods;
        }

        $totalToPay = $payment * $totalPeriods;

        // Calculate opening commission
        $openingCommissionRate = $product->rules['opening_commission'] ?? 0;
        $openingCommission = $request->requested_amount * ($openingCommissionRate / 100);

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
            'status' => Application::STATUS_DRAFT,
            'status_history' => [[
                'from' => null,
                'to' => Application::STATUS_DRAFT,
                'reason' => 'Application created',
                'timestamp' => now()->toIso8601String(),
            ]],
        ]);

        return response()->json([
            'message' => 'Application created',
            'data' => $this->formatApplication($application->load('product'))
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
                'message' => 'Application not found'
            ], 404);
        }

        $application->load(['product', 'documents', 'references']);

        return response()->json([
            'data' => $this->formatApplicationDetailed($application)
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
                'message' => 'Application not found'
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
            'payment_frequency' => 'sometimes|in:WEEKLY,BIWEEKLY,QUINCENAL,MONTHLY,MENSUAL',
            'purpose' => 'sometimes|string|max:50',
            'purpose_description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // If amount or terms changed, recalculate
        if ($request->has('requested_amount') || $request->has('term_months') || $request->has('payment_frequency')) {
            $amount = $request->input('requested_amount', $application->requested_amount);
            $termMonths = $request->input('term_months', $application->term_months);
            $frequency = $request->input('payment_frequency', $application->payment_frequency);

            $periodsPerYear = match ($frequency) {
                'WEEKLY' => 52,
                'BIWEEKLY', 'QUINCENAL' => 24,
                default => 12,
            };

            $totalPeriods = match ($frequency) {
                'WEEKLY' => $termMonths * 4.33,
                'BIWEEKLY', 'QUINCENAL' => $termMonths * 2,
                default => $termMonths,
            };
            $totalPeriods = (int) round($totalPeriods);

            $periodRate = ($application->interest_rate / 100) / $periodsPerYear;

            if ($periodRate > 0) {
                $payment = $amount *
                    ($periodRate * pow(1 + $periodRate, $totalPeriods)) /
                    (pow(1 + $periodRate, $totalPeriods) - 1);
            } else {
                $payment = $amount / $totalPeriods;
            }

            $application->requested_amount = $amount;
            $application->term_months = $termMonths;
            $application->payment_frequency = $frequency;
            $application->monthly_payment = round($payment, 2);
            $application->total_to_pay = round($payment * $totalPeriods, 2);
        }

        if ($request->has('purpose')) {
            $application->purpose = $request->purpose;
        }
        if ($request->has('purpose_description')) {
            $application->purpose_description = $request->purpose_description;
        }

        $application->save();

        return response()->json([
            'message' => 'Application updated',
            'data' => $this->formatApplication($application->fresh()->load('product'))
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
                'message' => 'Application not found'
            ], 404);
        }

        if ($application->status !== Application::STATUS_DRAFT &&
            $application->status !== Application::STATUS_DOCS_PENDING) {
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
        $requiredDocs = $application->product->required_documents ?? [];
        $uploadedDocTypes = $application->documents()->pluck('type')->toArray();
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

        $application->changeStatus(Application::STATUS_SUBMITTED, 'Application submitted by applicant', $request->user()->id);

        return response()->json([
            'message' => 'Application submitted successfully',
            'data' => $this->formatApplication($application->fresh()->load('product'))
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
                'message' => 'Application not found'
            ], 404);
        }

        $cancelableStatuses = [
            Application::STATUS_DRAFT,
            Application::STATUS_SUBMITTED,
            Application::STATUS_IN_REVIEW,
            Application::STATUS_DOCS_PENDING,
            Application::STATUS_COUNTER_OFFERED,
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
            Application::STATUS_CANCELLED,
            $request->input('reason', 'Cancelled by applicant'),
            $request->user()->id
        );

        return response()->json([
            'message' => 'Application cancelled',
            'data' => $this->formatApplication($application->fresh()->load('product'))
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
                'message' => 'Application not found'
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
                'message' => 'Application not found'
            ], 404);
        }

        if (!$application->isEditable() && $application->status !== Application::STATUS_SUBMITTED) {
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
                'message' => 'Validation error',
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
            'type' => Reference::TYPE_PERSONAL,
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

    /**
     * Format application for list response.
     */
    private function formatApplication(Application $application): array
    {
        return [
            'id' => $application->id,
            'folio' => $application->folio,
            'status' => $application->status,
            'product' => $application->product ? [
                'id' => $application->product->id,
                'name' => $application->product->name,
                'type' => $application->product->type,
            ] : null,
            'requested_amount' => (float) $application->requested_amount,
            'approved_amount' => $application->approved_amount ? (float) $application->approved_amount : null,
            'term_months' => $application->term_months,
            'payment_frequency' => $application->payment_frequency,
            'interest_rate' => (float) $application->interest_rate,
            'monthly_payment' => (float) $application->monthly_payment,
            'total_to_pay' => (float) $application->total_to_pay,
            'purpose' => $application->purpose,
            'created_at' => $application->created_at->toIso8601String(),
            'updated_at' => $application->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format application with full details.
     */
    private function formatApplicationDetailed(Application $application): array
    {
        $data = $this->formatApplication($application);

        $data['purpose_description'] = $application->purpose_description;
        $data['opening_commission'] = (float) $application->opening_commission;
        $data['rejection_reason'] = $application->rejection_reason;
        $data['assigned_to'] = $application->assignedAgent?->name;

        // Documents
        $data['documents'] = $application->documents->map(fn($doc) => [
            'id' => $doc->id,
            'type' => $doc->type,
            'name' => $doc->original_name,
            'status' => $doc->status,
            'rejection_reason' => $doc->rejection_reason,
            'uploaded_at' => $doc->created_at->toIso8601String(),
        ]);

        // Pending documents (required but not uploaded)
        $requiredDocs = $application->product->required_docs ?? $application->product->required_documents ?? [];
        $uploadedTypes = $application->documents->pluck('type')->toArray();
        $data['pending_documents'] = collect($requiredDocs)
            ->filter(fn($type) => !in_array($type, $uploadedTypes))
            ->map(fn($type) => [
                'type' => $type,
                'label' => $this->getDocumentLabel($type),
                'description' => $this->getDocumentDescription($type),
                'required' => true,
            ])
            ->values();

        // References
        $data['references'] = $application->references->map(fn($ref) => [
            'id' => $ref->id,
            'full_name' => $ref->full_name,
            'relationship' => $ref->relationship,
            'phone' => $ref->phone,
            'verified' => $ref->is_verified,
        ]);

        // Status history (simplified for applicant)
        $data['status_history'] = collect($application->status_history ?? [])->map(fn($h) => [
            'status' => $h['to'],
            'timestamp' => $h['timestamp'],
        ]);

        return $data;
    }

    /**
     * Get human-readable label for a document type.
     */
    private function getDocumentLabel(string $type): string
    {
        return match ($type) {
            'INE_FRONT' => 'INE (Frente)',
            'INE_BACK' => 'INE (Reverso)',
            'PROOF_ADDRESS' => 'Comprobante de domicilio',
            'PROOF_INCOME' => 'Comprobante de ingresos',
            'PAYSLIP_1' => 'Recibo de nómina 1',
            'PAYSLIP_2' => 'Recibo de nómina 2',
            'PAYSLIP_3' => 'Recibo de nómina 3',
            'BANK_STATEMENT' => 'Estado de cuenta bancario',
            'VEHICLE_INVOICE' => 'Factura del vehículo',
            'RFC_CONSTANCY' => 'Constancia de RFC',
            'CURP' => 'CURP',
            'SELFIE' => 'Selfie con INE',
            default => ucwords(str_replace('_', ' ', strtolower($type))),
        };
    }

    /**
     * Get description for a document type.
     */
    private function getDocumentDescription(string $type): string
    {
        return match ($type) {
            'INE_FRONT' => 'Foto clara del frente de tu INE/IFE vigente',
            'INE_BACK' => 'Foto clara del reverso de tu INE/IFE vigente',
            'PROOF_ADDRESS' => 'Recibo de luz, agua, teléfono o estado de cuenta bancario (no mayor a 3 meses)',
            'PROOF_INCOME' => 'Recibo de nómina, declaración de impuestos o constancia de ingresos',
            'PAYSLIP_1' => 'Recibo de nómina del mes actual',
            'PAYSLIP_2' => 'Recibo de nómina del mes anterior',
            'PAYSLIP_3' => 'Recibo de nómina de hace 2 meses',
            'BANK_STATEMENT' => 'Estado de cuenta bancario de los últimos 3 meses',
            'VEHICLE_INVOICE' => 'Factura original del vehículo a arrendar',
            'RFC_CONSTANCY' => 'Constancia de situación fiscal del SAT',
            'CURP' => 'Clave Única de Registro de Población',
            'SELFIE' => 'Foto de tu rostro sosteniendo tu INE junto a tu cara',
            default => 'Documento requerido para tu solicitud',
        };
    }
}
