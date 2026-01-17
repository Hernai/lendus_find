<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Education level enum.
 *
 * Uses English values as canonical for consistency across all enums.
 * Includes normalize() method for compatibility with legacy Spanish values.
 */
enum EducationLevel: string
{
    use HasOptions;
    case PRIMARY = 'PRIMARY';
    case SECONDARY = 'SECONDARY';
    case HIGH_SCHOOL = 'HIGH_SCHOOL';
    case TECHNICAL = 'TECHNICAL';
    case BACHELOR = 'BACHELOR';
    case MASTER = 'MASTER';
    case DOCTORATE = 'DOCTORATE';

    public function label(): string
    {
        return match ($this) {
            self::PRIMARY => 'Primaria',
            self::SECONDARY => 'Secundaria',
            self::HIGH_SCHOOL => 'Preparatoria',
            self::TECHNICAL => 'Técnico',
            self::BACHELOR => 'Licenciatura',
            self::MASTER => 'Maestría',
            self::DOCTORATE => 'Doctorado',
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
            'PRIMARIA' => self::PRIMARY,
            'SECUNDARIA' => self::SECONDARY,
            'PREPARATORIA' => self::HIGH_SCHOOL,
            'TECNICO' => self::TECHNICAL,
            'LICENCIATURA' => self::BACHELOR,
            'MAESTRIA' => self::MASTER,
            'DOCTORADO' => self::DOCTORATE,
            default => null,
        };
    }
}
