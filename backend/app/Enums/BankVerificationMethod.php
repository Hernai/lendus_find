<?php

namespace App\Enums;

enum BankVerificationMethod: string
{
    case SPEI_VALIDATION = 'SPEI_VALIDATION';
    case PENNY_TEST = 'PENNY_TEST';
    case MANUAL = 'MANUAL';

    public function label(): string
    {
        return match ($this) {
            self::SPEI_VALIDATION => 'Validación SPEI',
            self::PENNY_TEST => 'Prueba de centavos',
            self::MANUAL => 'Manual',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
