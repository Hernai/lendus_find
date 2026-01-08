<?php

namespace App\Events;

use App\Models\Reference;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReferenceVerified implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Reference $reference,
        public string $result,
        public ?string $notes = null,
        public ?User $verifiedBy = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $application = $this->reference->application;

        return [
            new PrivateChannel("tenant.{$application->tenant_id}.application.{$application->id}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'reference.verified';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'reference_id' => $this->reference->id,
            'application_id' => $this->reference->application_id,
            'full_name' => $this->reference->full_name,
            'result' => $this->result,
            'is_verified' => $this->reference->is_verified,
            'notes' => $this->notes,
            'verified_by' => $this->verifiedBy ? [
                'id' => $this->verifiedBy->id,
                'name' => $this->verifiedBy->name,
            ] : null,
            'verified_at' => now()->toIso8601String(),
        ];
    }
}
