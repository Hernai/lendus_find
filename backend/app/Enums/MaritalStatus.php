<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Marital status enum.
 *
 * Uses English values as canonical for consistency across all enums.
 * Includes normalize() method for compatibility with legacy Spanish values.
 */
enum MaritalStatus: string
{
    use HasOptions;
    case SINGLE = 'SINGLE';
    case MARRIED = 'MARRIED';
    case COMMON_LAW = 'COMMON_LAW';
    case DIVORCED = 'DIVORCED';
    case WIDOWED = 'WIDOWED';
    case SEPARATED = 'SEPARATED';

    public function label(): string
    {
        return match ($this) {
            self::SINGLE => 'Soltero/a',
            self::MARRIED => 'Casado/a',
            self::COMMON_LAW => 'Unión Libre',
            self::DIVORCED => 'Divorciado/a',
            self::WIDOWED => 'Viudo/a',
            self::SEPARATED => 'Separado/a',
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
            'SOLTERO' => self::SINGLE,
            'CASADO' => self::MARRIED,
            'UNION_LIBRE' => self::COMMON_LAW,
            'DIVORCIADO' => self::DIVORCED,
            'VIUDO' => self::WIDOWED,
            'SEPARADO' => self::SEPARATED,
            default => null,
        };
    }
}
