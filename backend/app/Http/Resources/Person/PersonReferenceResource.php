<?php

namespace App\Http\Resources\Person;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonReferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'first_name' => $this->first_name,
            'last_name_1' => $this->last_name_1,
            'last_name_2' => $this->last_name_2,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'relationship' => $this->relationship,
            'relationship_label' => $this->relationship_label,
            'years_known' => $this->years_known,
            'employer_name' => $this->employer_name,
            'job_title' => $this->job_title,
            'status' => $this->status,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verification_notes' => $this->verification_notes,
            'contact_attempts' => $this->contact_attempts,
            'last_contact_attempt' => $this->last_contact_attempt?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
