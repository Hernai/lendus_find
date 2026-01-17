<?php

namespace App\Enums;

/**
 * Applicant type enum.
 *
 * Uses English values as canonical for consistency across all enums.
 * Includes normalize() method for compatibility with legacy Spanish values.
 */
enum ApplicantType: string
{
    case INDIVIDUAL = 'INDIVIDUAL';
    case BUSINESS = 'BUSINESS';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Persona Física',
            self::BUSINESS => 'Persona Moral',
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
            'PERSONA_FISICA' => self::INDIVIDUAL,
            'PERSONA_MORAL' => self::BUSINESS,
            default => null,
        };
    }
}
