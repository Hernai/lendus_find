<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Employment type enum.
 *
 * Uses English values as canonical for consistency across all enums.
 * Includes normalize() method for compatibility with legacy Spanish values.
 */
enum EmploymentType: string
{
    use HasOptions;
    case EMPLOYEE = 'EMPLOYEE';
    case SELF_EMPLOYED = 'SELF_EMPLOYED';
    case BUSINESS_OWNER = 'BUSINESS_OWNER';
    case RETIRED = 'RETIRED';
    case STUDENT = 'STUDENT';
    case HOMEMAKER = 'HOMEMAKER';
    case UNEMPLOYED = 'UNEMPLOYED';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::EMPLOYEE => 'Empleado',
            self::SELF_EMPLOYED => 'Trabajador Independiente',
            self::BUSINESS_OWNER => 'Empresario',
            self::RETIRED => 'Pensionado',
            self::STUDENT => 'Estudiante',
            self::HOMEMAKER => 'Hogar',
            self::UNEMPLOYED => 'Desempleado',
            self::OTHER => 'Otro',
        };
    }

    /**
     * Check if employment type requires proof of income.
     */
    public function requiresProofOfIncome(): bool
    {
        return in_array($this, [
            self::EMPLOYEE,
            self::SELF_EMPLOYED,
            self::BUSINESS_OWNER,
            self::RETIRED,
        ]);
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
            'EMPLEADO' => self::EMPLOYEE,
            'INDEPENDIENTE' => self::SELF_EMPLOYED,
            'EMPRESARIO' => self::BUSINESS_OWNER,
            'PENSIONADO' => self::RETIRED,
            'ESTUDIANTE' => self::STUDENT,
            'HOGAR' => self::HOMEMAKER,
            'DESEMPLEADO' => self::UNEMPLOYED,
            'OTRO' => self::OTHER,
            default => null,
        };
    }
}
