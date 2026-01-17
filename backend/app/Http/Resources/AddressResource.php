<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'is_primary' => $this->is_primary,
            'street' => $this->street,
            'ext_number' => $this->ext_number,
            'int_number' => $this->int_number,
            'neighborhood' => $this->neighborhood,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'municipality' => $this->municipality,
            'state' => $this->state,
            'country' => $this->country,
            'between_streets' => $this->between_streets,
            'references' => $this->references,

            // Housing details
            'housing_type' => $this->housing_type,
            'housing_type_label' => $this->housing_type_label,
            'years_at_address' => $this->years_at_address,
            'months_at_address' => $this->months_at_address,
            'total_months_at_address' => $this->total_months_at_address,
            'monthly_rent' => $this->monthly_rent ? (float) $this->monthly_rent : null,

            // Verification
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verification_method' => $this->verification_method,

            // Geolocation (only if available)
            'coordinates' => $this->when($this->latitude && $this->longitude, [
                'latitude' => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
            ]),

            // Formatted address
            'full_address' => $this->full_address,

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
