<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\Applicant\StoreReferenceRequest;
use App\Enums\BankAccountType;
use App\Enums\HousingType;
use App\Models\Application;
use App\Services\ApplicationEventService;
use App\Services\ApplicantProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * V2 Applicant Profile Controller.
 *
 * Manages the authenticated applicant's profile data using the Person model.
 * All endpoints are under /api/v2/applicant/profile
 */
class ProfileController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected ApplicantProfileService $profileService,
        protected ApplicationEventService $eventService
    ) {}

    /**
     * Get the current (active) application for the person.
     * Returns DRAFT or active application if exists.
     */
    private function getCurrentApplication($person): ?Application
    {
        return Application::where('person_id', $person->id)
            ->active()
            ->orderByDesc('created_at')
            ->first();
    }

    // =========================================================================
    // Profile
    // =========================================================================

    /**
     * Get applicant profile.
     *
     * GET /api/v2/applicant/profile
     */
    public function show(Request $request): JsonResponse
    {
        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        // Load relationships
        $person->load([
            'currentHomeAddress',
            'currentEmployment',
            'bankAccounts' => fn($q) => $q->orderByDesc('is_primary'),
            'references',
            'identifications',
        ]);

        // Calculate seniority in total months for frontend display
        $seniorityMonths = null;
        if ($person->currentEmployment) {
            $years = $person->currentEmployment->years_employed ?? 0;
            $months = $person->currentEmployment->months_employed ?? 0;
            $seniorityMonths = ($years * 12) + $months;
        }

        // Get primary bank account
        $primaryBankAccount = $person->bankAccounts->firstWhere('is_primary', true)
            ?? $person->bankAccounts->first();

        // Calculate profile completeness and missing data
        $completeness = $this->calculateCompleteness($person);
        $missingData = $this->getMissingData($person);

        // Get CURP and RFC from person_identifications table
        $identifications = $person->identifications ?? collect();
        $curp = $identifications->firstWhere('type', 'CURP')?->identifier_value;
        $rfc = $identifications->firstWhere('type', 'RFC')?->identifier_value;
        $ineData = $identifications->firstWhere('type', 'INE');

        return $this->success([
            'id' => $person->id,

            // Personal data section
            'personal_data' => [
                'first_name' => $person->first_name,
                'last_name_1' => $person->last_name_1,
                'last_name_2' => $person->last_name_2,
                'full_name' => $person->full_name,
                'birth_date' => $person->birth_date?->format('Y-m-d'),
                'birth_state' => $person->birth_state,
                'birth_country' => $person->birth_country ?? 'México',
                'age' => $person->birth_date?->age,
                'gender' => $person->gender,
                'gender_label' => $this->getGenderLabel($person->gender),
                'nationality' => $person->nationality,
                'marital_status' => $person->marital_status,
                'marital_status_label' => $this->getMaritalStatusLabel($person->marital_status),
                'education_level' => $person->education_level,
                'education_level_label' => $this->getEducationLevelLabel($person->education_level),
                'dependents_count' => $person->dependents_count,
            ],

            // Identifications section (from person_identifications table)
            'identifications' => [
                'curp' => $curp,
                'rfc' => $rfc,
                'ine_clave' => $ineData?->identifier_value,
                'ine_ocr' => $ineData?->document_data['ocr'] ?? null,
                'ine_folio' => $ineData?->document_data['folio'] ?? null,
                'ine_verified' => $ineData?->verified_at !== null,
            ],

            // Contact info
            'contact' => [
                'email' => $account->email,
                'phone' => $account->phone,
            ],

            // Address section
            'address' => $person->currentHomeAddress ? [
                'id' => $person->currentHomeAddress->id,
                'street' => $person->currentHomeAddress->street,
                'ext_number' => $person->currentHomeAddress->exterior_number,
                'int_number' => $person->currentHomeAddress->interior_number,
                'neighborhood' => $person->currentHomeAddress->neighborhood,
                'municipality' => $person->currentHomeAddress->municipality,
                'city' => $person->currentHomeAddress->city,
                'state' => $person->currentHomeAddress->state,
                'postal_code' => $person->currentHomeAddress->postal_code,
                'country' => 'México',
                'housing_type' => $person->currentHomeAddress->housing_type,
                'years_at_address' => $person->currentHomeAddress->years_at_address,
                'months_at_address' => $person->currentHomeAddress->months_at_address,
                'full_address' => $this->formatFullAddress($person->currentHomeAddress),
                'is_verified' => (bool) $person->currentHomeAddress->verified_at,
            ] : null,

            // Employment section
            'employment' => $person->currentEmployment ? [
                'id' => $person->currentEmployment->id,
                'employment_type' => $person->currentEmployment->employment_type,
                'company_name' => $person->currentEmployment->employer_name,
                'position' => $person->currentEmployment->job_title,
                'department' => $person->currentEmployment->department,
                'work_phone' => $this->formatPhone($person->currentEmployment->employer_phone),
                'monthly_income' => (int) $person->currentEmployment->monthly_income,
                'payment_frequency' => $person->currentEmployment->payment_frequency,
                'years_employed' => $person->currentEmployment->years_employed,
                'months_employed' => $person->currentEmployment->months_employed,
                'seniority_months' => $seniorityMonths,
                'start_date' => $person->currentEmployment->start_date?->format('Y-m-d'),
                'is_verified' => (bool) $person->currentEmployment->verified_at,
                'income_verified' => (bool) $person->currentEmployment->income_verified,
            ] : null,

            // Bank account (primary one for backward compatibility)
            'bank_account' => $primaryBankAccount ? [
                'id' => $primaryBankAccount->id,
                'bank_name' => $primaryBankAccount->bank_name,
                'bank_code' => $primaryBankAccount->bank_code,
                'clabe' => $primaryBankAccount->clabe,
                'clabe_masked' => $this->maskClabe($primaryBankAccount->clabe),
                'account_number' => null,
                'holder_name' => $primaryBankAccount->holder_name,
                'account_type' => $primaryBankAccount->account_type,
                'is_primary' => (bool) $primaryBankAccount->is_primary,
                'is_verified' => (bool) ($primaryBankAccount->is_verified ?? false),
            ] : null,

            // All bank accounts
            'bank_accounts' => $person->bankAccounts->map(fn($ba) => [
                'id' => $ba->id,
                'bank_name' => $ba->bank_name,
                'bank_code' => $ba->bank_code,
                'clabe' => $ba->clabe,
                'clabe_masked' => $this->maskClabe($ba->clabe),
                'account_number' => null,
                'holder_name' => $ba->holder_name,
                'account_type' => $ba->account_type,
                'is_primary' => (bool) $ba->is_primary,
                'is_verified' => (bool) ($ba->is_verified ?? false),
            ])->values(),

            // References
            'references' => $person->references->map(fn($ref) => [
                'id' => $ref->id,
                'type' => $ref->type,
                'first_name' => $ref->first_name,
                'last_name_1' => $ref->last_name_1,
                'last_name_2' => $ref->last_name_2,
                'full_name' => trim("{$ref->first_name} {$ref->last_name_1} {$ref->last_name_2}"),
                'phone' => $ref->phone,
                'relationship' => $ref->relationship,
                'years_known' => $ref->years_known,
                'is_verified' => (bool) $ref->verified_at,
                'verification_status' => $ref->verification_status ?? 'PENDING',
            ])->values(),

            // Summary and status
            'profile_completeness' => $completeness,
            'missing_data' => $missingData,
            'kyc_status' => $this->getKycStatusString($person),
            'is_kyc_verified' => $person->isKycVerified(),
            'has_signature' => $person->signature_date !== null,
        ]);
    }

    /**
     * Get gender label.
     */
    private function getGenderLabel(?string $gender): ?string
    {
        if (!$gender) return null;
        return match ($gender) {
            'M' => 'Masculino',
            'F' => 'Femenino',
            default => $gender,
        };
    }

    /**
     * Get marital status label.
     */
    private function getMaritalStatusLabel(?string $status): ?string
    {
        if (!$status) return null;
        return match ($status) {
            'SINGLE' => 'Soltero/a',
            'MARRIED' => 'Casado/a',
            'DIVORCED' => 'Divorciado/a',
            'WIDOWED' => 'Viudo/a',
            'FREE_UNION' => 'Unión libre',
            'SEPARATED' => 'Separado/a',
            default => $status,
        };
    }

    /**
     * Get education level label.
     */
    private function getEducationLevelLabel(?string $level): ?string
    {
        if (!$level) return null;
        return match ($level) {
            'NONE' => 'Sin estudios',
            'PRIMARY' => 'Primaria',
            'SECONDARY' => 'Secundaria',
            'HIGH_SCHOOL' => 'Preparatoria',
            'TECHNICAL' => 'Carrera técnica',
            'BACHELOR' => 'Licenciatura',
            'MASTER' => 'Maestría',
            'DOCTORATE' => 'Doctorado',
            default => $level,
        };
    }

    /**
     * Format full address string.
     */
    private function formatFullAddress($address): string
    {
        $parts = [];
        if ($address->street) {
            $street = $address->street;
            if ($address->exterior_number) $street .= ' ' . $address->exterior_number;
            if ($address->interior_number) $street .= ', Int ' . $address->interior_number;
            $parts[] = $street;
        }
        if ($address->neighborhood) $parts[] = 'Col. ' . $address->neighborhood;
        if ($address->postal_code) $parts[] = 'CP ' . $address->postal_code;
        $cityState = trim(($address->municipality ?? $address->city ?? '') . ', ' . ($address->state ?? ''), ', ');
        if ($cityState) $parts[] = $cityState;
        return implode(', ', $parts);
    }

    /**
     * Get KYC status as string for frontend.
     */
    private function getKycStatusString($person): string
    {
        if ($person->isKycVerified()) {
            return 'VERIFIED';
        }
        $identifications = $person->identifications ?? collect();
        if ($identifications->where('is_verified', true)->isNotEmpty()) {
            return 'PARTIAL';
        }
        return 'PENDING';
    }

    /**
     * Get list of missing data fields.
     */
    private function getMissingData($person): array
    {
        $missing = [];
        if (!$person->first_name) $missing[] = 'first_name';
        if (!$person->last_name_1) $missing[] = 'last_name_1';
        if (!$person->birth_date) $missing[] = 'birth_date';

        // Check CURP from identifications
        $identifications = $person->identifications ?? collect();
        if (!$identifications->firstWhere('type', 'CURP')?->identifier_value) {
            $missing[] = 'curp';
        }

        if (!$person->currentHomeAddress) $missing[] = 'address';
        if (!$person->currentEmployment) $missing[] = 'employment';
        if ($person->bankAccounts->isEmpty()) $missing[] = 'bank_account';
        return $missing;
    }

    /**
     * Mask CLABE for display (show only last 4 digits).
     */
    private function maskClabe(?string $clabe): ?string
    {
        if (!$clabe || strlen($clabe) < 4) {
            return $clabe;
        }
        return str_repeat('*', strlen($clabe) - 4) . substr($clabe, -4);
    }

    /**
     * Format Mexican phone number for display (XX XXXX XXXX).
     */
    private function formatPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        // Remove all non-numeric characters
        $cleaned = preg_replace('/\D/', '', $phone);

        // Only format if it's a valid 10-digit number
        if (strlen($cleaned) === 10) {
            return substr($cleaned, 0, 2) . ' ' . substr($cleaned, 2, 4) . ' ' . substr($cleaned, 6, 4);
        }

        return $phone;
    }

    // =========================================================================
    // Personal Data
    // =========================================================================

    /**
     * Update personal data.
     *
     * PATCH /api/v2/applicant/profile/personal-data
     */
    public function updatePersonalData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name_1' => 'sometimes|string|max:100',
            'last_name_2' => 'nullable|string|max:100',
            'birth_date' => 'sometimes|date|before:today',
            'birth_state' => 'nullable|string|max:50',
            'birth_country' => 'nullable|string|max:50',
            'gender' => 'nullable|in:M,F,O',
            'nationality' => 'nullable|string|max:50',
            'marital_status' => 'nullable|string|max:30',
            'education_level' => 'nullable|string|max:50',
            'dependents_count' => 'nullable|integer|min:0|max:20',
        ]);

        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        // Track which fields were changed
        $changedFields = array_keys(array_filter($validated, fn($value, $key) =>
            $person->$key !== $value, ARRAY_FILTER_USE_BOTH
        ));

        $person->update($validated);

        // Record event if there's an active application
        $application = $this->getCurrentApplication($person);
        if ($application && !empty($changedFields)) {
            $this->eventService->recordProfileUpdated(
                $application,
                $account->id,
                $changedFields,
                $request
            );
        }

        return $this->success([
            'personal_data' => [
                'first_name' => $person->first_name,
                'last_name_1' => $person->last_name_1,
                'last_name_2' => $person->last_name_2,
                'full_name' => $person->full_name,
                'birth_date' => $person->birth_date?->format('Y-m-d'),
                'birth_state' => $person->birth_state,
                'gender' => $person->gender,
                'nationality' => $person->nationality,
                'marital_status' => $person->marital_status,
                'education_level' => $person->education_level,
                'dependents_count' => $person->dependents_count,
            ],
            'profile_completeness' => $this->calculateCompleteness($person),
        ], 'Datos actualizados');
    }

    /**
     * Update identifications (CURP, RFC, INE).
     *
     * PATCH /api/v2/applicant/profile/identifications
     *
     * Saves to person_identifications table instead of persons table.
     */
    public function updateIdentifications(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'curp' => 'sometimes|string|size:18',
            'rfc' => 'sometimes|string|min:12|max:13',
            'ine_clave' => 'sometimes|string|max:20',
            'ine_ocr' => 'sometimes|string|max:20',
            'ine_folio' => 'sometimes|string|max:20',
            // Passport fields for foreigners
            'passport_number' => 'sometimes|string|max:20',
            'passport_issue_date' => 'sometimes|date',
            'passport_expiry_date' => 'sometimes|date|after:today',
        ]);

        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        // Save CURP to person_identifications (preserve verified status if already verified)
        if (isset($validated['curp'])) {
            $existingCurp = $person->identifications()->where('type', 'CURP')->first();
            $isVerified = $existingCurp && $existingCurp->status === 'VERIFIED';

            $person->identifications()->updateOrCreate(
                ['type' => 'CURP'],
                [
                    'tenant_id' => $account->tenant_id,
                    'identifier_value' => strtoupper($validated['curp']),
                    'is_current' => true,
                    'status' => $isVerified ? 'VERIFIED' : 'PENDING',
                    'verified_at' => $isVerified ? ($existingCurp->verified_at ?? now()) : null,
                    'verification_method' => $isVerified ? ($existingCurp->verification_method ?? 'MANUAL') : null,
                ]
            );
        }

        // Save RFC to person_identifications (preserve verified status if already verified)
        if (isset($validated['rfc'])) {
            $existingRfc = $person->identifications()->where('type', 'RFC')->first();
            $isVerified = $existingRfc && $existingRfc->status === 'VERIFIED';

            $person->identifications()->updateOrCreate(
                ['type' => 'RFC'],
                [
                    'tenant_id' => $account->tenant_id,
                    'identifier_value' => strtoupper($validated['rfc']),
                    'is_current' => true,
                    'status' => $isVerified ? 'VERIFIED' : 'PENDING',
                    'verified_at' => $isVerified ? ($existingRfc->verified_at ?? now()) : null,
                    'verification_method' => $isVerified ? ($existingRfc->verification_method ?? 'MANUAL') : null,
                ]
            );
        }

        // Save INE to person_identifications (preserve verified status if already verified)
        if (isset($validated['ine_clave'])) {
            $existingIne = $person->identifications()->where('type', 'INE')->first();
            $isVerified = $existingIne && $existingIne->status === 'VERIFIED';

            $ineData = [
                'ocr' => $validated['ine_ocr'] ?? ($existingIne?->document_data['ocr'] ?? null),
                'folio' => $validated['ine_folio'] ?? ($existingIne?->document_data['folio'] ?? null),
            ];
            $person->identifications()->updateOrCreate(
                ['type' => 'INE'],
                [
                    'tenant_id' => $account->tenant_id,
                    'identifier_value' => strtoupper($validated['ine_clave']),
                    'document_data' => $ineData,
                    'is_current' => true,
                    'status' => $isVerified ? 'VERIFIED' : 'PENDING',
                    'verified_at' => $isVerified ? ($existingIne->verified_at ?? now()) : null,
                    'verification_method' => $isVerified ? ($existingIne->verification_method ?? 'MANUAL') : null,
                ]
            );
        }

        // Save PASSPORT to person_identifications (for foreigners)
        if (isset($validated['passport_number'])) {
            $existingPassport = $person->identifications()->where('type', 'PASSPORT')->first();
            $isVerified = $existingPassport && $existingPassport->status === 'VERIFIED';

            $passportData = [
                'issue_date' => $validated['passport_issue_date'] ?? ($existingPassport?->document_data['issue_date'] ?? null),
                'expiry_date' => $validated['passport_expiry_date'] ?? ($existingPassport?->document_data['expiry_date'] ?? null),
            ];
            $person->identifications()->updateOrCreate(
                ['type' => 'PASSPORT'],
                [
                    'tenant_id' => $account->tenant_id,
                    'identifier_value' => strtoupper($validated['passport_number']),
                    'document_data' => $passportData,
                    'is_current' => true,
                    'status' => $isVerified ? 'VERIFIED' : 'PENDING',
                    'verified_at' => $isVerified ? ($existingPassport->verified_at ?? now()) : null,
                    'verification_method' => $isVerified ? ($existingPassport->verification_method ?? 'MANUAL') : null,
                ]
            );
        }

        // Reload identifications
        $person->load('identifications');
        $identifications = $person->identifications;
        $curp = $identifications->firstWhere('type', 'CURP')?->identifier_value;
        $rfc = $identifications->firstWhere('type', 'RFC')?->identifier_value;
        $passport = $identifications->firstWhere('type', 'PASSPORT');

        $response = [
            'identifications' => [
                'curp' => $curp,
                'rfc' => $rfc,
            ],
            'profile_completeness' => $this->calculateCompleteness($person),
        ];

        // Include passport data if exists
        if ($passport) {
            $response['identifications']['passport_number'] = $passport->identifier_value;
            $response['identifications']['passport_issue_date'] = $passport->document_data['issue_date'] ?? null;
            $response['identifications']['passport_expiry_date'] = $passport->document_data['expiry_date'] ?? null;
        }

        return $this->success($response, 'Identificaciones actualizadas');
    }

    /**
     * Calculate profile completeness percentage.
     */
    private function calculateCompleteness($person): int
    {
        $total = 8;
        $filled = 0;

        // Personal data fields
        if (!empty($person->first_name)) $filled++;
        if (!empty($person->last_name_1)) $filled++;
        if (!empty($person->birth_date)) $filled++;
        if (!empty($person->gender)) $filled++;
        if (!empty($person->nationality)) $filled++;
        if (!empty($person->marital_status)) $filled++;

        // Check CURP and RFC from identifications
        $identifications = $person->identifications ?? collect();
        if ($identifications->firstWhere('type', 'CURP')?->identifier_value) $filled++;
        if ($identifications->firstWhere('type', 'RFC')?->identifier_value) $filled++;

        return (int) round(($filled / $total) * 100);
    }

    /**
     * Normalize housing type from frontend to database values.
     * Uses the HousingType enum's normalize method for consistency.
     */
    private function normalizeHousingType(string $type): string
    {
        $normalized = HousingType::normalize($type);
        return $normalized?->value ?? $type;
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
        $address = $person->currentHomeAddress;

        if (!$address) {
            return $this->success(null);
        }

        return $this->success([
            'street' => $address->street,
            'exterior_number' => $address->exterior_number,
            'interior_number' => $address->interior_number,
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
    public function updateAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'street' => 'required|string|max:200',
            'ext_number' => 'required|string|max:20',
            'int_number' => 'nullable|string|max:20',
            'neighborhood' => 'required|string|max:100',
            'municipality' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'required|string|max:50',
            'postal_code' => 'required|string|size:5',
            'housing_type' => 'required|string',
            'years_at_address' => 'nullable|integer|min:0|max:99',
            'months_at_address' => 'nullable|integer|min:0|max:11',
        ]);

        // Map frontend field names to database column names
        $data = [
            'street' => $validated['street'],
            'exterior_number' => $validated['ext_number'],
            'interior_number' => $validated['int_number'] ?? null,
            'neighborhood' => $validated['neighborhood'],
            'municipality' => $validated['municipality'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'],
            'postal_code' => $validated['postal_code'],
            'housing_type' => $this->normalizeHousingType($validated['housing_type']),
            'years_at_address' => $validated['years_at_address'] ?? null,
            'months_at_address' => $validated['months_at_address'] ?? null,
        ];

        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        $address = $person->currentHomeAddress;

        if ($address) {
            $address->update($data);
        } else {
            $address = $person->addresses()->create(array_merge($data, [
                'tenant_id' => $account->tenant_id,
                'entity_type' => 'persons',
                'type' => 'HOME',
                'is_current' => true,
                'valid_from' => now(),
            ]));
        }

        // Record event if there's an active application
        $application = $this->getCurrentApplication($person);
        if ($application) {
            $this->eventService->recordAddressSaved(
                $application,
                $account->id,
                $validated['postal_code'],
                $request
            );
        }

        return $this->success([
            'address' => [
                'street' => $address->street,
                'exterior_number' => $address->exterior_number,
                'interior_number' => $address->interior_number,
                'neighborhood' => $address->neighborhood,
                'municipality' => $address->municipality,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'housing_type' => $address->housing_type,
                'years_at_address' => $address->years_at_address,
                'months_at_address' => $address->months_at_address,
            ],
            'profile_completeness' => $this->calculateCompleteness($person),
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
        $employment = $person->currentEmployment;

        if (!$employment) {
            return $this->success(null);
        }

        return $this->success([
            'employment_type' => $employment->employment_type,
            'company_name' => $employment->employer_name,
            'position' => $employment->job_title,
            'department' => $employment->department,
            'monthly_income' => (int) $employment->monthly_income,
            'payment_frequency' => $employment->payment_frequency,
            'seniority_years' => $employment->years_employed,
            'seniority_months' => $employment->months_employed,
            'start_date' => $employment->start_date?->format('Y-m-d'),
            'work_phone' => $this->formatPhone($employment->employer_phone),
        ]);
    }

    /**
     * Update employment.
     *
     * PUT /api/v2/applicant/profile/employment
     */
    public function updateEmployment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employment_type' => 'required|string|max:50',
            'company_name' => 'nullable|string|max:200',
            'position' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'monthly_income' => 'required|numeric|min:0',
            'payment_frequency' => 'nullable|string|max:20',
            'contract_type' => 'nullable|string|max:50',
            'start_date' => 'nullable|date',
            'seniority_years' => 'nullable|integer|min:0|max:99',
            'seniority_months' => 'nullable|integer|min:0|max:11',
            'work_phone' => 'nullable|string|max:20',
            'company_address' => 'nullable|string|max:300',
            'employer_rfc' => 'nullable|string|max:13',
        ]);

        // Clean phone number (remove spaces and formatting)
        $cleanPhone = null;
        if (!empty($validated['work_phone'])) {
            $cleanPhone = preg_replace('/\D/', '', $validated['work_phone']);
            // Only save if it's a valid 10-digit Mexican number
            if (strlen($cleanPhone) !== 10) {
                $cleanPhone = null;
            }
        }

        // Map frontend field names to database column names (person_employments table)
        $data = [
            'employment_type' => $validated['employment_type'],
            'employer_name' => $validated['company_name'] ?? null,
            'job_title' => $validated['position'] ?? null,
            'department' => $validated['department'] ?? null,
            'monthly_income' => $validated['monthly_income'],
            'payment_frequency' => $validated['payment_frequency'] ?? null,
            'contract_type' => $validated['contract_type'] ?? null,
            'years_employed' => $validated['seniority_years'] ?? null,
            'months_employed' => $validated['seniority_months'] ?? null,
            'employer_phone' => $cleanPhone,
            'employer_address' => $validated['company_address'] ?? null,
            'employer_rfc' => $validated['employer_rfc'] ?? null,
        ];

        // Calculate start_date from seniority if not provided directly
        if (empty($validated['start_date']) && ($data['years_employed'] || $data['months_employed'])) {
            $years = $data['years_employed'] ?? 0;
            $months = $data['months_employed'] ?? 0;
            $data['start_date'] = now()->subYears($years)->subMonths($months)->startOfMonth();
        } elseif (!empty($validated['start_date'])) {
            $data['start_date'] = $validated['start_date'];
        }

        $account = $request->user();
        $person = $this->profileService->getOrCreatePerson($account);

        // Use the service method which already has the duration calculation logic
        // Pass only $data since it already has all fields properly mapped to DB column names
        $employment = $this->profileService->updateEmployment($person, $data);

        // Record event if there's an active application
        $application = $this->getCurrentApplication($person);
        if ($application) {
            $this->eventService->recordEmploymentSaved(
                $application,
                $account->id,
                $validated['employment_type'],
                $request
            );
        }

        return $this->success([
            'employment' => [
                'employment_type' => $employment->employment_type,
                'company_name' => $employment->employer_name,
                'position' => $employment->job_title,
                'department' => $employment->department,
                'monthly_income' => (int) $employment->monthly_income,
                'payment_frequency' => $employment->payment_frequency,
                'seniority_years' => $employment->years_employed,
                'seniority_months' => $employment->months_employed,
                'start_date' => $employment->start_date?->format('Y-m-d'),
                'work_phone' => $this->formatPhone($employment->employer_phone),
            ],
            'profile_completeness' => $this->calculateCompleteness($person),
        ], 'Empleo actualizado');
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

        // Normalize account_type using enum
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
            'entity_type' => 'persons',
            'bank_code' => $clabeValidation['bank_code'],
            'bank_name' => $clabeValidation['bank_name'],
            'clabe' => $validated['clabe'],
            'holder_name' => strtoupper($validated['holder_name']),
            'account_type' => $accountType->value,
            'is_primary' => $isFirst,
            'status' => 'ACTIVE',
        ]);

        // Record event if there's an active application
        $application = $this->getCurrentApplication($person);
        if ($application) {
            $this->eventService->recordBankAccountAdded(
                $application,
                $clabeValidation['bank_name'],
                $account->id,
                $request
            );
        }

        return $this->created([
            'bank_account' => $this->formatBankAccount($bankAccount),
        ], 'Cuenta bancaria creada');
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

        return $this->success($references->map(fn($ref) => [
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
        ]));
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

        // Record event if there's an active application
        $application = $this->getCurrentApplication($person);
        if ($application) {
            $fullName = trim("{$reference->first_name} {$reference->last_name_1}");
            $this->eventService->recordReferenceAdded(
                $application,
                $reference->type,
                $fullName,
                $account->id,
                $request
            );
        }

        return $this->created([
            'id' => $reference->id,
            'type' => $reference->type,
            'first_name' => $reference->first_name,
            'last_name_1' => $reference->last_name_1,
            'last_name_2' => $reference->last_name_2,
            'full_name' => trim("{$reference->first_name} {$reference->last_name_1}"),
            'phone' => $reference->phone,
            'relationship' => $reference->relationship,
        ], 'Referencia agregada');
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
            'signature' => 'required|string',
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
            return $this->validationError('Firma inválida', ['signature' => ['La firma no es válida']]);
        }

        // Generate unique filename
        $filename = 'signature_' . $person->id . '_' . now()->format('YmdHis') . '.png';
        $path = "documents/{$account->tenant_id}/signatures/{$filename}";

        // Store file
        $disk = config('filesystems.default', 'local');
        \Illuminate\Support\Facades\Storage::disk($disk)->put($path, $imageData);

        // Mark any existing signature documents as replaced
        \App\Models\Document::where('documentable_type', \App\Models\Person::class)
            ->where('documentable_id', $person->id)
            ->where('type', 'SIGNATURE')
            ->whereNull('replaced_at')
            ->update(['replaced_at' => now()]);

        // Create document record
        $document = \App\Models\Document::create([
            'tenant_id' => $account->tenant_id,
            'documentable_type' => \App\Models\Person::class,
            'documentable_id' => $person->id,
            'type' => 'SIGNATURE',
            'category' => 'LEGAL',
            'file_name' => $filename,
            'original_filename' => 'firma_digital.png',
            'file_path' => $path,
            'mime_type' => 'image/png',
            'file_size' => strlen($imageData),
            'storage_disk' => $disk,
            'status' => 'APPROVED',
            'uploaded_by' => $account->id,
            'ocr_data' => [
                'signed_at' => now()->toIso8601String(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);

        // Update person record
        $person->update([
            'signature_date' => now(),
            'signature_ip' => $request->ip(),
        ]);

        // Record event if there's an active application
        $application = $this->getCurrentApplication($person);
        if ($application) {
            $this->eventService->recordSignatureSaved(
                $application,
                $account->id,
                $request
            );
        }

        return $this->success([
            'signed_at' => now()->toIso8601String(),
            'document_id' => $document->id,
        ], 'Firma guardada');
    }
}
