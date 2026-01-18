<?php

namespace App\Http\Resources\Person;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name_1' => $this->last_name_1,
            'last_name_2' => $this->last_name_2,
            'full_name' => $this->full_name,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'birth_state' => $this->birth_state,
            'birth_country' => $this->birth_country,
            'age' => $this->age,
            'gender' => $this->gender,
            'gender_label' => $this->gender_label,
            'nationality' => $this->nationality,
            'marital_status' => $this->marital_status,
            'marital_status_label' => $this->marital_status_label,
            'education_level' => $this->education_level,
            'education_level_label' => $this->education_level_label,
            'dependents_count' => $this->dependents_count,
            'curp' => $this->curp,
            'rfc' => $this->rfc,
            'kyc_status' => $this->kyc_status,
            'is_kyc_verified' => $this->is_kyc_verified,
            'kyc_verified_at' => $this->kyc_verified_at?->toIso8601String(),
            'profile_completeness' => $this->profile_completeness,
            'account_id' => $this->account_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Conditional relations
            'current_curp' => new PersonIdentificationResource($this->whenLoaded('currentCurp')),
            'current_rfc' => new PersonIdentificationResource($this->whenLoaded('currentRfc')),
            'current_ine' => new PersonIdentificationResource($this->whenLoaded('currentIne')),
            'identifications' => PersonIdentificationResource::collection($this->whenLoaded('identifications')),
            'current_home_address' => new PersonAddressResource($this->whenLoaded('currentHomeAddress')),
            'addresses' => PersonAddressResource::collection($this->whenLoaded('addresses')),
            'current_employment' => new PersonEmploymentResource($this->whenLoaded('currentEmployment')),
            'employments' => PersonEmploymentResource::collection($this->whenLoaded('employments')),
            'primary_bank_account' => new PersonBankAccountResource($this->whenLoaded('primaryBankAccount')),
            'bank_accounts' => PersonBankAccountResource::collection($this->whenLoaded('bankAccounts')),
            'references' => PersonReferenceResource::collection($this->whenLoaded('references')),
        ];
    }
}
