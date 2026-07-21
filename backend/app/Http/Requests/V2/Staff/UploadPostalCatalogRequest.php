<?php

namespace App\Http\Requests\V2\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validación de la carga del catálogo de códigos postales.
 *
 * La autorización (SUPER_ADMIN) la impone la ruta vía `permission:canConfigureTenant`;
 * aquí solo se valida el archivo: formato ZIP/TXT/CSV y límite de tamaño configurable.
 */
class UploadPostalCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Límite en KB (Laravel usa KB en la regla `max`).
        $maxKb = (int) config('postal_codes.max_upload_mb', 30) * 1024;

        return [
            'file' => [
                'required',
                'file',
                'mimes:zip,txt,csv',
                'max:'.$maxKb,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = (int) config('postal_codes.max_upload_mb', 30);

        return [
            'file.required' => 'Debes adjuntar el archivo del catálogo (ZIP oficial de SEPOMEX o TXT/CSV).',
            'file.file' => 'El archivo adjunto no es válido.',
            'file.mimes' => 'El archivo debe ser ZIP, TXT o CSV.',
            'file.max' => "El archivo excede el límite de {$maxMb} MB.",
        ];
    }

    /**
     * Devuelve el error de validación en el formato de respuesta V2
     * ({success:false, error, message, errors}) en lugar del default de Laravel.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => 'VALIDATION_ERROR',
            'message' => 'Error de validación',
            'errors' => $validator->errors()->toArray(),
        ], 422, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
