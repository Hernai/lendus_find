<?php

namespace App\Events;

use App\Models\NotificationLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public NotificationLog $log
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->log->tenant_id}.applicant.{$this->log->recipient_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.received';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->log->id,
            'event' => $this->log->event instanceof \BackedEnum ? $this->log->event->value : $this->log->event,
            'subject' => $this->log->subject,
            'body' => $this->log->body,
            'created_at' => $this->log->created_at->toISOString(),
            'metadata' => $this->log->metadata,
        ];
    }
}
