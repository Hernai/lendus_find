<?php

namespace App\Enums;

/**
 * Payment frequency options for loan products.
 *
 * Uses Spanish values as canonical (SEMANAL, QUINCENAL, MENSUAL)
 * with helper methods to normalize from English equivalents.
 */
enum PaymentFrequency: string
{
    case SEMANAL = 'SEMANAL';
    case QUINCENAL = 'QUINCENAL';
    case MENSUAL = 'MENSUAL';

    /**
     * Get the display label in Spanish.
     */
    public function label(): string
    {
        return match ($this) {
            self::SEMANAL => 'Semanal',
            self::QUINCENAL => 'Quincenal',
            self::MENSUAL => 'Mensual',
        };
    }

    /**
     * Get the number of periods per year.
     */
    public function periodsPerYear(): int
    {
        return match ($this) {
            self::SEMANAL => 52,
            self::QUINCENAL => 24,
            self::MENSUAL => 12,
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
     * Handles both Spanish and English values.
     */
    public static function normalize(string $value): ?self
    {
        $normalized = strtoupper(trim($value));

        // Direct match
        $direct = self::tryFrom($normalized);
        if ($direct !== null) {
            return $direct;
        }

        // Map English values to Spanish equivalents
        return match ($normalized) {
            'WEEKLY' => self::SEMANAL,
            'BIWEEKLY' => self::QUINCENAL,
            'MONTHLY' => self::MENSUAL,
            default => null,
        };
    }
}
