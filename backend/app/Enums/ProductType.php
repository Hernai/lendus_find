<?php

namespace App\Enums;

enum ProductType: string
{
    case PERSONAL = 'PERSONAL';
    case PAYROLL = 'PAYROLL';
    case SME = 'SME';
    case LEASING = 'LEASING';
    case FACTORING = 'FACTORING';

    public function label(): string
    {
        return match ($this) {
            self::PERSONAL => 'Crédito Personal',
            self::PAYROLL => 'Crédito Nómina',
            self::SME => 'Crédito PyME',
            self::LEASING => 'Arrendamiento',
            self::FACTORING => 'Factoraje',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PERSONAL => 'user',
            self::PAYROLL => 'briefcase',
            self::SME => 'building',
            self::LEASING => 'truck',
            self::FACTORING => 'file-text',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
