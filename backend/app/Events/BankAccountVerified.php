<?php

namespace App\Events;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BankAccountVerified implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public BankAccount $bankAccount,
        public string $applicationId,
        public bool $isVerified,
        public ?User $verifiedBy = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->bankAccount->tenant_id}.application.{$this->applicationId}"),
            new PrivateChannel("tenant.{$this->bankAccount->tenant_id}.applicant.{$this->bankAccount->applicant_id}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'bank_account.verified';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'bank_account_id' => $this->bankAccount->id,
            'application_id' => $this->applicationId,
            'applicant_id' => $this->bankAccount->applicant_id,
            'bank_name' => $this->bankAccount->bank_name,
            'is_verified' => $this->isVerified,
            'verified_by' => $this->verifiedBy ? [
                'id' => $this->verifiedBy->id,
                'name' => $this->verifiedBy->name,
            ] : null,
            'verified_at' => now()->toIso8601String(),
        ];
    }
}
