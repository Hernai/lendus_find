<?php

namespace App\Helpers;

/**
 * Phone number normalization utilities for Mexican phone numbers.
 *
 * Mexican mobile numbers are 10 digits. This helper handles various formats:
 * - Raw 10 digits: 5512345678
 * - With country code: 525512345678, +525512345678
 * - With separators: 55-1234-5678, (55) 1234-5678
 */
class PhoneNormalizer
{
    /**
     * Normalize a phone number to 10 digits (Mexican mobile format).
     *
     * @param string|null $phone The phone number to normalize
     * @return string|null The normalized 10-digit phone number, or null if invalid
     */
    public static function normalize(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Remove all non-numeric characters
        $digits = preg_replace('/[^0-9]/', '', $phone);

        // If we have more than 10 digits, take the last 10 (strip country code)
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        // Return only if we have exactly 10 digits
        return strlen($digits) === 10 ? $digits : null;
    }

    /**
     * Format a phone number for display.
     *
     * @param string|null $phone The phone number to format
     * @param string $format The format to use: 'dashes', 'spaces', 'parentheses'
     * @return string|null The formatted phone number
     */
    public static function format(?string $phone, string $format = 'dashes'): ?string
    {
        $normalized = self::normalize($phone);
        if (!$normalized) {
            return $phone; // Return original if can't normalize
        }

        return match ($format) {
            'dashes' => substr($normalized, 0, 2) . '-' . substr($normalized, 2, 4) . '-' . substr($normalized, 6),
            'spaces' => substr($normalized, 0, 2) . ' ' . substr($normalized, 2, 4) . ' ' . substr($normalized, 6),
            'parentheses' => '(' . substr($normalized, 0, 2) . ') ' . substr($normalized, 2, 4) . '-' . substr($normalized, 6),
            default => $normalized,
        };
    }

    /**
     * Add Mexico country code to a phone number.
     *
     * @param string|null $phone The phone number
     * @param bool $withPlus Whether to include the + prefix
     * @return string|null The phone with country code
     */
    public static function withCountryCode(?string $phone, bool $withPlus = true): ?string
    {
        $normalized = self::normalize($phone);
        if (!$normalized) {
            return null;
        }

        return ($withPlus ? '+' : '') . '52' . $normalized;
    }

    /**
     * Check if two phone numbers are equivalent (same after normalization).
     *
     * @param string|null $phone1 First phone number
     * @param string|null $phone2 Second phone number
     * @return bool Whether the phones match
     */
    public static function areEqual(?string $phone1, ?string $phone2): bool
    {
        $n1 = self::normalize($phone1);
        $n2 = self::normalize($phone2);

        if ($n1 === null || $n2 === null) {
            return false;
        }

        return $n1 === $n2;
    }

    /**
     * Validate that a phone number is a valid Mexican mobile.
     *
     * @param string|null $phone The phone number to validate
     * @return bool Whether the phone is valid
     */
    public static function isValid(?string $phone): bool
    {
        $normalized = self::normalize($phone);
        return $normalized !== null && strlen($normalized) === 10;
    }

    /**
     * Mask a phone number for display (e.g., 55****5678).
     *
     * @param string|null $phone The phone number to mask
     * @param int $visibleStart Number of digits to show at start
     * @param int $visibleEnd Number of digits to show at end
     * @return string|null The masked phone number
     */
    public static function mask(?string $phone, int $visibleStart = 2, int $visibleEnd = 4): ?string
    {
        $normalized = self::normalize($phone);
        if (!$normalized) {
            return $phone ? '****' : null;
        }

        $maskLength = 10 - $visibleStart - $visibleEnd;
        if ($maskLength < 0) {
            $maskLength = 0;
        }

        return substr($normalized, 0, $visibleStart)
            . str_repeat('*', $maskLength)
            . substr($normalized, -$visibleEnd);
    }
}
