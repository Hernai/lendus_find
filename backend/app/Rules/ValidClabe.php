<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom validation rule for Mexican CLABE (Clave Bancaria Estandarizada).
 *
 * CLABE is an 18-digit standardized banking code in Mexico.
 * This rule validates the format and check digit using the Luhn-like algorithm.
 */
class ValidClabe implements ValidationRule
{
    /**
     * CLABE validation weights.
     */
    private const WEIGHTS = [3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7];

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Must be 18 digits
        if (!preg_match('/^\d{18}$/', $value)) {
            $fail('La :attribute debe contener exactamente 18 dígitos numéricos.');
            return;
        }

        // Validate check digit
        if (!$this->validateCheckDigit($value)) {
            $fail('La :attribute tiene un dígito verificador inválido.');
        }
    }

    /**
     * Validate CLABE check digit using the weighted sum algorithm.
     */
    private function validateCheckDigit(string $clabe): bool
    {
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $digit = (int) $clabe[$i];
            $product = ($digit * self::WEIGHTS[$i]) % 10;
            $sum += $product;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit === (int) $clabe[17];
    }
}
