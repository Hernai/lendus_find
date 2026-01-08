<?php

namespace App\Enums;

enum MaritalStatus: string
{
    case SOLTERO = 'SOLTERO';
    case CASADO = 'CASADO';
    case UNION_LIBRE = 'UNION_LIBRE';
    case DIVORCIADO = 'DIVORCIADO';
    case VIUDO = 'VIUDO';
    case SEPARADO = 'SEPARADO';

    public function label(): string
    {
        return match ($this) {
            self::SOLTERO => 'Soltero/a',
            self::CASADO => 'Casado/a',
            self::UNION_LIBRE => 'Unión Libre',
            self::DIVORCIADO => 'Divorciado/a',
            self::VIUDO => 'Viudo/a',
            self::SEPARADO => 'Separado/a',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
