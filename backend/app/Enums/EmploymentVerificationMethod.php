<?php

namespace App\Enums;

/**
 * Employment verification method enum.
 *
 * Uses English values as canonical for consistency across all enums.
 * Includes normalize() method for compatibility with legacy Spanish values.
 */
enum EmploymentVerificationMethod: string
{
    case PAYSLIP = 'PAYSLIP';
    case EMPLOYMENT_LETTER = 'EMPLOYMENT_LETTER';
    case PHONE_CALL = 'PHONE_CALL';

    public function label(): string
    {
        return match ($this) {
            self::PAYSLIP => 'Recibo de nómina',
            self::EMPLOYMENT_LETTER => 'Constancia laboral',
            self::PHONE_CALL => 'Llamada',
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
            'RECIBO_NOMINA' => self::PAYSLIP,
            'CONSTANCIA' => self::EMPLOYMENT_LETTER,
            'LLAMADA' => self::PHONE_CALL,
            default => null,
        };
    }
}
