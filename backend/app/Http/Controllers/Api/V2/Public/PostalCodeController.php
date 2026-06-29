<?php

namespace App\Http\Controllers\Api\V2\Public;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\PostalCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * GET /api/v2/public/postal-codes/{cp}
 *
 * Devuelve estado, municipio, ciudad y las colonias (asentamientos) de un
 * código postal de 5 dígitos, a partir del catálogo SEPOMEX (tabla
 * postal_codes). Catálogo estático → se cachea por CP.
 */
class PostalCodeController extends Controller
{
    use ApiResponses;

    public function show(string $cp): JsonResponse
    {
        $cp = preg_replace('/\D/', '', $cp);
        if (strlen($cp) !== 5) {
            return $this->validationError('Código postal inválido', [
                'cp' => ['El código postal debe tener 5 dígitos.'],
            ]);
        }

        $data = Cache::remember("postal_code:{$cp}", now()->addDay(), function () use ($cp) {
            $rows = PostalCode::where('cp', $cp)->orderBy('asentamiento')->get();
            if ($rows->isEmpty()) {
                return null;
            }

            $first = $rows->first();

            return [
                'cp' => $cp,
                'estado' => $first->estado,
                'municipio' => $first->municipio,
                'ciudad' => $first->ciudad,
                'colonias' => $rows->map(fn ($r) => [
                    'nombre' => $r->asentamiento,
                    'tipo' => $r->tipo_asentamiento,
                ])->values(),
            ];
        });

        if ($data === null) {
            return $this->notFound('Código postal no encontrado.');
        }

        return $this->success($data);
    }
}
