<?php

namespace App\Events;

use App\Models\Application;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationAssigned implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Application $application,
        public User $assignedTo,
        public ?User $assignedBy = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            // Al analista asignado
            new PrivateChannel("tenant.{$this->application->tenant_id}.user.{$this->assignedTo->id}"),

            // Al canal de la aplicación
            new PrivateChannel("tenant.{$this->application->tenant_id}.application.{$this->application->id}"),

            // Al dashboard admin
            new PrivateChannel("tenant.{$this->application->tenant_id}.admin"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'application.assigned';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'application_id' => $this->application->id,
            'folio' => $this->application->folio,
            'assigned_to' => [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ],
            'assigned_by' => $this->assignedBy ? [
                'id' => $this->assignedBy->id,
                'name' => $this->assignedBy->name,
            ] : null,
            'assigned_at' => now()->toIso8601String(),
        ];
    }
}
