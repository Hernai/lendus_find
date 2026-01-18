<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'person_id' => $this->person_id,
            'account_id' => $this->account_id,
            'full_name' => $this->full_name,
            'role' => $this->role,
            'role_label' => $this->role_label,
            'title' => $this->title,
            'is_legal_representative' => $this->is_legal_representative,
            'power_type' => $this->power_type,
            'power_type_label' => $this->power_type_label,
            'power_granted_date' => $this->power_granted_date?->format('Y-m-d'),
            'power_expiry_date' => $this->power_expiry_date?->format('Y-m-d'),
            'is_power_valid' => $this->isPowerValid(),
            'can_legally_bind' => $this->canLegallyBind(),
            'is_shareholder' => $this->is_shareholder,
            'ownership_percentage' => $this->ownership_percentage,
            'permissions' => $this->permissions,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'invited_at' => $this->invited_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'removed_at' => $this->removed_at?->toIso8601String(),
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'person' => $this->when(
                $this->relationLoaded('person'),
                fn() => [
                    'id' => $this->person->id,
                    'full_name' => $this->person->full_name,
                    'curp' => $this->person->currentCurp?->value,
                    'rfc' => $this->person->currentRfc?->value,
                ]
            ),
        ];
    }
}
