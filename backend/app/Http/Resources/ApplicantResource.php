<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,

            // Personal info
            'first_name' => $this->first_name,
            'last_name_1' => $this->last_name_1,
            'last_name_2' => $this->last_name_2,
            'full_name' => $this->full_name,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'age' => $this->birth_date?->age,
            'gender' => $this->gender,
            'marital_status' => $this->marital_status,
            'nationality' => $this->nationality,
            'education_level' => $this->education_level,
            'dependents_count' => $this->dependents_count,

            // Identification
            'curp' => $this->curp,
            'rfc' => $this->rfc,
            'ine_clave' => $this->ine_clave,

            // Contact
            'phone' => $this->phone,
            'phone_secondary' => $this->phone_secondary,
            'email' => $this->email,

            // KYC Status
            'kyc_status' => $this->kyc_status,
            'kyc_completed_at' => $this->kyc_completed_at?->toIso8601String(),

            // Related data (loaded conditionally)
            'primary_address' => new AddressResource($this->whenLoaded('addresses', function () {
                return $this->addresses->where('is_primary', true)->first()
                    ?? $this->addresses->first();
            })),

            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),

            'current_employment' => new EmploymentRecordResource(
                $this->whenLoaded('currentEmployment')
            ),

            'employment_records' => EmploymentRecordResource::collection(
                $this->whenLoaded('employmentRecords')
            ),

            'primary_bank_account' => new BankAccountResource($this->whenLoaded('bankAccounts', function () {
                return $this->bankAccounts->where('is_primary', true)->first()
                    ?? $this->bankAccounts->where('is_active', true)->first();
            })),

            'bank_accounts' => BankAccountResource::collection(
                $this->whenLoaded('bankAccounts')
            ),

            // Applications count
            'applications_count' => $this->when(
                $this->applications_count !== null,
                $this->applications_count
            ),

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
