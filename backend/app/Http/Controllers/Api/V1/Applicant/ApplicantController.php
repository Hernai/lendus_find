<?php

namespace App\Http\Controllers\Api\V1\Applicant;

use App\Enums\ApplicantType;
use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\KycStatus;
use App\Enums\MaritalStatus;
use App\Enums\VerificationMethod;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApplicantHelpers;
use App\Http\Traits\ValidationHelpers;
use App\Http\Controllers\Api\V1\Applicant\AddressController;
use App\Http\Controllers\Api\V1\Applicant\BankAccountController;
use App\Http\Controllers\Api\V1\Applicant\EmploymentController;
use App\Http\Requests\UpdatePersonalDataRequest;
use App\Models\Applicant;
use App\Services\VerificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Controller for applicant profile management.
 *
 * Single Responsibility: Handle applicant profile CRUD and personal data.
 *
 * Related resources are managed by specialized controllers:
 * - AddressController: Address management
 * - EmploymentController: Employment records
 * - BankAccountController: Bank accounts
 *
 * Note: Deprecated delegation methods resolve controllers from container
 * to avoid violating Liskov Substitution Principle (LSP).
 */
class ApplicantController extends Controller
{
    use ApplicantHelpers;
    use ValidationHelpers;

    public function __construct(
        protected VerificationService $verificationService
    ) {}

    /**
     * Get the current user's applicant profile.
     */
    public function show(Request $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);
        $applicant->load(['addresses', 'currentEmployment', 'primaryBankAccount']);

        return response()->json([
            'data' => $this->formatApplicant($applicant)
        ]);
    }

    /**
     * Create a new applicant profile for the current user.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->applicant) {
            return response()->json([
                'message' => 'El perfil de solicitante ya existe'
            ], 409);
        }

        $validation = $this->validateRequest($request->all(), [
            'type' => ['sometimes', Rule::in(ApplicantType::values())],
            'first_name' => 'sometimes|string|max:100',
            'last_name_1' => 'sometimes|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
        ]);

        if ($this->isValidationError($validation)) {
            return $validation;
        }

        $applicant = Applicant::create([
            'id' => Str::uuid(),
            'tenant_id' => $request->attributes->get('tenant')->id,
            'user_id' => $user->id,
            'type' => $request->input('type', ApplicantType::INDIVIDUAL->value),
            'phone' => $user->phone,
            'email' => $user->email,
            'first_name' => $request->input('first_name'),
            'last_name_1' => $request->input('last_name_1'),
            'last_name_2' => $request->input('last_name_2'),
            'kyc_status' => KycStatus::PENDING->value,
        ]);

        // Sync phone/email verifications from User to Applicant
        $this->syncUserVerificationsToApplicant($user, $applicant);

        return response()->json([
            'message' => 'Perfil de solicitante creado',
            'data' => $this->formatApplicant($applicant)
        ], 201);
    }

    /**
     * Update the applicant profile.
     */
    public function update(Request $request): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return $this->notFoundResponse('Applicant profile');
        }

        $validation = $this->validateRequest($request->all(), [
            'curp' => 'sometimes|string|size:18',
            'rfc' => 'sometimes|string|min:12|max:13',
            'ine_clave' => 'sometimes|string|max:20',
            'first_name' => 'sometimes|string|max:100',
            'last_name_1' => 'sometimes|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
            'birth_date' => 'sometimes|date',
            'gender' => ['sometimes', Rule::in(Gender::values())],
            'marital_status' => 'nullable|string|max:20',
            'phone' => 'sometimes|string|max:15',
            'phone_secondary' => 'nullable|string|max:15',
            'email' => 'sometimes|email',
            'education_level' => 'nullable|string|max:50',
            'dependents_count' => 'nullable|integer|min:0',
        ]);

        if ($this->isValidationError($validation)) {
            return $validation;
        }

        $applicant->fill($request->only([
            'curp', 'rfc', 'ine_clave',
            'first_name', 'last_name_1', 'last_name_2',
            'birth_date', 'gender', 'marital_status',
            'phone', 'phone_secondary', 'email',
            'education_level', 'dependents_count'
        ]));

        $applicant->save();

        // Sync verification from User if phone/email matches verified User data
        $this->syncUserVerificationsToApplicant($request->user(), $applicant);

        return response()->json([
            'message' => 'Perfil de solicitante actualizado',
            'data' => $this->formatApplicant($applicant->fresh())
        ]);
    }

    /**
     * Update personal data step (Step 1 & 2).
     *
     * Uses UpdatePersonalDataRequest for validation with:
     * - Automatic normalization (CURP/RFC to uppercase, enum normalization)
     * - Custom Spanish error messages
     * - Legacy field name mapping (last_name -> last_name_1)
     */
    public function updatePersonalData(UpdatePersonalDataRequest $request): JsonResponse
    {
        $applicant = $this->getOrCreateApplicant($request);

        // Validation handled by Form Request
        // Only update fields that are provided (supports partial updates)
        $this->updateApplicantFields($applicant, $request);

        try {
            $applicant->save();
        } catch (UniqueConstraintViolationException $e) {
            // Handle duplicate CURP/RFC
            $message = $e->getMessage();
            if (str_contains($message, 'curp')) {
                return response()->json([
                    'message' => 'El CURP ingresado ya está registrado con otra cuenta',
                    'errors' => ['curp' => ['Este CURP ya está registrado en el sistema']]
                ], 422);
            }
            if (str_contains($message, 'rfc')) {
                return response()->json([
                    'message' => 'El RFC ingresado ya está registrado con otra cuenta',
                    'errors' => ['rfc' => ['Este RFC ya está registrado en el sistema']]
                ], 422);
            }
            throw $e;
        }

        // Sync data to users table
        $this->syncApplicantToUser($applicant, $request->user());

        // Sync verification from User if phone/email matches verified User data
        $this->syncUserVerificationsToApplicant($request->user(), $applicant);

        return response()->json([
            'message' => 'Datos personales actualizados',
            'data' => $this->formatApplicant($applicant->fresh())
        ]);
    }

    /**
     * Save applicant signature (Step 8).
     */
    public function saveSignature(Request $request): JsonResponse
    {
        $applicant = $request->user()->applicant;

        if (!$applicant) {
            return $this->notFoundResponse('Applicant profile');
        }

        $validation = $this->validateRequest($request->all(), [
            'signature' => 'required|string', // Base64 PNG
        ]);

        if ($this->isValidationError($validation)) {
            return $validation;
        }

        $applicant->signature_base64 = $request->signature;
        $applicant->signature_date = now();
        $applicant->signature_ip = $request->ip();
        $applicant->save();

        return response()->json([
            'message' => 'Firma guardada',
            'data' => [
                'signed_at' => $applicant->signature_date->toIso8601String(),
            ]
        ]);
    }

    // =========================================================================
    // Delegating methods for backward compatibility
    // These methods resolve controllers from the container to avoid LSP violation
    // =========================================================================

    /**
     * Update address step (Step 3).
     * @deprecated Use AddressController@updatePrimary instead
     */
    public function updateAddress(Request $request): JsonResponse
    {
        return app(AddressController::class)->updatePrimary($request);
    }

    /**
     * Update employment info step (Step 4).
     * @deprecated Use EmploymentController@updateCurrent instead
     */
    public function updateEmployment(Request $request): JsonResponse
    {
        return app(EmploymentController::class)->updateCurrent($request);
    }

    /**
     * Update bank information.
     * @deprecated Use BankAccountController@updatePrimary instead
     */
    public function updateBankInfo(Request $request): JsonResponse
    {
        return app(BankAccountController::class)->updatePrimary($request);
    }

    /**
     * Alias for updateBankInfo (route compatibility).
     * @deprecated Use BankAccountController@updatePrimary instead
     */
    public function updateBankAccount(Request $request): JsonResponse
    {
        return $this->updateBankInfo($request);
    }

    /**
     * List all addresses for the applicant.
     * @deprecated Use AddressController@index instead
     */
    public function listAddresses(Request $request): JsonResponse
    {
        return app(AddressController::class)->index($request);
    }

    /**
     * Add a new address.
     * @deprecated Use AddressController@store instead
     */
    public function storeAddress(Request $request): JsonResponse
    {
        return app(AddressController::class)->store($request);
    }

    /**
     * Update an address by ID.
     * @deprecated Use AddressController@update instead
     */
    public function updateAddressById(Request $request, $address): JsonResponse
    {
        return app(AddressController::class)->update($request, $address);
    }

    /**
     * Delete an address.
     * @deprecated Use AddressController@destroy instead
     */
    public function destroyAddress(Request $request, $address): JsonResponse
    {
        return app(AddressController::class)->destroy($request, $address);
    }

    /**
     * List all employment records for the applicant.
     * @deprecated Use EmploymentController@index instead
     */
    public function listEmploymentRecords(Request $request): JsonResponse
    {
        return app(EmploymentController::class)->index($request);
    }

    /**
     * Add a new employment record.
     * @deprecated Use EmploymentController@store instead
     */
    public function storeEmploymentRecord(Request $request): JsonResponse
    {
        return app(EmploymentController::class)->store($request);
    }

    /**
     * List all bank accounts for the applicant.
     * @deprecated Use BankAccountController@index instead
     */
    public function listBankAccounts(Request $request): JsonResponse
    {
        return app(BankAccountController::class)->index($request);
    }

    /**
     * Add a new bank account.
     * @deprecated Use BankAccountController@store instead
     */
    public function storeBankAccount(Request $request): JsonResponse
    {
        return app(BankAccountController::class)->store($request);
    }

    /**
     * Set a bank account as primary.
     * @deprecated Use BankAccountController@setPrimary instead
     */
    public function setPrimaryBankAccount(Request $request, $bankAccount): JsonResponse
    {
        return app(BankAccountController::class)->setPrimary($request, $bankAccount);
    }

    /**
     * Delete a bank account.
     * @deprecated Use BankAccountController@destroy instead
     */
    public function deleteBankAccount(Request $request, $bankAccount): JsonResponse
    {
        return app(BankAccountController::class)->destroy($request, $bankAccount);
    }

    /**
     * Validate a CLABE number.
     * @deprecated Use BankAccountController@validateClabe instead
     */
    public function validateClabe(Request $request): JsonResponse
    {
        return app(BankAccountController::class)->validateClabe($request);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Update applicant fields from request.
     */
    private function updateApplicantFields(Applicant $applicant, Request $request): void
    {
        if ($request->has('first_name')) {
            $applicant->first_name = $request->first_name;
        }
        // Support both field naming conventions
        if ($request->has('last_name_1') || $request->has('last_name')) {
            $applicant->last_name_1 = $request->last_name_1 ?? $request->last_name;
        }
        if ($request->has('last_name_2') || $request->has('second_last_name')) {
            $applicant->last_name_2 = $request->last_name_2 ?? $request->second_last_name;
        }
        if ($request->has('birth_date')) {
            $applicant->birth_date = $request->birth_date;
        }
        if ($request->has('gender')) {
            $applicant->gender = $request->gender;
        }
        if ($request->filled('marital_status')) {
            $normalized = MaritalStatus::normalize($request->marital_status);
            $applicant->marital_status = $normalized?->value ?? $request->marital_status;
        }
        if ($request->has('nationality')) {
            $applicant->nationality = $request->nationality;
        }
        if ($request->has('birth_state')) {
            $applicant->birth_state = $request->birth_state;
        }
        if ($request->filled('curp')) {
            $applicant->curp = strtoupper($request->curp);
        }
        if ($request->filled('rfc')) {
            $applicant->rfc = strtoupper($request->rfc);
        }
        if ($request->has('ine_clave')) {
            $applicant->ine_clave = $request->ine_clave;
        }
        if ($request->has('ine_ocr')) {
            $applicant->ine_ocr = $request->ine_ocr;
        }
        if ($request->has('ine_folio')) {
            $applicant->ine_folio = $request->ine_folio;
        }
        if ($request->has('passport_number')) {
            $applicant->passport_number = $request->passport_number;
        }
        if ($request->has('passport_issue_date')) {
            $applicant->passport_issue_date = $request->passport_issue_date;
        }
        if ($request->has('passport_expiry_date')) {
            $applicant->passport_expiry_date = $request->passport_expiry_date;
        }
        if ($request->filled('email')) {
            $applicant->email = $request->email;
        }
        if ($request->filled('education_level')) {
            $normalized = EducationLevel::normalize($request->education_level);
            $applicant->education_level = $normalized?->value ?? $request->education_level;
        }
        if ($request->has('dependents_count')) {
            $applicant->dependents_count = $request->dependents_count;
        }

        // Generate full name if name fields exist
        if ($applicant->first_name) {
            $applicant->full_name = trim("{$applicant->first_name} {$applicant->last_name_1} {$applicant->last_name_2}");
        }
    }

    /**
     * Sync applicant data to user table.
     */
    private function syncApplicantToUser(Applicant $applicant, $user): void
    {
        $userUpdates = [];

        if ($applicant->first_name) {
            $userUpdates['first_name'] = $applicant->first_name;
        }
        if ($applicant->last_name_1 || $applicant->last_name_2) {
            $userUpdates['last_name'] = trim("{$applicant->last_name_1} {$applicant->last_name_2}");
        }
        if ($applicant->full_name) {
            $userUpdates['name'] = $applicant->full_name;
        }
        if ($applicant->email && $applicant->email !== $user->email) {
            $userUpdates['email'] = $applicant->email;
        }

        if (!empty($userUpdates)) {
            $user->update($userUpdates);
        }
    }

    /**
     * Sync verified phone/email from User to Applicant's DataVerification records.
     */
    private function syncUserVerificationsToApplicant($user, Applicant $applicant): void
    {
        // If phone was verified via OTP on User
        if ($user->phone && $user->phone_verified_at) {
            $this->verificationService->verify(
                $applicant,
                'phone',
                $user->phone,
                VerificationMethod::OTP,
                ['synced_from_user' => true, 'user_verified_at' => $user->phone_verified_at->toIso8601String()]
            );
        }

        // If email was verified via OTP on User
        if ($user->email && $user->email_verified_at) {
            $this->verificationService->verify(
                $applicant,
                'email',
                $user->email,
                VerificationMethod::OTP,
                ['synced_from_user' => true, 'user_verified_at' => $user->email_verified_at->toIso8601String()]
            );
        }
    }
}
