<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case PENDING = 'PENDING';
    case VERIFIED = 'VERIFIED';
    case REJECTED = 'REJECTED';
    case CORRECTED = 'CORRECTED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::VERIFIED => 'Verificado',
            self::REJECTED => 'Rechazado',
            self::CORRECTED => 'Corregido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::VERIFIED => 'green',
            self::REJECTED => 'red',
            self::CORRECTED => 'blue',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
