<?php

namespace App\Http\Requests\V2\Applicant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for address updates.
 */
class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'street' => 'required|string|max:255',
            'ext_number' => 'sometimes|string|max:20',
            'exterior_number' => 'sometimes|string|max:20', // Alias
            'int_number' => 'nullable|string|max:20',
            'interior_number' => 'nullable|string|max:20', // Alias
            'neighborhood' => 'required|string|max:100',
            'municipality' => 'sometimes|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'required|string|max:50',
            'postal_code' => 'required|string|size:5|regex:/^[0-9]{5}$/',
            'country' => 'nullable|string|max:3',
            'housing_type' => ['required', Rule::in(\App\Enums\HousingType::values())],
            'years_at_address' => 'nullable|integer|min:0|max:100',
            'months_at_address' => 'nullable|integer|min:0|max:11',
            'monthly_rent' => 'nullable|numeric|min:0',
            'between_streets' => 'nullable|string|max:255',
            'references' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'street.required' => 'La calle es requerida.',
            'street.max' => 'La calle no puede exceder 255 caracteres.',
            'neighborhood.required' => 'La colonia es requerida.',
            'state.required' => 'El estado es requerido.',
            'postal_code.required' => 'El código postal es requerido.',
            'postal_code.size' => 'El código postal debe tener 5 dígitos.',
            'postal_code.regex' => 'El código postal debe contener solo números.',
            'housing_type.required' => 'El tipo de vivienda es requerido.',
            'housing_type.in' => 'El tipo de vivienda seleccionado no es válido.',
            'years_at_address.min' => 'Los años en el domicilio no pueden ser negativos.',
            'months_at_address.min' => 'Los meses en el domicilio no pueden ser negativos.',
            'months_at_address.max' => 'Los meses en el domicilio no pueden exceder 11.',
        ];
    }
}
