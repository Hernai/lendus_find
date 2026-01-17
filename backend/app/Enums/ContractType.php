<?php

namespace App\Enums;

/**
 * Contract type enum.
 *
 * Uses English values as canonical for consistency across all enums.
 * Includes normalize() method for compatibility with legacy Spanish values.
 */
enum ContractType: string
{
    case PERMANENT = 'PERMANENT';
    case TEMPORARY = 'TEMPORARY';
    case PROJECT_BASED = 'PROJECT_BASED';
    case FREELANCE = 'FREELANCE';
    case COMMISSION = 'COMMISSION';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::PERMANENT => 'Indefinido',
            self::TEMPORARY => 'Temporal',
            self::PROJECT_BASED => 'Por obra',
            self::FREELANCE => 'Honorarios',
            self::COMMISSION => 'Comisión',
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
            'INDEFINIDO' => self::PERMANENT,
            'TEMPORAL' => self::TEMPORARY,
            'POR_OBRA' => self::PROJECT_BASED,
            'HONORARIOS' => self::FREELANCE,
            'COMISION' => self::COMMISSION,
            'OTRO' => self::OTHER,
            default => null,
        };
    }
}
