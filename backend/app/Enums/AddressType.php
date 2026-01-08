<?php

namespace App\Enums;

enum AddressType: string
{
    case HOME = 'HOME';
    case WORK = 'WORK';
    case FISCAL = 'FISCAL';
    case CORRESPONDENCE = 'CORRESPONDENCE';

    public function label(): string
    {
        return match ($this) {
            self::HOME => 'Domicilio Particular',
            self::WORK => 'Domicilio Laboral',
            self::FISCAL => 'Domicilio Fiscal',
            self::CORRESPONDENCE => 'Correspondencia',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
