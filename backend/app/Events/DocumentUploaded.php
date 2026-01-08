<?php

namespace App\Events;

use App\Models\Document;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentUploaded implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Document $document,
        public ?User $uploadedBy = null
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
            new PrivateChannel("tenant.{$application->tenant_id}.admin"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'document.uploaded';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $type = $this->document->type;

        return [
            'document_id' => $this->document->id,
            'application_id' => $this->document->application_id,
            'applicant_id' => $this->document->applicant_id,
            'type' => $type instanceof \App\Enums\DocumentType ? $type->value : $type,
            'status' => $this->document->status?->value ?? 'PENDING',
            'uploaded_by' => $this->uploadedBy ? [
                'id' => $this->uploadedBy->id,
                'name' => $this->uploadedBy->name,
            ] : null,
            'uploaded_at' => now()->toIso8601String(),
        ];
    }
}
