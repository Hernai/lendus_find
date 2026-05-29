<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Payment frequency enum.
 *
 * Uses English values as canonical for consistency across all enums.
 * Includes normalize() method for compatibility with legacy Spanish values.
 */
enum PaymentFrequency: string
{
    use HasOptions;

    case WEEKLY = 'WEEKLY';
    case BIWEEKLY = 'BIWEEKLY';
    case MONTHLY = 'MONTHLY';
    case SINGLE = 'SINGLE';

    /**
     * Get the display label in Spanish.
     */
    public function label(): string
    {
        return match ($this) {
            self::WEEKLY => 'Semanal',
            self::BIWEEKLY => 'Quincenal',
            self::MONTHLY => 'Mensual',
            self::SINGLE => 'Pago único',
        };
    }

    /**
     * Get the number of periods per year.
     * SINGLE (bullet) se trata como 1 periodo en cálculos anualizados.
     */
    public function periodsPerYear(): int
    {
        return match ($this) {
            self::WEEKLY => 52,
            self::BIWEEKLY => 24,
            self::MONTHLY => 12,
            self::SINGLE => 1,
        };
    }

    /**
     * Get all values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Normalize a frequency value to the canonical enum.
     * Handles both English and legacy Spanish values.
     */
    public static function normalize(string $value): ?self
    {
        $normalized = strtoupper(trim($value));

        // Direct match
        $direct = self::tryFrom($normalized);
        if ($direct !== null) {
            return $direct;
        }

        // Map legacy Spanish values to English equivalents
        return match ($normalized) {
            'SEMANAL' => self::WEEKLY,
            'QUINCENAL' => self::BIWEEKLY,
            'MENSUAL' => self::MONTHLY,
            'PAGO_UNICO', 'PAGO UNICO', 'BULLET', 'UNICO' => self::SINGLE,
            default => null,
        };
    }
}
