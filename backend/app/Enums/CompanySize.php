<?php

namespace App\Enums;

/**
 * Company size enum.
 *
 * Uses English values as canonical for consistency across all enums.
 * Includes normalize() method for compatibility with legacy Spanish values.
 */
enum CompanySize: string
{
    case MICRO = 'MICRO';
    case SMALL = 'SMALL';
    case MEDIUM = 'MEDIUM';
    case LARGE = 'LARGE';

    public function label(): string
    {
        return match ($this) {
            self::MICRO => 'Microempresa',
            self::SMALL => 'Pequeña',
            self::MEDIUM => 'Mediana',
            self::LARGE => 'Grande',
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
            'PEQUENA' => self::SMALL,
            'MEDIANA' => self::MEDIUM,
            'GRANDE' => self::LARGE,
            default => null,
        };
    }
}
