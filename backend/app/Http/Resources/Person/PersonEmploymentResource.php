<?php

namespace App\Http\Resources\Person;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonEmploymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employment_type' => $this->employment_type,
            'employer_name' => $this->employer_name,
            'employer_rfc' => $this->employer_rfc,
            'employer_phone' => $this->employer_phone,
            'employer_address' => $this->employer_address,
            'job_title' => $this->job_title,
            'department' => $this->department,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'is_current' => $this->is_current,
            'contract_type' => $this->contract_type,
            'monthly_income' => $this->monthly_income,
            'payment_frequency' => $this->payment_frequency,
            'years_employed' => $this->years_employed,
            'months_employed' => $this->months_employed,
            'status' => $this->status,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verification_method' => $this->verification_method,
            'income_verified' => $this->income_verified,
            'income_verified_at' => $this->income_verified_at?->toIso8601String(),
            'verified_income' => $this->verified_income,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
