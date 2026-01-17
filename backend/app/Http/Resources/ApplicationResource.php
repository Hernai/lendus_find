<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folio' => $this->folio,
            'status' => $this->status,

            // Applicant (basic)
            'applicant' => $this->when($this->relationLoaded('applicant'), function () {
                return [
                    'id' => $this->applicant->id,
                    'full_name' => $this->applicant->full_name,
                    'phone' => $this->applicant->phone,
                    'email' => $this->applicant->email,
                    'curp' => $this->applicant->curp,
                ];
            }),

            // Product
            'product' => $this->when($this->relationLoaded('product'), function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'type' => $this->product->type,
                ];
            }),

            // Loan details
            'requested_amount' => (float) $this->requested_amount,
            'approved_amount' => $this->approved_amount ? (float) $this->approved_amount : null,
            'term_months' => $this->term_months,
            'payment_frequency' => $this->payment_frequency,
            // Fallback to product rate if application rate is 0 (legacy data)
            // Use explicit numeric comparison because decimal:2 cast returns "0.00" string which is truthy
            'interest_rate' => (float) $this->interest_rate > 0
                ? (float) $this->interest_rate
                : (float) ($this->product?->annual_rate ?? 0),
            'opening_commission' => (float) $this->opening_commission > 0
                ? (float) $this->opening_commission
                : (float) ($this->product?->opening_commission_rate ?? 0),
            'monthly_payment' => (float) $this->monthly_payment,
            'total_to_pay' => (float) $this->total_to_pay,
            'cat' => $this->cat ? (float) $this->cat : null,

            // Purpose
            'purpose' => $this->purpose,
            'purpose_description' => $this->purpose_description,

            // Risk
            'risk_score' => $this->risk_score,
            'risk_level' => $this->risk_level,

            // Assignment
            'assigned_to' => $this->when($this->relationLoaded('assignedAgent'), function () {
                return $this->assignedAgent ? [
                    'id' => $this->assignedAgent->id,
                    'name' => $this->assignedAgent->name,
                ] : null;
            }),

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'disbursed_at' => $this->disbursed_at?->toIso8601String(),
        ];
    }
}
