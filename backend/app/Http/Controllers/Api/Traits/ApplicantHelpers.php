<?php

namespace App\Http\Controllers\Api\Traits;

use App\Enums\ApplicantType;
use App\Enums\KycStatus;
use App\Models\Address;
use App\Models\Applicant;
use App\Models\BankAccount;
use App\Models\EmploymentRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Shared helper methods for Applicant-related controllers.
 *
 * Provides formatting methods and common operations to avoid code duplication
 * across the split ApplicantController components.
 */
trait ApplicantHelpers
{
    /**
     * Get or create applicant profile for the current user.
     */
    protected function getOrCreateApplicant(Request $request): Applicant
    {
        $user = $request->user();

        if (!$user->applicant) {
            $applicant = Applicant::create([
                'id' => Str::uuid(),
                'tenant_id' => app('tenant.id'),
                'user_id' => $user->id,
                'type' => ApplicantType::INDIVIDUAL->value,
                'phone' => $user->phone,
                'email' => $user->email,
                'kyc_status' => KycStatus::PENDING->value,
            ]);

            return $applicant;
        }

        return $user->applicant;
    }

    /**
     * Format address for API response.
     */
    protected function formatAddress(Address $address): array
    {
        return [
            'id' => $address->id,
            'type' => $address->type,
            'is_primary' => $address->is_primary,
            'street' => $address->street,
            'ext_number' => $address->ext_number,
            'int_number' => $address->int_number,
            'neighborhood' => $address->neighborhood,
            'municipality' => $address->municipality,
            'postal_code' => $address->postal_code,
            'city' => $address->city,
            'state' => $address->state,
            'country' => $address->country,
            'full_address' => $address->full_address,
            'housing_type' => $address->housing_type,
            'housing_type_label' => $address->housing_type_label,
            'monthly_rent' => $address->monthly_rent,
            'years_at_address' => $address->years_at_address,
            'months_at_address' => $address->months_at_address,
            'is_verified' => $address->is_verified,
            'created_at' => $address->created_at->toIso8601String(),
            'updated_at' => $address->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format employment record for API response.
     */
    protected function formatEmployment(EmploymentRecord $employment): array
    {
        return [
            'id' => $employment->id,
            'is_current' => $employment->is_current,
            'employment_type' => $employment->employment_type,
            'employment_type_label' => $employment->employment_type_label,
            'company_name' => $employment->company_name,
            'company_rfc' => $employment->company_rfc,
            'company_industry' => $employment->company_industry,
            'position' => $employment->position,
            'department' => $employment->department,
            'contract_type' => $employment->contract_type,
            'start_date' => $employment->start_date?->format('Y-m-d'),
            'end_date' => $employment->end_date?->format('Y-m-d'),
            'seniority_months' => $employment->seniority_months,
            'monthly_income' => $employment->monthly_income,
            'monthly_net_income' => $employment->monthly_net_income,
            'payment_frequency' => $employment->payment_frequency,
            'income_type' => $employment->income_type,
            'other_income' => $employment->other_income,
            'other_income_source' => $employment->other_income_source,
            'total_monthly_income' => $employment->total_monthly_income,
            'is_verified' => $employment->is_verified,
            'created_at' => $employment->created_at->toIso8601String(),
            'updated_at' => $employment->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format bank account for API response.
     */
    protected function formatBankAccount(BankAccount $bankAccount): array
    {
        return [
            'id' => $bankAccount->id,
            'type' => $bankAccount->type,
            'is_primary' => $bankAccount->is_primary,
            'bank_name' => $bankAccount->bank_name,
            'bank_code' => $bankAccount->bank_code,
            'clabe' => $bankAccount->masked_clabe,
            'clabe_last4' => substr($bankAccount->clabe, -4),
            'account_number' => $bankAccount->account_number,
            'account_type' => $bankAccount->account_type,
            'account_type_label' => $bankAccount->account_type_label,
            'holder_name' => $bankAccount->holder_name,
            'holder_rfc' => $bankAccount->holder_rfc,
            'is_own_account' => $bankAccount->is_own_account,
            'is_verified' => $bankAccount->is_verified,
            'is_active' => $bankAccount->is_active,
            'created_at' => $bankAccount->created_at->toIso8601String(),
            'updated_at' => $bankAccount->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format applicant for API response.
     */
    protected function formatApplicant(Applicant $applicant): array
    {
        $address = $applicant->addresses->where('type', 'HOME')->first();
        $employment = $applicant->currentEmployment ?? $applicant->employmentRecords->where('is_current', true)->first();
        $bankAccount = $applicant->primaryBankAccount ?? $applicant->bankAccounts->where('is_primary', true)->first();

        return [
            'id' => $applicant->id,
            'type' => $applicant->type,
            'curp' => $applicant->curp,
            'rfc' => $applicant->rfc,
            'ine_clave' => $applicant->ine_clave,
            'ine_ocr' => $applicant->ine_ocr,
            'ine_folio' => $applicant->ine_folio,
            'passport_number' => $applicant->passport_number,
            'passport_issue_date' => $applicant->passport_issue_date?->format('Y-m-d'),
            'passport_expiry_date' => $applicant->passport_expiry_date?->format('Y-m-d'),
            'full_name' => $applicant->full_name,
            'first_name' => $applicant->first_name,
            'last_name_1' => $applicant->last_name_1,
            'last_name_2' => $applicant->last_name_2,
            'birth_date' => $applicant->birth_date?->format('Y-m-d'),
            'age' => $applicant->age,
            'gender' => $applicant->gender,
            'gender_label' => $applicant->gender_label,
            'marital_status' => $applicant->marital_status,
            'marital_status_label' => $applicant->marital_status_label,
            'nationality' => $applicant->nationality,
            'birth_state' => $applicant->birth_state,
            'phone' => $applicant->phone,
            'phone_secondary' => $applicant->phone_secondary,
            'email' => $applicant->email,
            'education_level' => $applicant->education_level,
            'education_level_label' => $applicant->education_level_label,
            'dependents_count' => $applicant->dependents_count,
            'address' => $address ? $this->formatAddressEmbedded($address) : null,
            'employment' => $employment ? $this->formatEmploymentEmbedded($employment) : null,
            'bank_account' => $bankAccount ? $this->formatBankAccountEmbedded($bankAccount) : null,
            'kyc_status' => $applicant->kyc_status,
            'has_signed' => $applicant->hasSigned(),
            'signed_at' => $applicant->signature_date?->toIso8601String(),
            'completeness_percent' => $applicant->completeness_percent,
            'completeness_details' => $applicant->completeness_details,
            'created_at' => $applicant->created_at->toIso8601String(),
            'updated_at' => $applicant->updated_at->toIso8601String(),
        ];
    }

    /**
     * Format address for embedding in applicant response.
     */
    private function formatAddressEmbedded(Address $address): array
    {
        return [
            'id' => $address->id,
            'street' => $address->street,
            'ext_number' => $address->ext_number,
            'int_number' => $address->int_number,
            'neighborhood' => $address->neighborhood,
            'municipality' => $address->municipality,
            'postal_code' => $address->postal_code,
            'city' => $address->city,
            'state' => $address->state,
            'country' => $address->country,
            'full_address' => $address->full_address,
            'housing_type' => $address->housing_type,
            'housing_type_label' => $address->housing_type_label,
            'monthly_rent' => $address->monthly_rent,
            'years_at_address' => $address->years_at_address,
            'months_at_address' => $address->months_at_address,
            'is_verified' => $address->is_verified,
        ];
    }

    /**
     * Format employment for embedding in applicant response.
     */
    private function formatEmploymentEmbedded(EmploymentRecord $employment): array
    {
        return [
            'id' => $employment->id,
            'employment_type' => $employment->employment_type,
            'employment_type_label' => $employment->employment_type_label,
            'company_name' => $employment->company_name,
            'company_rfc' => $employment->company_rfc,
            'company_industry' => $employment->company_industry,
            'position' => $employment->position,
            'department' => $employment->department,
            'contract_type' => $employment->contract_type,
            'start_date' => $employment->start_date?->format('Y-m-d'),
            'seniority_months' => $employment->seniority_months,
            'monthly_income' => $employment->monthly_income,
            'monthly_net_income' => $employment->monthly_net_income,
            'payment_frequency' => $employment->payment_frequency,
            'income_type' => $employment->income_type,
            'other_income' => $employment->other_income,
            'other_income_source' => $employment->other_income_source,
            'total_monthly_income' => $employment->total_monthly_income,
            'work_phone' => $employment->work_phone,
            'is_verified' => $employment->is_verified,
        ];
    }

    /**
     * Format bank account for embedding in applicant response.
     */
    private function formatBankAccountEmbedded(BankAccount $bankAccount): array
    {
        return [
            'id' => $bankAccount->id,
            'bank_name' => $bankAccount->bank_name,
            'clabe_masked' => $bankAccount->masked_clabe,
            'account_type' => $bankAccount->account_type,
            'account_type_label' => $bankAccount->account_type_label,
            'holder_name' => $bankAccount->holder_name,
            'is_verified' => $bankAccount->is_verified,
        ];
    }
}
