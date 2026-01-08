<?php

namespace App\Enums;

enum EmploymentVerificationMethod: string
{
    case RECIBO_NOMINA = 'RECIBO_NOMINA';
    case CONSTANCIA = 'CONSTANCIA';
    case LLAMADA = 'LLAMADA';

    public function label(): string
    {
        return match ($this) {
            self::RECIBO_NOMINA => 'Recibo de nómina',
            self::CONSTANCIA => 'Constancia laboral',
            self::LLAMADA => 'Llamada',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
