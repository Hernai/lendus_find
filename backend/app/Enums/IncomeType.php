<?php

namespace App\Enums;

/**
 * Income type enum.
 *
 * Uses English values as canonical for consistency across all enums.
 * Includes normalize() method for compatibility with legacy Spanish values.
 */
enum IncomeType: string
{
    case SALARY = 'SALARY';
    case FREELANCE = 'FREELANCE';
    case MIXED = 'MIXED';
    case COMMISSION = 'COMMISSION';
    case SELF_EMPLOYED = 'SELF_EMPLOYED';
    case PENSION = 'PENSION';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::SALARY => 'Nómina',
            self::FREELANCE => 'Honorarios',
            self::MIXED => 'Mixto',
            self::COMMISSION => 'Comisiones',
            self::SELF_EMPLOYED => 'Negocio propio',
            self::PENSION => 'Pensión',
            self::OTHER => 'Otro',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Normalize a value to the canonical enum.
     * Handles both English and legacy Spanish values.
     */
    public static function normalize(string $value): ?self
    {
        $normalized = strtoupper(trim($value));

        $direct = self::tryFrom($normalized);
        if ($direct !== null) {
            return $direct;
        }

        return match ($normalized) {
            'NOMINA' => self::SALARY,
            'HONORARIOS' => self::FREELANCE,
            'MIXTO' => self::MIXED,
            'COMISIONES' => self::COMMISSION,
            'NEGOCIO_PROPIO' => self::SELF_EMPLOYED,
            'OTRO' => self::OTHER,
            default => null,
        };
    }
}
