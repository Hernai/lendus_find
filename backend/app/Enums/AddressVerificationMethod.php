<?php

namespace App\Enums;

enum AddressVerificationMethod: string
{
    case DOCUMENT = 'DOCUMENT';
    case GEOLOCATION = 'GEOLOCATION';
    case VISIT = 'VISIT';

    public function label(): string
    {
        return match ($this) {
            self::DOCUMENT => 'Documento',
            self::GEOLOCATION => 'Geolocalización',
            self::VISIT => 'Visita',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
