<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Identification document type enum.
 *
 * Represents the type of government-issued ID document.
 */
enum IdType: string
{
    use HasOptions;

    case INE = 'INE';
    case PASSPORT = 'PASSPORT';

    /**
     * Get human-readable label in Spanish.
     */
    public function label(): string
    {
        return match ($this) {
            self::INE => 'INE/IFE',
            self::PASSPORT => 'Pasaporte',
        };
    }

    /**
     * Get all values as array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
