<?php

namespace App\Http\Resources\Company;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
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
            'legal_name' => $this->legal_name,
            'trade_name' => $this->trade_name,
            'display_name' => $this->display_name,
            'rfc' => $this->rfc,
            'legal_entity_type' => $this->legal_entity_type,
            'incorporation_date' => $this->incorporation_date?->format('Y-m-d'),
            'notary_number' => $this->notary_number,
            'commercial_folio' => $this->commercial_folio,
            'industry_code' => $this->industry_code,
            'industry_description' => $this->industry_description,
            'main_activity' => $this->main_activity,
            'company_size' => $this->company_size,
            'employees_count' => $this->employees_count,
            'annual_revenue' => $this->annual_revenue,
            'annual_revenue_currency' => $this->annual_revenue_currency,
            'website' => $this->website,
            'main_phone' => $this->main_phone,
            'main_email' => $this->main_email,
            'status' => $this->status,
            'is_verified' => $this->isVerified(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'kyb_status' => $this->kyb_status,
            'kyb_verified_at' => $this->kyb_verified_at?->toIso8601String(),
            'can_apply_for_credit' => $this->canApplyForCredit(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'fiscal_address' => $this->when(
                $this->relationLoaded('addresses'),
                fn() => new CompanyAddressResource($this->fiscal_address)
            ),
            'current_addresses' => $this->when(
                $this->relationLoaded('currentAddresses'),
                fn() => CompanyAddressResource::collection($this->currentAddresses)
            ),
            'addresses' => $this->when(
                $this->relationLoaded('addresses'),
                fn() => CompanyAddressResource::collection($this->addresses)
            ),
            'members' => $this->when(
                $this->relationLoaded('members'),
                fn() => CompanyMemberResource::collection($this->members)
            ),
            'active_members' => $this->when(
                $this->relationLoaded('activeMembers'),
                fn() => CompanyMemberResource::collection($this->activeMembers)
            ),
        ];
    }
}
