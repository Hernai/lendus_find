<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\Applicant\StoreReferenceRequest;
use App\Http\Requests\V2\Applicant\UpdateAddressRequest;
use App\Http\Requests\V2\Applicant\UpdateBankAccountRequest;
use App\Http\Requests\V2\Applicant\UpdateEmploymentRequest;
use App\Http\Requests\V2\Applicant\UpdateIdentificationsRequest;
use App\Http\Requests\V2\Applicant\UpdatePersonalDataRequest;
use App\Enums\BankAccountType;
use App\Models\PersonReference;
use App\Services\ApplicantProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * V2 Applicant Profile Controller.
 *
 * Manages the authenticated applicant's profile data using the Person model.
 * All endpoints are under /api/v2/applicant/profile
 *
 * Design Principles:
 * - Single Responsibility: Only handles HTTP layer, delegates to service
 * - Dependency Inversion: Depends on ApplicantProfileService abstraction
 * - Clean responses: Consistent JSON structure via ApiResponses trait
 */
class ProfileController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected ApplicantProfileService $profileService
    ) {}

    // =========================================================================
    // Profile Overview
    // =========================================================================

    /**
     * Get complete profile.
     *
     * GET /api/v2/applicant/profile
     */
    public function show(Request $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);
        $profile = $this->profileService->getCompleteProfile($person);

        return $this->success($profile);
    }

    /**
     * Get profile summary (minimal data).
     *
     * GET /api/v2/applicant/profile/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);
        $summary = $this->profileService->getProfileSummary($person);

        return $this->success($summary);
    }

    // =========================================================================
    // Personal Data
    // =========================================================================

    /**
     * Update personal data.
     *
     * PATCH /api/v2/applicant/profile/personal-data
     */
    public function updatePersonalData(UpdatePersonalDataRequest $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);
        $person = $this->profileService->updatePersonalData($person, $request->validated());

        return $this->success([
            'personal_data' => [
                'first_name' => $person->first_name,
                'last_name_1' => $person->last_name_1,
                'last_name_2' => $person->last_name_2,
                'full_name' => $person->full_name,
                'birth_date' => $person->birth_date?->format('Y-m-d'),
                'gender' => $person->gender,
                'marital_status' => $person->marital_status,
                'education_level' => $person->education_level,
            ],
            'profile_completeness' => $person->profile_completeness,
        ], 'Datos personales actualizados');
    }

    // =========================================================================
    // Identifications
    // =========================================================================

    /**
     * Update identifications (CURP, RFC, INE, Passport).
     *
     * PATCH /api/v2/applicant/profile/identifications
     */
    public function updateIdentifications(UpdateIdentificationsRequest $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        try {
            $person = $this->profileService->updateIdentifications($person, $request->validated());
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $message = $e->getMessage();

            if (str_contains(strtolower($message), 'curp')) {
                return $this->validationError('El CURP ya está registrado con otra cuenta', [
                    'curp' => ['Este CURP ya existe en el sistema'],
                ]);
            }

            if (str_contains(strtolower($message), 'rfc')) {
                return $this->validationError('El RFC ya está registrado con otra cuenta', [
                    'rfc' => ['Este RFC ya existe en el sistema'],
                ]);
            }

            throw $e;
        }

        return $this->success([
            'identifications' => [
                'curp' => $person->curp,
                'curp_verified' => $person->currentCurp?->is_verified ?? false,
                'rfc' => $person->rfc,
                'rfc_verified' => $person->currentRfc?->is_verified ?? false,
            ],
            'profile_completeness' => $person->profile_completeness,
        ], 'Identificaciones actualizadas');
    }

    // =========================================================================
    // Address
    // =========================================================================

    /**
     * Get current home address.
     *
     * GET /api/v2/applicant/profile/address
     */
    public function getAddress(Request $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);
        $address = $this->profileService->getAddress($person);

        if (!$address) {
            return $this->success(null, 'No hay dirección registrada');
        }

        return $this->success([
            'id' => $address->id,
            'street' => $address->street,
            'ext_number' => $address->exterior_number,
            'int_number' => $address->interior_number,
            'neighborhood' => $address->neighborhood,
            'municipality' => $address->municipality,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'housing_type' => $address->housing_type,
            'years_at_address' => $address->years_at_address,
            'months_at_address' => $address->months_at_address,
        ]);
    }

    /**
     * Update home address.
     *
     * PUT /api/v2/applicant/profile/address
     */
    public function updateAddress(UpdateAddressRequest $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);
        $address = $this->profileService->updateAddress($person, $request->validated());

        return $this->success([
            'address' => [
                'id' => $address->id,
                'street' => $address->street,
                'ext_number' => $address->exterior_number,
                'neighborhood' => $address->neighborhood,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
            ],
            'profile_completeness' => $person->fresh()->profile_completeness,
        ], 'Dirección actualizada');
    }

    // =========================================================================
    // Employment
    // =========================================================================

    /**
     * Get current employment.
     *
     * GET /api/v2/applicant/profile/employment
     */
    public function getEmployment(Request $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);
        $employment = $this->profileService->getEmployment($person);

        if (!$employment) {
            return $this->success(null, 'No hay empleo registrado');
        }

        return $this->success([
            'id' => $employment->id,
            'employment_type' => $employment->employment_type,
            'company_name' => $employment->employer_name,
            'position' => $employment->job_title,
            'monthly_income' => $employment->monthly_income,
            'seniority_months' => $employment->months_employed ??
                (($employment->years_employed ?? 0) * 12),
            'work_phone' => $employment->employer_phone,
        ]);
    }

    /**
     * Update employment.
     *
     * PUT /api/v2/applicant/profile/employment
     */
    public function updateEmployment(UpdateEmploymentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        \Log::info('Employment update request', [
            'seniority_years' => $validated['seniority_years'] ?? 'NOT_SET',
            'seniority_months' => $validated['seniority_months'] ?? 'NOT_SET',
            'all_data' => $validated
        ]);

        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);
        $employment = $this->profileService->updateEmployment($person, $validated);

        \Log::info('Employment after save', [
            'years_employed' => $employment->years_employed,
            'months_employed' => $employment->months_employed,
        ]);

        return $this->success([
            'employment' => [
                'id' => $employment->id,
                'employment_type' => $employment->employment_type,
                'company_name' => $employment->employer_name,
                'position' => $employment->job_title,
                'monthly_income' => $employment->monthly_income,
                'years_employed' => $employment->years_employed,
                'months_employed' => $employment->months_employed,
                'seniority_months' => ($employment->years_employed ?? 0) * 12 + ($employment->months_employed ?? 0),
                'work_phone' => $employment->employer_phone,
            ],
            'profile_completeness' => $person->fresh()->profile_completeness,
        ], 'Información laboral actualizada');
    }

    // =========================================================================
    // Bank Accounts
    // =========================================================================

    /**
     * List all bank accounts.
     *
     * GET /api/v2/applicant/profile/bank-accounts
     */
    public function listBankAccounts(Request $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);
        $bankAccounts = $person->bankAccounts()->orderByDesc('is_primary')->orderByDesc('created_at')->get();

        return $this->success([
            'bank_accounts' => $bankAccounts->map(fn($ba) => $this->formatBankAccount($ba)),
        ]);
    }

    /**
     * Get primary bank account.
     *
     * GET /api/v2/applicant/profile/bank-account
     */
    public function getBankAccount(Request $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);
        $bankAccount = $this->profileService->getBankAccount($person);

        if (!$bankAccount) {
            return $this->success(null, 'No hay cuenta bancaria registrada');
        }

        return $this->success($this->formatBankAccount($bankAccount));
    }

    /**
     * Create a new bank account.
     *
     * POST /api/v2/applicant/profile/bank-accounts
     */
    public function storeBankAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clabe' => 'required|string|size:18',
            'holder_name' => 'required|string|max:100',
            'account_type' => 'nullable|string',
        ]);

        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        // Normalize account_type using enum (supports both English and Spanish values)
        $accountType = BankAccountType::DEBIT;
        if (!empty($validated['account_type'])) {
            $normalized = BankAccountType::normalize($validated['account_type']);
            if ($normalized === null) {
                return $this->validationError('Tipo de cuenta inválido', [
                    'account_type' => ['El tipo de cuenta no es válido. Valores permitidos: ' . implode(', ', BankAccountType::values())],
                ]);
            }
            $accountType = $normalized;
        }

        // Validate CLABE
        $clabeValidation = $this->profileService->validateClabe($validated['clabe']);
        if (!$clabeValidation['is_valid']) {
            return $this->validationError('La CLABE no es válida', [
                'clabe' => ['El dígito verificador de la CLABE es incorrecto'],
            ]);
        }

        // Check if CLABE already exists for this person
        $existing = $person->bankAccounts()->where('clabe', $validated['clabe'])->first();
        if ($existing) {
            return $this->badRequest('DUPLICATE_CLABE', 'Ya tienes una cuenta con esta CLABE registrada.');
        }

        // Create bank account
        $isFirst = $person->bankAccounts()->count() === 0;
        $bankAccount = $person->bankAccounts()->create([
            'tenant_id' => $account->tenant_id,
            'owner_type' => 'persons',
            'bank_code' => $clabeValidation['bank_code'],
            'bank_name' => $clabeValidation['bank_name'],
            'clabe' => $validated['clabe'],
            'holder_name' => strtoupper($validated['holder_name']),
            'account_type' => $accountType->value,
            'is_primary' => $isFirst, // First account is automatically primary
            'status' => 'ACTIVE',
        ]);

        return $this->created([
            'bank_account' => $this->formatBankAccount($bankAccount),
        ], 'Cuenta bancaria creada');
    }

    /**
     * Update bank account.
     *
     * PUT /api/v2/applicant/profile/bank-account
     */
    public function updateBankAccount(UpdateBankAccountRequest $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        // Validate CLABE first
        $clabeValidation = $this->profileService->validateClabe($request->clabe);
        if (!$clabeValidation['is_valid']) {
            return $this->validationError('La CLABE no es válida', [
                'clabe' => ['El dígito verificador de la CLABE es incorrecto'],
            ]);
        }

        $bankAccount = $this->profileService->updateBankAccount($person, $request->validated());

        return $this->success([
            'bank_account' => $this->formatBankAccount($bankAccount),
        ], 'Cuenta bancaria actualizada');
    }

    /**
     * Set bank account as primary.
     *
     * PATCH /api/v2/applicant/profile/bank-accounts/{id}/primary
     */
    public function setPrimaryBankAccount(Request $request, string $id): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        $bankAccount = $person->bankAccounts()->where('id', $id)->first();
        if (!$bankAccount) {
            return $this->notFound('Cuenta bancaria no encontrada.');
        }

        // Unset all other primary accounts
        $person->bankAccounts()->update(['is_primary' => false]);

        // Set this one as primary
        $bankAccount->update(['is_primary' => true]);

        return $this->success([
            'bank_account' => $this->formatBankAccount($bankAccount->fresh()),
        ], 'Cuenta establecida como principal');
    }

    /**
     * Delete a bank account.
     *
     * DELETE /api/v2/applicant/profile/bank-accounts/{id}
     */
    public function deleteBankAccount(Request $request, string $id): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        $bankAccount = $person->bankAccounts()->where('id', $id)->first();
        if (!$bankAccount) {
            return $this->notFound('Cuenta bancaria no encontrada.');
        }

        if ($bankAccount->is_verified) {
            return $this->badRequest('VERIFIED_ACCOUNT', 'No puedes eliminar una cuenta verificada.');
        }

        $wasPrimary = $bankAccount->is_primary;
        $bankAccount->delete();

        // If deleted account was primary, set another one as primary
        if ($wasPrimary) {
            $newPrimary = $person->bankAccounts()->first();
            if ($newPrimary) {
                $newPrimary->update(['is_primary' => true]);
            }
        }

        return $this->success(null, 'Cuenta bancaria eliminada');
    }

    /**
     * Format bank account for response.
     */
    private function formatBankAccount($bankAccount): array
    {
        return [
            'id' => $bankAccount->id,
            'bank_name' => $bankAccount->bank_name,
            'clabe' => $bankAccount->clabe,
            'clabe_masked' => substr($bankAccount->clabe, 0, 4) . '****' . substr($bankAccount->clabe, -4),
            'holder_name' => $bankAccount->holder_name,
            'account_type' => $bankAccount->account_type,
            'is_primary' => (bool) $bankAccount->is_primary,
            'is_verified' => (bool) ($bankAccount->is_verified ?? false),
            'status' => $bankAccount->status ?? 'ACTIVE',
            'created_at' => $bankAccount->created_at?->toIso8601String(),
        ];
    }

    /**
     * Validate a CLABE number.
     *
     * POST /api/v2/applicant/profile/validate-clabe
     */
    public function validateClabe(Request $request): JsonResponse
    {
        $request->validate([
            'clabe' => 'required|string|size:18',
        ]);

        $result = $this->profileService->validateClabe($request->clabe);

        return $this->success($result);
    }

    // =========================================================================
    // References
    // =========================================================================

    /**
     * List all references.
     *
     * GET /api/v2/applicant/profile/references
     */
    public function listReferences(Request $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);
        $references = $this->profileService->getReferences($person);

        return $this->success([
            'references' => $references->map(fn($ref) => [
                'id' => $ref->id,
                'type' => $ref->type,
                'first_name' => $ref->first_name,
                'last_name_1' => $ref->last_name_1,
                'last_name_2' => $ref->last_name_2,
                'full_name' => trim("{$ref->first_name} {$ref->last_name_1} {$ref->last_name_2}"),
                'phone' => $ref->phone,
                'relationship' => $ref->relationship,
                'years_known' => $ref->years_known,
                'verification_status' => $ref->verification_status ?? 'PENDING',
            ]),
            'meta' => [
                'total' => $references->count(),
                'personal_count' => $references->where('type', 'PERSONAL')->count(),
                'work_count' => $references->where('type', 'WORK')->count(),
            ],
        ]);
    }

    /**
     * Add a reference.
     *
     * POST /api/v2/applicant/profile/references
     */
    public function storeReference(StoreReferenceRequest $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);
        $reference = $this->profileService->addReference($person, $request->validated());

        return $this->created([
            'id' => $reference->id,
            'type' => $reference->type,
            'full_name' => trim("{$reference->first_name} {$reference->last_name_1}"),
            'phone' => $reference->phone,
            'relationship' => $reference->relationship,
        ], 'Referencia agregada');
    }

    /**
     * Update a reference.
     *
     * PUT /api/v2/applicant/profile/references/{reference}
     */
    public function updateReference(Request $request, PersonReference $reference): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        // Verify ownership
        if ($reference->person_id !== $person->id) {
            return $this->notFound('Referencia no encontrada');
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name_1' => 'sometimes|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
            'phone' => 'sometimes|string|max:15',
            'email' => 'nullable|email|max:100',
            'relationship' => 'sometimes|string|max:50',
            'years_known' => 'nullable|integer|min:0|max:100',
        ]);

        $reference = $this->profileService->updateReference($reference, $validated);

        return $this->success([
            'id' => $reference->id,
            'full_name' => trim("{$reference->first_name} {$reference->last_name_1}"),
            'phone' => $reference->phone,
        ], 'Referencia actualizada');
    }

    /**
     * Delete a reference.
     *
     * DELETE /api/v2/applicant/profile/references/{reference}
     */
    public function deleteReference(Request $request, PersonReference $reference): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        // Verify ownership
        if ($reference->person_id !== $person->id) {
            return $this->notFound('Referencia no encontrada');
        }

        $this->profileService->deleteReference($reference);

        return $this->success(null, 'Referencia eliminada');
    }

    // =========================================================================
    // Signature
    // =========================================================================

    /**
     * Save signature.
     *
     * POST /api/v2/applicant/profile/signature
     */
    public function saveSignature(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'signature' => 'required|string', // Base64 PNG
        ]);

        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        // Decode base64 and save as file
        $base64Data = $validated['signature'];

        // Remove data:image/png;base64, prefix if present
        if (str_contains($base64Data, ',')) {
            $base64Data = explode(',', $base64Data)[1];
        }

        $imageData = base64_decode($base64Data);
        if (!$imageData) {
            return $this->validationError(['signature' => 'Firma inválida']);
        }

        // Generate unique filename
        $filename = 'signature_' . $person->id . '_' . now()->format('YmdHis') . '.png';
        $path = "documents/{$account->tenant_id}/signatures/{$filename}";

        // Store file
        $disk = config('filesystems.default', 'local');
        \Illuminate\Support\Facades\Storage::disk($disk)->put($path, $imageData);

        // Mark any existing signature documents as replaced
        \App\Models\DocumentV2::where('documentable_type', \App\Models\Person::class)
            ->where('documentable_id', $person->id)
            ->where('type', 'SIGNATURE')
            ->whereNull('replaced_at')
            ->update(['replaced_at' => now()]);

        // Create document record
        $document = \App\Models\DocumentV2::create([
            'tenant_id' => $account->tenant_id,
            'documentable_type' => \App\Models\Person::class,
            'documentable_id' => $person->id,
            'type' => 'SIGNATURE',
            'category' => 'LEGAL',
            'file_name' => $filename,
            'file_path' => $path,
            'mime_type' => 'image/png',
            'file_size' => strlen($imageData),
            'storage_disk' => $disk,
            'status' => 'APPROVED', // Signatures are auto-approved
            'uploaded_by' => $account->id,
            'ocr_data' => [
                'signed_at' => now()->toIso8601String(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);

        // Also update person record for backward compatibility
        $person->update([
            'signature_date' => now(),
            'signature_ip' => $request->ip(),
        ]);

        return $this->success([
            'signed_at' => now()->toIso8601String(),
            'document_id' => $document->id,
        ], 'Firma guardada');
    }
}
