<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

enum ProductType: string
{
    use HasOptions;

    case PERSONAL = 'PERSONAL';
    case AUTO = 'AUTO';
    case HIPOTECARIO = 'HIPOTECARIO';
    case PYME = 'PYME';
    case NOMINA = 'NOMINA';
    case ARRENDAMIENTO = 'ARRENDAMIENTO';

    public function label(): string
    {
        return match ($this) {
            self::PERSONAL => 'Crédito Personal',
            self::AUTO => 'Crédito Automotriz',
            self::HIPOTECARIO => 'Crédito Hipotecario',
            self::PYME => 'Crédito PyME',
            self::NOMINA => 'Crédito Nómina',
            self::ARRENDAMIENTO => 'Arrendamiento',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PERSONAL => 'user',
            self::AUTO => 'car',
            self::HIPOTECARIO => 'home',
            self::PYME => 'building',
            self::NOMINA => 'banknotes',
            self::ARRENDAMIENTO => 'key',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
