<?php

namespace App\Enums;

enum CompanySize: string
{
    case MICRO = 'MICRO';
    case PEQUENA = 'PEQUENA';
    case MEDIANA = 'MEDIANA';
    case GRANDE = 'GRANDE';

    public function label(): string
    {
        return match ($this) {
            self::MICRO => 'Microempresa',
            self::PEQUENA => 'Pequeña',
            self::MEDIANA => 'Mediana',
            self::GRANDE => 'Grande',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
