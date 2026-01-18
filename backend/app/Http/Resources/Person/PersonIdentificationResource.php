<?php

namespace App\Http\Resources\Person;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonIdentificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'identifier_value' => $this->identifier_value,
            'document_data' => $this->document_data,
            'issued_at' => $this->issued_at?->format('Y-m-d'),
            'expires_at' => $this->expires_at?->format('Y-m-d'),
            'is_current' => $this->is_current,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_verified' => $this->is_verified,
            'is_expired' => $this->is_expired,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verification_method' => $this->verification_method,
            'verification_confidence' => $this->verification_confidence,
            'notes' => $this->notes,
            'previous_version_id' => $this->previous_version_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
