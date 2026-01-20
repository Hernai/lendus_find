<?php

namespace App\Enums;

use App\Enums\Traits\HasOptions;

/**
 * Bank account type enum.
 *
 * Uses English values as canonical for consistency across all enums.
 * Includes normalize() method for compatibility with legacy Spanish values.
 */
enum BankAccountType: string
{
    use HasOptions;

    case DEBIT = 'DEBIT';
    case PAYROLL = 'PAYROLL';
    case SAVINGS = 'SAVINGS';
    case CHECKING = 'CHECKING';
    case INVESTMENT = 'INVESTMENT';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::DEBIT => 'Débito',
            self::PAYROLL => 'Nómina',
            self::SAVINGS => 'Ahorro',
            self::CHECKING => 'Cheques',
            self::INVESTMENT => 'Inversión',
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
            'DEBITO' => self::DEBIT,
            'NOMINA' => self::PAYROLL,
            'AHORRO' => self::SAVINGS,
            'CHEQUES' => self::CHECKING,
            'INVERSION' => self::INVESTMENT,
            'OTRO' => self::OTHER,
            default => null,
        };
    }
}
