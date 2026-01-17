<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\BankAccount;

/**
 * Custom validation rule for Mexican CLABE (Clave Bancaria Estandarizada).
 *
 * CLABE is an 18-digit standardized banking code in Mexico.
 * This rule validates the format and check digit using the Luhn-like algorithm.
 */
class ValidClabe implements ValidationRule
{
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

        // Validate using model's method
        if (!BankAccount::validateClabe($value)) {
            $fail('La :attribute tiene un dígito verificador inválido.');
        }
    }
}
