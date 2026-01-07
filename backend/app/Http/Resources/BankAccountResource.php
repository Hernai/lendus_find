<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => $this->type,
            'is_primary' => $this->is_primary,

            // Bank info
            'bank_name' => $this->bank_name,
            'bank_code' => $this->bank_code,

            // Account details (CLABE masked for security)
            'clabe' => $this->maskedClabe(),
            'clabe_last4' => substr($this->clabe, -4),
            'account_number' => $this->account_number ? $this->maskAccountNumber($this->account_number) : null,
            'card_number_last4' => $this->card_number_last4,
            'account_type' => $this->account_type,

            // Holder info
            'holder_name' => $this->holder_name,
            'holder_rfc' => $this->holder_rfc,
            'is_own_account' => $this->is_own_account,

            // Verification
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verification_method' => $this->verification_method,

            // Status
            'is_active' => $this->is_active,
            'deactivated_at' => $this->deactivated_at?->toIso8601String(),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Mask CLABE for display (show first 3 and last 4).
     */
    protected function maskedClabe(): string
    {
        if (strlen($this->clabe) !== 18) {
            return $this->clabe;
        }

        return substr($this->clabe, 0, 3) . '***********' . substr($this->clabe, -4);
    }

    /**
     * Mask account number.
     */
    protected function maskAccountNumber(string $accountNumber): string
    {
        $length = strlen($accountNumber);
        if ($length <= 4) {
            return $accountNumber;
        }

        return str_repeat('*', $length - 4) . substr($accountNumber, -4);
    }
}
