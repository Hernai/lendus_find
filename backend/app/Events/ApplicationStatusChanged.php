<?php

namespace App\Events;

use App\Models\Application;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Application $application,
        public string $previousStatus,
        public string $newStatus,
        public ?string $reason = null,
        public ?User $changedBy = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            // Canal de la aplicación específica (para admins viendo esta app)
            new PrivateChannel("tenant.{$this->application->tenant_id}.application.{$this->application->id}"),

            // Canal personal del aplicante (para su dashboard)
            new PrivateChannel("tenant.{$this->application->tenant_id}.applicant.{$this->application->applicant_id}"),

            // Canal admin del tenant (para dashboard de admins)
            new PrivateChannel("tenant.{$this->application->tenant_id}.admin"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'application.status.changed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'application_id' => $this->application->id,
            'folio' => $this->application->folio,
            'applicant_id' => $this->application->applicant_id,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'reason' => $this->reason,
            'changed_by' => $this->changedBy ? [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->name,
            ] : null,
            'changed_at' => now()->toIso8601String(),
        ];
    }
}
