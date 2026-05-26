<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Rangos de ingreso mensual para onboarding minimalista (estilo
 * MoneyCapital). En lugar de pedir un número exacto se le da al
 * solicitante un menú de rangos.
 */
enum SalaryRange: string
{
    use HasOptions;

    case LT_3000 = 'LT_3000';
    case R_3001_6000 = 'R_3001_6000';
    case R_6001_9000 = 'R_6001_9000';
    case R_9001_12000 = 'R_9001_12000';
    case R_12001_15000 = 'R_12001_15000';
    case GT_15000 = 'GT_15000';

    public function label(): string
    {
        return match ($this) {
            self::LT_3000 => 'Menos de $3,000',
            self::R_3001_6000 => '$3,001 - $6,000',
            self::R_6001_9000 => '$6,001 - $9,000',
            self::R_9001_12000 => '$9,001 - $12,000',
            self::R_12001_15000 => '$12,001 - $15,000',
            self::GT_15000 => 'Más de $15,000',
        };
    }

    /**
     * Punto medio aproximado del rango — útil para el motor de decisión.
     */
    public function midpoint(): float
    {
        return match ($this) {
            self::LT_3000 => 2000,
            self::R_3001_6000 => 4500,
            self::R_6001_9000 => 7500,
            self::R_9001_12000 => 10500,
            self::R_12001_15000 => 13500,
            self::GT_15000 => 20000,
        };
    }
}
