<?php

namespace App\Enums;

enum HousingType: string
{
    case PROPIA_PAGADA = 'PROPIA_PAGADA';
    case PROPIA_HIPOTECA = 'PROPIA_HIPOTECA';
    case RENTADA = 'RENTADA';
    case FAMILIAR = 'FAMILIAR';
    case PRESTADA = 'PRESTADA';
    case OTRO = 'OTRO';

    public function label(): string
    {
        return match ($this) {
            self::PROPIA_PAGADA => 'Propia (pagada)',
            self::PROPIA_HIPOTECA => 'Propia (con hipoteca)',
            self::RENTADA => 'Rentada',
            self::FAMILIAR => 'Familiar',
            self::PRESTADA => 'Prestada',
            self::OTRO => 'Otro',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
