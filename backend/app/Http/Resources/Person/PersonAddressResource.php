<?php

namespace App\Http\Resources\Person;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'street' => $this->street,
            'exterior_number' => $this->exterior_number,
            'interior_number' => $this->interior_number,
            'neighborhood' => $this->neighborhood,
            'municipality' => $this->municipality,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'between_streets' => $this->between_streets,
            'references' => $this->references,
            'full_address' => $this->full_address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'geocode_accuracy' => $this->geocode_accuracy,
            'valid_from' => $this->valid_from?->format('Y-m-d'),
            'valid_until' => $this->valid_until?->format('Y-m-d'),
            'is_current' => $this->is_current,
            'years_at_address' => $this->years_at_address,
            'months_at_address' => $this->months_at_address,
            'housing_type' => $this->housing_type,
            'monthly_rent' => $this->monthly_rent,
            'status' => $this->status,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verification_method' => $this->verification_method,
            'previous_version_id' => $this->previous_version_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
