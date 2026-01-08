<?php

namespace App\Enums;

enum IncomeType: string
{
    case NOMINA = 'NOMINA';
    case HONORARIOS = 'HONORARIOS';
    case MIXTO = 'MIXTO';
    case COMISIONES = 'COMISIONES';
    case NEGOCIO_PROPIO = 'NEGOCIO_PROPIO';
    case PENSION = 'PENSION';
    case OTRO = 'OTRO';

    public function label(): string
    {
        return match ($this) {
            self::NOMINA => 'Nómina',
            self::HONORARIOS => 'Honorarios',
            self::MIXTO => 'Mixto',
            self::COMISIONES => 'Comisiones',
            self::NEGOCIO_PROPIO => 'Negocio propio',
            self::PENSION => 'Pensión',
            self::OTRO => 'Otro',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
