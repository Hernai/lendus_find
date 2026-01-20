<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Loan purpose enum.
 *
 * Represents the intended use of the loan funds.
 */
enum LoanPurpose: string
{
    use HasOptions;

    case PERSONAL = 'PERSONAL';
    case DEBT_CONSOLIDATION = 'DEBT_CONSOLIDATION';
    case BUSINESS = 'BUSINESS';
    case MEDICAL = 'MEDICAL';
    case EDUCATION = 'EDUCATION';
    case TRAVEL = 'TRAVEL';
    case HOME_IMPROVEMENT = 'HOME_IMPROVEMENT';
    case OTHER = 'OTHER';

    /**
     * Get human-readable label in Spanish.
     */
    public function label(): string
    {
        return match ($this) {
            self::PERSONAL => 'Gastos personales',
            self::DEBT_CONSOLIDATION => 'Consolidar deudas',
            self::BUSINESS => 'Capital de trabajo / negocio',
            self::MEDICAL => 'Gastos médicos',
            self::EDUCATION => 'Educación',
            self::TRAVEL => 'Viaje',
            self::HOME_IMPROVEMENT => 'Mejoras del hogar',
            self::OTHER => 'Otro',
        };
    }

    /**
     * Get all values as array.
     */
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
            'CONSOLIDACION' => self::DEBT_CONSOLIDATION,
            'NEGOCIO' => self::BUSINESS,
            'MEDICO' => self::MEDICAL,
            'EDUCACION' => self::EDUCATION,
            'VIAJE' => self::TRAVEL,
            'HOGAR' => self::HOME_IMPROVEMENT,
            'OTRO' => self::OTHER,
            default => null,
        };
    }
}
