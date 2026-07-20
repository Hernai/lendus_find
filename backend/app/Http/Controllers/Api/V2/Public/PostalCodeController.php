<?php

namespace App\Http\Controllers\Api\V2\Public;

use App\Enums\MexicanState;
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

    /**
     * GET /api/v2/public/postal-codes/municipios/{estado}
     *
     * Devuelve los municipios (distintos, ordenados alfabéticamente) de un
     * estado, tomados del catálogo SEPOMEX (postal_codes). El frontend envía el
     * código del enum MexicanState (p. ej. CDMX, JAL), que se resuelve a la
     * clave INEGI de 2 dígitos (columna estado_clave) — nomenclaturas distintas.
     *
     * Estado no reconocido o sin filas en el catálogo → lista vacía (no 500),
     * para que el paso estado→municipio degrade con seguridad. Catálogo
     * estático → se cachea por estado, igual que la consulta por CP.
     */
    public function municipalities(string $estado): JsonResponse
    {
        $code = strtoupper(trim($estado));
        $state = MexicanState::tryFrom($code);

        // Estado inexistente/no reconocido → lista vacía (degradar seguro).
        if ($state === null) {
            return $this->success(['estado' => $code, 'municipios' => []]);
        }

        $municipios = Cache::remember(
            "postal_municipalities:{$state->value}",
            now()->addDay(),
            function () use ($state) {
                $clave = $state->inegiCode();

                // Tolera la clave con y sin cero a la izquierda por si algún
                // import guardó `c_estado` sin padding (SEPOMEX la trae en 2
                // dígitos, pero blindamos el match).
                $claves = array_values(array_unique([$clave, ltrim($clave, '0')]));

                return PostalCode::query()
                    ->whereIn('estado_clave', $claves)
                    ->whereNotNull('municipio')
                    ->where('municipio', '!=', '')
                    ->distinct()
                    ->orderBy('municipio')
                    ->pluck('municipio')
                    ->all();
            }
        );

        return $this->success([
            'estado' => $state->value,
            'municipios' => $municipios,
        ]);
    }
}
