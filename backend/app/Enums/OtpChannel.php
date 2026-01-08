<?php

namespace App\Enums;

enum OtpChannel: string
{
    case SMS = 'SMS';
    case WHATSAPP = 'WHATSAPP';
    case EMAIL = 'EMAIL';

    public function label(): string
    {
        return match ($this) {
            self::SMS => 'SMS',
            self::WHATSAPP => 'WhatsApp',
            self::EMAIL => 'Correo Electrónico',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
