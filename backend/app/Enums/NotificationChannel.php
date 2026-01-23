<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Notification delivery channels.
 */
enum NotificationChannel: string
{
    use HasOptions;

    case SMS = 'SMS';
    case WHATSAPP = 'WHATSAPP';
    case EMAIL = 'EMAIL';
    case IN_APP = 'IN_APP';

    public function label(): string
    {
        return match ($this) {
            self::SMS => 'SMS',
            self::WHATSAPP => 'WhatsApp',
            self::EMAIL => 'Correo Electrónico',
            self::IN_APP => 'Notificación Interna',
        };
    }

    /**
     * Check if channel supports HTML content.
     */
    public function supportsHtml(): bool
    {
        return match ($this) {
            self::EMAIL, self::IN_APP => true,
            default => false,
        };
    }

    /**
     * Check if channel requires subject line.
     */
    public function requiresSubject(): bool
    {
        return $this === self::EMAIL;
    }

    /**
     * Get character limit for this channel.
     */
    public function characterLimit(): ?int
    {
        return match ($this) {
            self::SMS => 160,
            self::WHATSAPP => 4096,
            default => null,
        };
    }
}
