<?php

namespace App\Http\Resources\Person;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonBankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bank_name' => $this->bank_name,
            'bank_code' => $this->bank_code,
            'clabe' => $this->clabe,
            'clabe_masked' => $this->clabe_masked,
            'account_number' => $this->account_number,
            'card_number_masked' => $this->card_number ? $this->maskCardNumber($this->card_number) : null,
            'holder_name' => $this->holder_name,
            'account_type' => $this->account_type,
            'is_primary' => $this->is_primary,
            'is_for_disbursement' => $this->is_for_disbursement,
            'is_for_collection' => $this->is_for_collection,
            'status' => $this->status,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verification_method' => $this->verification_method,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function maskCardNumber(string $cardNumber): string
    {
        return '**** **** **** ' . substr($cardNumber, -4);
    }
}
