<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Requests\V2\Applicant\StoreReferenceRequest;
use App\Http\Requests\V2\Applicant\UpdateAddressRequest;
use App\Http\Requests\V2\Applicant\UpdateBankAccountRequest;
use App\Http\Requests\V2\Applicant\UpdateEmploymentRequest;
use App\Http\Requests\V2\Applicant\UpdateIdentificationsRequest;
use App\Http\Requests\V2\Applicant\UpdatePersonalDataRequest;
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
 * - Clean responses: Consistent JSON structure
 */
class ProfileController extends Controller
{
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

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
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

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
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

        return response()->json([
            'success' => true,
            'message' => 'Datos personales actualizados',
            'data' => [
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
            ],
        ]);
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
                return response()->json([
                    'success' => false,
                    'message' => 'El CURP ya está registrado con otra cuenta',
                    'errors' => ['curp' => ['Este CURP ya existe en el sistema']],
                ], 422);
            }

            if (str_contains(strtolower($message), 'rfc')) {
                return response()->json([
                    'success' => false,
                    'message' => 'El RFC ya está registrado con otra cuenta',
                    'errors' => ['rfc' => ['Este RFC ya existe en el sistema']],
                ], 422);
            }

            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Identificaciones actualizadas',
            'data' => [
                'identifications' => [
                    'curp' => $person->curp,
                    'curp_verified' => $person->currentCurp?->is_verified ?? false,
                    'rfc' => $person->rfc,
                    'rfc_verified' => $person->currentRfc?->is_verified ?? false,
                ],
                'profile_completeness' => $person->profile_completeness,
            ],
        ]);
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
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No hay dirección registrada',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
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
            ],
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

        return response()->json([
            'success' => true,
            'message' => 'Dirección actualizada',
            'data' => [
                'address' => [
                    'id' => $address->id,
                    'street' => $address->street,
                    'ext_number' => $address->exterior_number,
                    'neighborhood' => $address->neighborhood,
                    'state' => $address->state,
                    'postal_code' => $address->postal_code,
                ],
                'profile_completeness' => $person->fresh()->profile_completeness,
            ],
        ]);
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
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No hay empleo registrado',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $employment->id,
                'employment_type' => $employment->employment_type,
                'company_name' => $employment->employer_name,
                'position' => $employment->job_title,
                'monthly_income' => $employment->monthly_income,
                'seniority_months' => $employment->months_employed ??
                    (($employment->years_employed ?? 0) * 12),
                'work_phone' => $employment->employer_phone,
            ],
        ]);
    }

    /**
     * Update employment.
     *
     * PUT /api/v2/applicant/profile/employment
     */
    public function updateEmployment(UpdateEmploymentRequest $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);
        $employment = $this->profileService->updateEmployment($person, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Información laboral actualizada',
            'data' => [
                'employment' => [
                    'id' => $employment->id,
                    'employment_type' => $employment->employment_type,
                    'company_name' => $employment->employer_name,
                    'monthly_income' => $employment->monthly_income,
                ],
                'profile_completeness' => $person->fresh()->profile_completeness,
            ],
        ]);
    }

    // =========================================================================
    // Bank Account
    // =========================================================================

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
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No hay cuenta bancaria registrada',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $bankAccount->id,
                'bank_name' => $bankAccount->bank_name,
                'clabe' => $bankAccount->clabe,
                'clabe_masked' => substr($bankAccount->clabe, 0, 4) . '****' . substr($bankAccount->clabe, -4),
                'holder_name' => $bankAccount->holder_name,
                'account_type' => $bankAccount->account_type,
                'is_verified' => $bankAccount->is_verified ?? false,
            ],
        ]);
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
            return response()->json([
                'success' => false,
                'message' => 'La CLABE no es válida',
                'errors' => ['clabe' => ['El dígito verificador de la CLABE es incorrecto']],
            ], 422);
        }

        $bankAccount = $this->profileService->updateBankAccount($person, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cuenta bancaria actualizada',
            'data' => [
                'bank_account' => [
                    'id' => $bankAccount->id,
                    'bank_name' => $bankAccount->bank_name,
                    'clabe_masked' => substr($bankAccount->clabe, 0, 4) . '****' . substr($bankAccount->clabe, -4),
                ],
            ],
        ]);
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

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
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

        return response()->json([
            'success' => true,
            'data' => $references->map(fn($ref) => [
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

        return response()->json([
            'success' => true,
            'message' => 'Referencia agregada',
            'data' => [
                'id' => $reference->id,
                'type' => $reference->type,
                'full_name' => trim("{$reference->first_name} {$reference->last_name_1}"),
                'phone' => $reference->phone,
                'relationship' => $reference->relationship,
            ],
        ], 201);
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
            return response()->json([
                'success' => false,
                'message' => 'Referencia no encontrada',
            ], 404);
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

        return response()->json([
            'success' => true,
            'message' => 'Referencia actualizada',
            'data' => [
                'id' => $reference->id,
                'full_name' => trim("{$reference->first_name} {$reference->last_name_1}"),
                'phone' => $reference->phone,
            ],
        ]);
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
            return response()->json([
                'success' => false,
                'message' => 'Referencia no encontrada',
            ], 404);
        }

        $this->profileService->deleteReference($reference);

        return response()->json([
            'success' => true,
            'message' => 'Referencia eliminada',
        ]);
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

        // Store signature on person (or related table)
        $person->update([
            'signature_base64' => $validated['signature'],
            'signature_date' => now(),
            'signature_ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Firma guardada',
            'data' => [
                'signed_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
