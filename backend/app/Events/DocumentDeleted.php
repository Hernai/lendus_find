<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentDeleted implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $documentId,
        public string $documentType,
        public string $applicationId,
        public string $applicantId,
        public string $tenantId,
        public ?User $deletedBy = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.application.{$this->applicationId}"),
            new PrivateChannel("tenant.{$this->tenantId}.applicant.{$this->applicantId}"),
            new PrivateChannel("tenant.{$this->tenantId}.admin"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'document.deleted';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'document_id' => $this->documentId,
            'application_id' => $this->applicationId,
            'applicant_id' => $this->applicantId,
            'type' => $this->documentType,
            'deleted_by' => $this->deletedBy ? [
                'id' => $this->deletedBy->id,
                'name' => $this->deletedBy->name,
            ] : null,
            'deleted_at' => now()->toIso8601String(),
        ];
    }
}
