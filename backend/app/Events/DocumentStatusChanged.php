<?php

namespace App\Events;

use App\Models\Document;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Document $document,
        public string $previousStatus,
        public string $newStatus,
        public ?string $reason = null,
        public ?User $reviewedBy = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $application = $this->document->application;

        return [
            new PrivateChannel("tenant.{$application->tenant_id}.application.{$application->id}"),
            new PrivateChannel("tenant.{$application->tenant_id}.applicant.{$application->applicant_id}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'document.status.changed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'document_id' => $this->document->id,
            'application_id' => $this->document->application_id,
            'type' => $this->document->type,
            'previous_status' => $this->previousStatus,
            'new_status' => $this->newStatus,
            'reason' => $this->reason,
            'reviewed_by' => $this->reviewedBy ? [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ] : null,
            'reviewed_at' => now()->toIso8601String(),
        ];
    }
}
