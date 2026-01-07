<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmploymentRecordResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'is_current' => $this->is_current,
            'employment_type' => $this->employment_type,
            'occupation' => $this->occupation,

            // Company info
            'company_name' => $this->company_name,
            'company_rfc' => $this->company_rfc,
            'company_industry' => $this->company_industry,
            'company_size' => $this->company_size,
            'company_phone' => $this->company_phone,
            'company_address' => $this->company_address,

            // Position details
            'job_title' => $this->job_title,
            'department' => $this->department,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'seniority_years' => $this->seniority_years,
            'contract_type' => $this->contract_type,

            // Income
            'monthly_income' => $this->monthly_income ? (float) $this->monthly_income : null,
            'monthly_net_income' => $this->monthly_net_income ? (float) $this->monthly_net_income : null,
            'payment_frequency' => $this->payment_frequency,
            'payment_day' => $this->payment_day,

            // Additional income
            'other_income' => $this->other_income ? (float) $this->other_income : null,
            'other_income_source' => $this->other_income_source,

            // Total income (computed)
            'total_monthly_income' => $this->monthly_net_income
                ? (float) $this->monthly_net_income + ($this->other_income ?? 0)
                : null,

            // Supervisor
            'supervisor_name' => $this->supervisor_name,
            'supervisor_phone' => $this->supervisor_phone,

            // Verification
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verification_method' => $this->verification_method,

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
