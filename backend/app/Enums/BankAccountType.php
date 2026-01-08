<?php

namespace App\Enums;

enum BankAccountType: string
{
    case DEBITO = 'DEBITO';
    case NOMINA = 'NOMINA';
    case AHORRO = 'AHORRO';
    case CHEQUES = 'CHEQUES';
    case INVERSION = 'INVERSION';
    case OTRO = 'OTRO';

    public function label(): string
    {
        return match ($this) {
            self::DEBITO => 'Débito',
            self::NOMINA => 'Nómina',
            self::AHORRO => 'Ahorro',
            self::CHEQUES => 'Cheques',
            self::INVERSION => 'Inversión',
            self::OTRO => 'Otro',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
