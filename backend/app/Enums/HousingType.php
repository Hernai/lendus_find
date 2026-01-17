<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Housing type enum.
 *
 * Uses English values as canonical for consistency across all enums.
 * Includes normalize() method for compatibility with legacy Spanish values.
 */
enum HousingType: string
{
    use HasOptions;
    case OWNED_PAID = 'OWNED_PAID';
    case OWNED_MORTGAGE = 'OWNED_MORTGAGE';
    case RENTED = 'RENTED';
    case FAMILY = 'FAMILY';
    case BORROWED = 'BORROWED';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::OWNED_PAID => 'Propia (pagada)',
            self::OWNED_MORTGAGE => 'Propia (con hipoteca)',
            self::RENTED => 'Rentada',
            self::FAMILY => 'Familiar',
            self::BORROWED => 'Prestada',
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
            'PROPIA_PAGADA' => self::OWNED_PAID,
            'PROPIA_HIPOTECA' => self::OWNED_MORTGAGE,
            'RENTADA' => self::RENTED,
            'FAMILIAR' => self::FAMILY,
            'PRESTADA' => self::BORROWED,
            'OTRO' => self::OTHER,
            default => null,
        };
    }
}
