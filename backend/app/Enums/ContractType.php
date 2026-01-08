<?php

namespace App\Enums;

enum ContractType: string
{
    case INDEFINIDO = 'INDEFINIDO';
    case TEMPORAL = 'TEMPORAL';
    case POR_OBRA = 'POR_OBRA';
    case HONORARIOS = 'HONORARIOS';
    case COMISION = 'COMISION';
    case OTRO = 'OTRO';

    public function label(): string
    {
        return match ($this) {
            self::INDEFINIDO => 'Indefinido',
            self::TEMPORAL => 'Temporal',
            self::POR_OBRA => 'Por obra',
            self::HONORARIOS => 'Honorarios',
            self::COMISION => 'Comisión',
            self::OTRO => 'Otro',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
