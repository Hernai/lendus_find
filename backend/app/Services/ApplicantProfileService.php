<?php

namespace App\Services;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\ApplicantAccount;
use App\Models\Person;
use App\Models\PersonAddress;
use App\Models\PersonBankAccount;
use App\Models\PersonEmployment;
use App\Models\PersonReference;
use App\Services\Person\PersonAddressService;
use App\Services\Person\PersonBankAccountService;
use App\Services\Person\PersonEmploymentService;
use App\Services\Person\PersonIdentificationService;
use App\Services\Person\PersonReferenceService;
use App\Services\Person\PersonService;
use Illuminate\Support\Facades\DB;

/**
 * Applicant Profile Service.
 *
 * Orchestrates profile management for applicants using the Person model
 * and related services. This service follows the Facade pattern to provide
 * a unified interface for all profile-related operations.
 *
 * Single Responsibility: Coordinate profile operations for authenticated applicants.
 * Open/Closed: Extensible through injected services.
 * Dependency Inversion: Depends on abstractions (services) not concrete implementations.
 */
class ApplicantProfileService
{
    public function __construct(
        protected PersonService $personService,
        protected PersonIdentificationService $identificationService,
        protected PersonAddressService $addressService,
        protected PersonEmploymentService $employmentService,
        protected PersonBankAccountService $bankAccountService,
        protected PersonReferenceService $referenceService
    ) {}

    // =========================================================================
    // Profile Retrieval
    // =========================================================================

    /**
     * Get or create person for an applicant account.
     */
    public function getOrCreatePerson(ApplicantAccount $account): Person
    {
        if ($account->person) {
            return $account->person;
        }

        return DB::transaction(function () use ($account) {
            $person = Person::create([
                'tenant_id' => $account->tenant_id,
                'account_id' => $account->id,
                'first_name' => '',
                'last_name_1' => '',
                'kyc_status' => Person::KYC_PENDING,
            ]);

            $account->update(['person_id' => $person->id]);

            return $person;
        });
    }

    /**
     * Get complete profile with all relations.
     */
    public function getCompleteProfile(Person $person): array
    {
        $person->load([
            'currentCurp',
            'currentRfc',
            'currentIne',
            'currentHomeAddress',
            'currentEmployment',
            'primaryBankAccount',
            'bankAccounts',
            'references',
        ]);

        return [
            'id' => $person->id,
            'personal_data' => $this->formatPersonalData($person),
            'identifications' => $this->formatIdentifications($person),
            'address' => $this->formatAddress($person->currentHomeAddress),
            'employment' => $this->formatEmployment($person->currentEmployment),
            'bank_account' => $this->formatBankAccount($person->primaryBankAccount),
            'bank_accounts' => $this->formatBankAccounts($person->bankAccounts),
            'references' => $this->formatReferences($person->references),
            'profile_completeness' => $person->profile_completeness,
            'missing_data' => $person->missing_data ?? [],
            'kyc_status' => $person->kyc_status,
            'is_kyc_verified' => $person->is_kyc_verified,
        ];
    }

    /**
     * Get profile summary (minimal data for dashboard).
     */
    public function getProfileSummary(Person $person): array
    {
        return [
            'id' => $person->id,
            'full_name' => $person->full_name,
            'profile_completeness' => $person->profile_completeness,
            'kyc_status' => $person->kyc_status,
            'is_kyc_verified' => $person->is_kyc_verified,
            'has_address' => $person->currentHomeAddress()->exists(),
            'has_employment' => $person->currentEmployment()->exists(),
            'has_bank_account' => $person->primaryBankAccount()->exists(),
        ];
    }

    // =========================================================================
    // Personal Data Operations
    // =========================================================================

    /**
     * Update personal data.
     */
    public function updatePersonalData(Person $person, array $data): Person
    {
        return DB::transaction(function () use ($person, $data) {
            $updateData = [];

            // Map and validate fields
            $fieldMappings = [
                'first_name' => 'first_name',
                'last_name_1' => 'last_name_1',
                'last_name' => 'last_name_1', // Alias
                'last_name_2' => 'last_name_2',
                'second_last_name' => 'last_name_2', // Alias
                'birth_date' => 'birth_date',
                'birth_state' => 'birth_state',
                'birth_country' => 'birth_country',
                'nationality' => 'nationality',
                'dependents_count' => 'dependents_count',
            ];

            foreach ($fieldMappings as $input => $field) {
                if (array_key_exists($input, $data)) {
                    $updateData[$field] = $data[$input];
                }
            }

            // Handle enums with normalization
            if (isset($data['gender'])) {
                $gender = Gender::tryFrom($data['gender']);
                $updateData['gender'] = $gender?->value ?? $data['gender'];
            }

            if (isset($data['marital_status'])) {
                $maritalStatus = MaritalStatus::normalize($data['marital_status']);
                $updateData['marital_status'] = $maritalStatus?->value ?? $data['marital_status'];
            }

            if (isset($data['education_level'])) {
                $educationLevel = EducationLevel::normalize($data['education_level']);
                $updateData['education_level'] = $educationLevel?->value ?? $data['education_level'];
            }

            if (!empty($updateData)) {
                $person->update($updateData);
                $person->calculateCompleteness();
            }

            return $person->fresh();
        });
    }

    // =========================================================================
    // Identification Operations
    // =========================================================================

    /**
     * Update identifications (CURP, RFC, INE).
     */
    public function updateIdentifications(Person $person, array $data): Person
    {
        return DB::transaction(function () use ($person, $data) {
            if (isset($data['curp']) && !empty($data['curp'])) {
                $this->identificationService->setCurp($person, strtoupper($data['curp']));
            }

            if (isset($data['rfc']) && !empty($data['rfc'])) {
                $this->identificationService->setRfc($person, strtoupper($data['rfc']));
            }

            if (isset($data['ine_clave']) || isset($data['ine_ocr']) || isset($data['ine_folio'])) {
                // CIC can come as ine_folio or ine_cic
                $cic = $data['ine_folio'] ?? $data['ine_cic'] ?? '';
                $ocr = $data['ine_ocr'] ?? '';

                // Parse expiration date if provided
                $expiresAt = null;
                if (!empty($data['ine_expiration'])) {
                    $expiresAt = \Carbon\Carbon::parse($data['ine_expiration']);
                }

                // Additional document data (clave_elector)
                $documentData = array_filter([
                    'clave_elector' => $data['ine_clave'] ?? null,
                ]);

                $this->identificationService->setIne(
                    $person,
                    $cic,
                    $ocr,
                    $expiresAt,
                    !empty($documentData) ? $documentData : null
                );
            }

            if (isset($data['passport_number'])) {
                $this->identificationService->create($person, [
                    'type' => 'PASSPORT',
                    'identifier_value' => $data['passport_number'],
                    'issue_date' => $data['passport_issue_date'] ?? null,
                    'expiration_date' => $data['passport_expiry_date'] ?? null,
                ]);
            }

            $person->calculateCompleteness();

            return $person->fresh(['currentCurp', 'currentRfc', 'currentIne']);
        });
    }

    // =========================================================================
    // Address Operations
    // =========================================================================

    /**
     * Update or create home address.
     */
    public function updateAddress(Person $person, array $data): PersonAddress
    {
        return DB::transaction(function () use ($person, $data) {
            $addressData = [
                'type' => 'HOME',
                'street' => $data['street'] ?? null,
                'exterior_number' => $data['ext_number'] ?? $data['exterior_number'] ?? null,
                'interior_number' => $data['int_number'] ?? $data['interior_number'] ?? null,
                'neighborhood' => $data['neighborhood'] ?? null,
                'municipality' => $data['municipality'] ?? null,
                'city' => $data['city'] ?? $data['municipality'] ?? null,
                'state' => $data['state'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'] ?? 'MX',
                'housing_type' => $data['housing_type'] ?? null,
                'years_at_address' => $data['years_at_address'] ?? 0,
                'months_at_address' => $data['months_at_address'] ?? 0,
                'monthly_rent' => $data['monthly_rent'] ?? null,
                'between_streets' => $data['between_streets'] ?? null,
                'references' => $data['references'] ?? null,
            ];

            $address = $this->addressService->setHomeAddress($person, $addressData);
            $person->calculateCompleteness();

            return $address;
        });
    }

    /**
     * Get current home address.
     */
    public function getAddress(Person $person): ?PersonAddress
    {
        return $this->addressService->getCurrentHome($person->id);
    }

    // =========================================================================
    // Employment Operations
    // =========================================================================

    /**
     * Update or create current employment.
     */
    public function updateEmployment(Person $person, array $data): PersonEmployment
    {
        \Log::info('ApplicantProfileService::updateEmployment input', [
            'seniority_years' => $data['seniority_years'] ?? 'NOT_SET',
            'seniority_months' => $data['seniority_months'] ?? 'NOT_SET',
            'data_keys' => array_keys($data),
        ]);

        return DB::transaction(function () use ($person, $data) {
            $employmentData = [
                'employment_type' => $data['employment_type'] ?? $data['type'] ?? 'EMPLOYEE',
                'employer_name' => $data['company_name'] ?? $data['employer_name'] ?? null,
                'job_title' => $data['position'] ?? $data['job_title'] ?? null,
                'department' => $data['department'] ?? null,
                'employer_phone' => $data['work_phone'] ?? $data['company_phone'] ?? $data['employer_phone'] ?? null,
                'employer_address' => $data['company_address'] ?? $data['employer_address'] ?? null,
                'employer_rfc' => $data['employer_rfc'] ?? null,
                'monthly_income' => $data['monthly_income'] ?? null,
                'payment_frequency' => $data['payment_frequency'] ?? 'MONTHLY',
                'contract_type' => $data['contract_type'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'years_employed' => $data['seniority_years'] ?? $data['years_employed'] ?? null,
                'months_employed' => $data['seniority_months'] ?? $data['months_employed'] ?? null,
            ];

            $employment = $this->employmentService->setCurrentEmployment($person, $employmentData);
            $person->calculateCompleteness();

            return $employment;
        });
    }

    /**
     * Get current employment.
     */
    public function getEmployment(Person $person): ?PersonEmployment
    {
        return $this->employmentService->getCurrent($person->id);
    }

    // =========================================================================
    // Bank Account Operations
    // =========================================================================

    /**
     * Update or create primary bank account.
     */
    public function updateBankAccount(Person $person, array $data): PersonBankAccount
    {
        return DB::transaction(function () use ($person, $data) {
            $bankData = [
                'bank_name' => $data['bank_name'] ?? null,
                'bank_code' => $data['bank_code'] ?? null,
                'clabe' => $data['clabe'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'card_number' => $data['card_number'] ?? null,
                'holder_name' => $data['holder_name'] ?? $person->full_name,
                'account_type' => $data['account_type'] ?? 'DEBIT',
            ];

            return $this->bankAccountService->setPrimaryAccount($person, $bankData);
        });
    }

    /**
     * Get primary bank account.
     */
    public function getBankAccount(Person $person): ?PersonBankAccount
    {
        return $this->bankAccountService->getPrimary($person->id);
    }

    /**
     * Validate CLABE number.
     */
    public function validateClabe(string $clabe): array
    {
        $isValid = $this->bankAccountService->validateClabe($clabe);
        $bankCode = $this->bankAccountService->extractBankCode($clabe);
        $bankName = $this->bankAccountService->getBankName($bankCode);

        return [
            'is_valid' => $isValid,
            'bank_code' => $bankCode,
            'bank_name' => $bankName,
        ];
    }

    // =========================================================================
    // Reference Operations
    // =========================================================================

    /**
     * Add a reference.
     */
    public function addReference(Person $person, array $data): PersonReference
    {
        return $this->referenceService->create($person, [
            'type' => $data['type'] ?? 'PERSONAL',
            'first_name' => $data['first_name'],
            'last_name_1' => $data['last_name_1'] ?? $data['last_name'] ?? '',
            'last_name_2' => $data['last_name_2'] ?? null,
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'relationship' => $data['relationship'],
            'years_known' => $data['years_known'] ?? null,
            'employer_name' => $data['employer_name'] ?? null,
            'job_title' => $data['job_title'] ?? null,
        ]);
    }

    /**
     * Update a reference.
     */
    public function updateReference(PersonReference $reference, array $data): PersonReference
    {
        return $this->referenceService->update($reference, $data);
    }

    /**
     * Delete a reference.
     */
    public function deleteReference(PersonReference $reference): bool
    {
        return $this->referenceService->delete($reference);
    }

    /**
     * Get all references for a person.
     */
    public function getReferences(Person $person): \Illuminate\Database\Eloquent\Collection
    {
        return $this->referenceService->getForPerson($person->id);
    }

    // =========================================================================
    // Formatting Helpers (Private)
    // =========================================================================

    private function formatPersonalData(Person $person): array
    {
        return [
            'first_name' => $person->first_name,
            'last_name_1' => $person->last_name_1,
            'last_name_2' => $person->last_name_2,
            'full_name' => $person->full_name,
            'birth_date' => $person->birth_date?->format('Y-m-d'),
            'birth_state' => $person->birth_state,
            'birth_country' => $person->birth_country,
            'age' => $person->age,
            'gender' => $person->gender,
            'gender_label' => $person->gender_label,
            'nationality' => $person->nationality,
            'marital_status' => $person->marital_status,
            'marital_status_label' => $person->marital_status_label,
            'education_level' => $person->education_level,
            'education_level_label' => $person->education_level_label,
            'dependents_count' => $person->dependents_count,
        ];
    }

    private function formatIdentifications(Person $person): array
    {
        return [
            'curp' => $person->currentCurp?->identifier_value,
            'curp_verified' => $person->currentCurp?->is_verified ?? false,
            'rfc' => $person->currentRfc?->identifier_value,
            'rfc_verified' => $person->currentRfc?->is_verified ?? false,
            'ine' => $person->currentIne ? [
                'clave_elector' => $person->currentIne->additional_data['clave_elector'] ?? null,
                'ocr' => $person->currentIne->additional_data['ocr'] ?? null,
                'folio' => $person->currentIne->additional_data['folio'] ?? null,
                'expiration_date' => $person->currentIne->expiration_date?->format('Y-m-d'),
                'verified' => $person->currentIne->is_verified,
            ] : null,
        ];
    }

    private function formatAddress(?PersonAddress $address): ?array
    {
        if (!$address) {
            return null;
        }

        return [
            'id' => $address->id,
            'street' => $address->street,
            'ext_number' => $address->exterior_number,
            'int_number' => $address->interior_number,
            'neighborhood' => $address->neighborhood,
            'municipality' => $address->municipality,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
            'housing_type' => $address->housing_type,
            'years_at_address' => $address->years_at_address,
            'months_at_address' => $address->months_at_address,
            'full_address' => $address->full_address ?? null,
            'is_verified' => $address->is_verified ?? false,
        ];
    }

    private function formatEmployment(?PersonEmployment $employment): ?array
    {
        if (!$employment) {
            return null;
        }

        return [
            'id' => $employment->id,
            'employment_type' => $employment->employment_type,
            'company_name' => $employment->employer_name,
            'position' => $employment->job_title,
            'department' => $employment->department,
            'work_phone' => $employment->employer_phone,
            'monthly_income' => $employment->monthly_income,
            'payment_frequency' => $employment->payment_frequency,
            'years_employed' => $employment->years_employed,
            'months_employed' => $employment->months_employed,
            'seniority_months' => $this->calculateSeniorityMonths($employment),
            'start_date' => $employment->start_date?->format('Y-m-d'),
            'is_verified' => $employment->is_verified ?? false,
            'income_verified' => $employment->income_verified ?? false,
        ];
    }

    /**
     * Calculate seniority months from stored values or start_date.
     */
    private function calculateSeniorityMonths(PersonEmployment $employment): int
    {
        // First priority: use explicit years/months if set
        if ($employment->years_employed !== null || $employment->months_employed !== null) {
            return (($employment->years_employed ?? 0) * 12) + ($employment->months_employed ?? 0);
        }

        // Second priority: calculate from start_date
        if ($employment->start_date) {
            $start = $employment->start_date;
            $now = now();
            $months = ($now->year - $start->year) * 12 + ($now->month - $start->month);
            // Adjust if current day is before start day in the month
            if ($now->day < $start->day) {
                $months--;
            }
            return max(0, $months);
        }

        return 0;
    }

    private function formatBankAccount(?PersonBankAccount $account): ?array
    {
        if (!$account) {
            return null;
        }

        return [
            'id' => $account->id,
            'bank_name' => $account->bank_name,
            'bank_code' => $account->bank_code,
            'clabe' => $account->clabe,
            'clabe_masked' => $account->clabe ? substr($account->clabe, 0, 4) . '****' . substr($account->clabe, -4) : null,
            'account_number' => $account->account_number,
            'holder_name' => $account->holder_name,
            'account_type' => $account->account_type,
            'is_primary' => $account->is_primary ?? false,
            'is_verified' => $account->is_verified ?? false,
        ];
    }

    private function formatBankAccounts($bankAccounts): array
    {
        return $bankAccounts
            ->sortByDesc('is_primary')
            ->sortByDesc('created_at')
            ->map(fn($account) => $this->formatBankAccount($account))
            ->values()
            ->toArray();
    }

    private function formatReferences($references): array
    {
        return $references->map(fn($ref) => [
            'id' => $ref->id,
            'type' => $ref->type,
            'full_name' => trim("{$ref->first_name} {$ref->last_name_1} {$ref->last_name_2}"),
            'phone' => $ref->phone,
            'relationship' => $ref->relationship,
            'years_known' => $ref->years_known,
            'is_verified' => $ref->is_verified ?? false,
            'verification_status' => $ref->verification_status ?? 'PENDING',
        ])->toArray();
    }
}
