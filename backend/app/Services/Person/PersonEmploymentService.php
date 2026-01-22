<?php

namespace App\Services\Person;

use App\Models\Person;
use App\Models\PersonEmployment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersonEmploymentService
{
    /**
     * Create a new employment record for a person.
     */
    public function create(Person $person, array $data): PersonEmployment
    {
        return DB::transaction(function () use ($person, $data) {
            // If this is current employment, end existing current employment
            if ($data['is_current'] ?? false) {
                $existing = PersonEmployment::where('person_id', $person->id)
                    ->where('is_current', true)
                    ->first();

                if ($existing) {
                    $existing->endEmployment();
                }
            }

            $data['tenant_id'] = $person->tenant_id;
            $data['person_id'] = $person->id;
            $data['status'] = $data['status'] ?? PersonEmployment::STATUS_PENDING;

            Log::info('PersonEmploymentService::create data', [
                'years_employed' => $data['years_employed'] ?? 'NOT_SET',
                'months_employed' => $data['months_employed'] ?? 'NOT_SET',
                'years_employed_isset' => isset($data['years_employed']),
                'months_employed_isset' => isset($data['months_employed']),
            ]);

            $employment = PersonEmployment::create($data);

            // Only calculate duration from start_date if explicit years/months were NOT provided
            // Treat 0 and null as "not set" - only preserve explicit positive values
            // This allows automatic calculation from start_date when user hasn't entered seniority
            $yearsProvided = isset($data['years_employed']) && $data['years_employed'] > 0;
            $monthsProvided = isset($data['months_employed']) && $data['months_employed'] > 0;
            $hasExplicitSeniority = $yearsProvided || $monthsProvided;

            Log::info('PersonEmploymentService::create after save', [
                'hasExplicitSeniority' => $hasExplicitSeniority,
                'yearsProvided' => $yearsProvided,
                'monthsProvided' => $monthsProvided,
                'employment_years_employed' => $employment->years_employed,
                'employment_months_employed' => $employment->months_employed,
                'will_calculateDuration' => $employment->start_date && $employment->is_current && !$hasExplicitSeniority,
            ]);

            if ($employment->start_date && $employment->is_current && !$hasExplicitSeniority) {
                $employment->calculateDuration();
            }

            return $employment;
        });
    }

    /**
     * Update an employment record.
     */
    public function update(PersonEmployment $employment, array $data): PersonEmployment
    {
        $employment->update($data);

        // Recalculate duration if dates changed
        if (isset($data['start_date']) || isset($data['end_date'])) {
            $employment->calculateDuration();
        }

        return $employment->fresh();
    }

    /**
     * Delete an employment record (soft delete).
     */
    public function delete(PersonEmployment $employment): bool
    {
        return $employment->delete();
    }

    /**
     * Find an employment record by ID.
     */
    public function find(string $id): ?PersonEmployment
    {
        return PersonEmployment::find($id);
    }

    /**
     * Get all employment records for a person.
     */
    public function getForPerson(string $personId): Collection
    {
        return PersonEmployment::getHistoryForPerson($personId);
    }

    /**
     * Get current employment for a person.
     */
    public function getCurrent(string $personId): ?PersonEmployment
    {
        return PersonEmployment::findCurrentForPerson($personId);
    }

    /**
     * End current employment.
     */
    public function endEmployment(PersonEmployment $employment, ?\DateTimeInterface $endDate = null): PersonEmployment
    {
        if ($endDate) {
            $employment->end_date = $endDate;
        }

        $employment->endEmployment();

        return $employment->fresh();
    }

    /**
     * Mark employment as verified.
     */
    public function verify(
        PersonEmployment $employment,
        string $method,
        ?string $verifiedBy = null,
        ?string $notes = null,
        ?array $verificationData = null
    ): PersonEmployment {
        $employment->markAsVerified($method, $verifiedBy, $notes, $verificationData);

        return $employment->fresh();
    }

    /**
     * Verify income for employment.
     */
    public function verifyIncome(
        PersonEmployment $employment,
        float $verifiedAmount,
        string $method,
        ?string $verifiedBy = null
    ): PersonEmployment {
        $employment->verifyIncome($verifiedAmount, $method, $verifiedBy);

        return $employment->fresh();
    }

    /**
     * Mark employment as rejected.
     */
    public function reject(PersonEmployment $employment, string $reason): PersonEmployment
    {
        $employment->markAsRejected($reason);

        return $employment->fresh();
    }

    /**
     * Mark employment as unreachable.
     */
    public function markUnreachable(PersonEmployment $employment, string $notes): PersonEmployment
    {
        $employment->markAsUnreachable($notes);

        return $employment->fresh();
    }

    /**
     * Set current employment for a person.
     */
    public function setCurrentEmployment(Person $person, array $employmentData): PersonEmployment
    {
        $employmentData['is_current'] = true;
        $employmentData['start_date'] = $employmentData['start_date'] ?? now();

        return $this->create($person, $employmentData);
    }

    /**
     * Update income for current employment.
     */
    public function updateIncome(
        PersonEmployment $employment,
        float $monthlyIncome,
        ?float $additionalIncome = null
    ): PersonEmployment {
        $employment->monthly_income = $monthlyIncome;
        $employment->additional_income = $additionalIncome;
        $employment->income_verified = false;
        $employment->income_verified_at = null;
        $employment->income_verified_by = null;
        $employment->verified_income = null;
        $employment->save();

        return $employment;
    }

    /**
     * Get employment history summary for a person.
     */
    public function getHistorySummary(string $personId): array
    {
        $history = $this->getForPerson($personId);

        $totalMonths = 0;
        $employerCount = 0;

        foreach ($history as $employment) {
            $totalMonths += $employment->total_months_employed ?? 0;
            $employerCount++;
        }

        $current = $history->firstWhere('is_current', true);

        return [
            'current_employer' => $current?->employer_name,
            'current_job_title' => $current?->job_title,
            'current_monthly_income' => $current?->monthly_income,
            'current_tenure_months' => $current?->total_months_employed,
            'total_work_history_months' => $totalMonths,
            'employer_count' => $employerCount,
            'is_income_verified' => $current?->income_verified ?? false,
            'is_employment_verified' => $current?->is_verified ?? false,
        ];
    }

    /**
     * Check if person has verified current employment.
     */
    public function hasVerifiedCurrent(string $personId): bool
    {
        return PersonEmployment::where('person_id', $personId)
            ->where('is_current', true)
            ->verified()
            ->exists();
    }

    /**
     * Check if person has verified income.
     */
    public function hasVerifiedIncome(string $personId): bool
    {
        return PersonEmployment::where('person_id', $personId)
            ->where('is_current', true)
            ->incomeVerified()
            ->exists();
    }

    /**
     * Get all pending verification employments for a person.
     */
    public function getPending(string $personId): Collection
    {
        return PersonEmployment::where('person_id', $personId)
            ->where('status', PersonEmployment::STATUS_PENDING)
            ->get();
    }

    /**
     * Get employments by employer (for verification batching).
     */
    public function getByEmployer(string $tenantId, string $employerName): Collection
    {
        // Escapar wildcards SQL y limitar longitud
        $employerName = str_replace(['%', '_'], ['\\%', '\\_'], $employerName);
        $employerName = mb_substr($employerName, 0, 200);

        return PersonEmployment::where('tenant_id', $tenantId)
            ->where('employer_name', 'LIKE', '%' . $employerName . '%')
            ->where('is_current', true)
            ->get();
    }

    /**
     * Get employments by employment type.
     */
    public function getByType(string $tenantId, string $type): Collection
    {
        return PersonEmployment::where('tenant_id', $tenantId)
            ->ofType($type)
            ->where('is_current', true)
            ->get();
    }

    /**
     * Calculate debt-to-income ratio for a person.
     */
    public function calculateDti(string $personId, float $proposedPayment): ?float
    {
        $current = $this->getCurrent($personId);

        if (!$current || !$current->monthly_income) {
            return null;
        }

        $income = $current->verified_income ?? $current->monthly_income;

        return ($proposedPayment / $income) * 100;
    }

    /**
     * Get income summary for a person.
     */
    public function getIncomeSummary(string $personId): array
    {
        $current = $this->getCurrent($personId);

        if (!$current) {
            return [
                'has_employment' => false,
                'monthly_income' => 0,
                'additional_income' => 0,
                'total_income' => 0,
                'verified_income' => null,
                'is_verified' => false,
            ];
        }

        return [
            'has_employment' => true,
            'employment_type' => $current->employment_type,
            'monthly_income' => $current->monthly_income ?? 0,
            'additional_income' => $current->additional_income ?? 0,
            'total_income' => $current->total_monthly_income ?? 0,
            'verified_income' => $current->verified_income,
            'is_verified' => $current->income_verified,
            'verification_date' => $current->income_verified_at,
        ];
    }
}
