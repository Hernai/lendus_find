<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Enums\PostalCodeImportStatus;
use App\Events\PostalCodeImportProgress;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\Staff\UploadPostalCatalogRequest;
use App\Jobs\ImportPostalCodesJob;
use App\Models\PostalCodeImport;
use App\Models\StaffAccount;
use App\Services\PostalCode\PostalCodeCatalogSwapper;
use App\Services\PostalCode\PostalCodeImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gestión del catálogo global de códigos postales (SEPOMEX) desde el panel.
 *
 * Flujo de dos fases: `upload` recibe el archivo y despacha el parseo a staging
 * en un job (progreso por Reverb); `apply` hace el swap atómico tras la
 * confirmación explícita del SUPER_ADMIN. `show`/`index`/`status` sirven el
 * preview, el historial de auditoría y la vigencia actual.
 *
 * Todos los endpoints exigen `canConfigureTenant`. El catálogo es GLOBAL: el
 * historial no se filtra por tenant (`origin_tenant_*` son informativos).
 */
class StaffPostalCatalogController extends Controller
{
    use ApiResponses;

    public function __construct(
        private readonly PostalCodeCatalogSwapper $swapper,
    ) {}

    /**
     * Recibe el archivo, crea el registro de importación y despacha el parseo.
     *
     * POST /v2/staff/catalogs/postal-codes/upload
     */
    public function upload(UploadPostalCatalogRequest $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        // El super admin global tiene tenant_id NULL; se usa el tenant resuelto
        // del header X-Tenant-ID (informativo para la auditoría).
        $tenant = app('tenant');

        // Serialización: la staging es una tabla física ÚNICA y compartida. Si hay
        // un import en vuelo (PENDING_PARSE/PARSED/APPLYING), una nueva carga
        // sobrescribiría su staging y un `apply` posterior aplicaría datos ajenos.
        // Se exige resolver (aplicar o descartar) el pendiente antes de subir otro.
        $inFlight = PostalCodeImport::whereIn('status', PostalCodeImportStatus::IN_FLIGHT)
            ->orderByDesc('created_at')
            ->first();

        if ($inFlight) {
            return $this->error(
                'IMPORT_IN_PROGRESS',
                'Ya hay una carga en curso o pendiente ("'.$inFlight->original_filename.'"). '
                .'Aplícala o descártala antes de subir otro archivo.',
                409
            );
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'dat');

        // El importId nombra tanto el registro como el canal Reverb y el dir temporal.
        $importId = (string) Str::uuid();

        // Se guarda bajo storage/app/{tmp_path}/{importId}/ para que coincida con
        // la base temporal controlada de PostalCodeFileResolver (cleanup seguro).
        $tmpPath = trim((string) config('postal_codes.tmp_path', 'tmp/postal-imports'), '/');
        $targetDir = storage_path('app/'.$tmpPath.'/'.$importId);
        $file->move($targetDir, 'original.'.$extension);
        $storedPath = $targetDir.'/original.'.$extension;

        $import = new PostalCodeImport([
            'staff_account_id' => $staff->id,
            'origin_tenant_id' => $tenant?->id,
            'origin_tenant_slug' => $tenant?->slug,
            'original_filename' => $originalName,
            'status' => PostalCodeImportStatus::PENDING_PARSE,
        ]);
        $import->id = $importId;
        $import->save();

        ImportPostalCodesJob::dispatch($importId, $storedPath);

        return $this->created(
            $this->presentImport($import),
            'Archivo recibido; el parseo comenzó.'
        );
    }

    /**
     * Estado + resumen de una importación (preview y respaldo de polling).
     *
     * GET /v2/staff/catalogs/postal-codes/imports/{id}
     */
    public function show(string $id): JsonResponse
    {
        $import = PostalCodeImport::find($id);
        if (! $import) {
            return $this->notFound('Importación no encontrada.');
        }

        return $this->success($this->presentImport($import));
    }

    /**
     * Aplica el swap atómico de un import en `PARSED` (confirmación del SUPER_ADMIN).
     *
     * POST /v2/staff/catalogs/postal-codes/imports/{id}/apply
     */
    public function apply(string $id): JsonResponse
    {
        $import = PostalCodeImport::find($id);
        if (! $import) {
            return $this->notFound('Importación no encontrada.');
        }

        // Transición atómica PARSED→APPLYING mediante UPDATE condicional: un doble
        // clic o un reintento de red no puede disparar dos swaps sobre el mismo
        // import (el segundo desharía el swap del primero). Solo un request gana.
        $claimed = PostalCodeImport::whereKey($id)
            ->where('status', PostalCodeImportStatus::PARSED)
            ->update(['status' => PostalCodeImportStatus::APPLYING]);

        if ($claimed === 0) {
            return $this->error(
                'INVALID_STATE',
                'Solo se puede aplicar una importación en estado "Listo para aplicar".',
                422
            );
        }

        $import->refresh();
        event(new PostalCodeImportProgress(importId: $import->id, phase: 'applying'));

        try {
            // Revalida contra la staging ACTUAL antes del swap: garantiza que sigue
            // siendo la de este import (mismo conteo que se parseó) y que supera el
            // umbral. Defensa en profundidad frente a una staging sobrescrita.
            $validation = $this->swapper->validateStaging();
            if (! $validation['ok'] || $validation['rows'] !== $import->rows_count) {
                $reason = $validation['ok']
                    ? 'El área de preparación cambió desde el parseo ('.$validation['rows'].' filas ahora vs '
                        .$import->rows_count.' parseadas): vuelve a subir el archivo.'
                    : $validation['reason'];

                $import->update([
                    'status' => PostalCodeImportStatus::REJECTED,
                    'rejection_reason' => $reason,
                ]);
                event(new PostalCodeImportProgress(importId: $import->id, phase: 'rejected', reason: $reason));

                return $this->error('STAGING_MISMATCH', $reason, 409);
            }

            // El swap es de milisegundos (RENAME de metadata) → síncrono en el request.
            $this->swapper->swap();

            $import->update(['status' => PostalCodeImportStatus::APPLIED]);
            event(new PostalCodeImportProgress(
                importId: $import->id,
                phase: 'applied',
                rows: $import->rows_count,
            ));

            return $this->success(
                $this->presentImport($import->refresh()),
                'Catálogo aplicado correctamente.'
            );
        } catch (\Throwable $e) {
            // Revertir a PARSED: el catálogo vigente quedó intacto (la transacción
            // del swap hace rollback) y el staging sigue listo para reintentar.
            $import->update(['status' => PostalCodeImportStatus::PARSED]);
            event(new PostalCodeImportProgress(
                importId: $import->id,
                phase: 'ready',
                rows: $import->rows_count,
                states: $import->states_count,
                municipalities: $import->municipalities_count,
            ));

            return $this->serverError('No se pudo aplicar el catálogo: '.$e->getMessage());
        }
    }

    /**
     * Descarta un import pendiente (PENDING_PARSE/PARSED) sin aplicarlo, liberando
     * la staging para una nueva carga. Necesario para resolver un import "olvidado"
     * (p. ej. tras recargar la página con un resumen a medio confirmar).
     *
     * POST /v2/staff/catalogs/postal-codes/imports/{id}/discard
     */
    public function discard(string $id): JsonResponse
    {
        // UPDATE condicional: solo descarta si sigue en un estado descartable, sin
        // pisar un `apply` concurrente ni un job que aún esté parseando y termine.
        $discarded = PostalCodeImport::whereKey($id)
            ->whereIn('status', [PostalCodeImportStatus::PENDING_PARSE, PostalCodeImportStatus::PARSED])
            ->update(['status' => PostalCodeImportStatus::DISCARDED]);

        if ($discarded === 0) {
            return PostalCodeImport::whereKey($id)->exists()
                ? $this->error(
                    'INVALID_STATE',
                    'Solo se puede descartar una importación pendiente o lista para aplicar.',
                    422
                )
                : $this->notFound('Importación no encontrada.');
        }

        return $this->success(['id' => $id], 'Importación descartada.');
    }

    /**
     * Historial de importaciones (auditoría global), más reciente primero.
     *
     * GET /v2/staff/catalogs/postal-codes/imports
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);

        // Sin filtro de tenant: el catálogo es global y todo SUPER_ADMIN ve todo.
        $imports = PostalCodeImport::orderByDesc('created_at')->paginate($perPage);

        return $this->success([
            'imports' => collect($imports->items())
                ->map(fn (PostalCodeImport $import) => $this->presentImport($import))
                ->all(),
            'meta' => [
                'current_page' => $imports->currentPage(),
                'from' => $imports->firstItem(),
                'last_page' => $imports->lastPage(),
                'per_page' => $imports->perPage(),
                'to' => $imports->lastItem(),
                'total' => $imports->total(),
            ],
        ]);
    }

    /**
     * Vigencia actual del catálogo: última importación aplicada y conteo vivo.
     *
     * GET /v2/staff/catalogs/postal-codes/status
     */
    public function status(): JsonResponse
    {
        // Marca `postal_codes:last_import` que también consume postal-codes:check-freshness.
        $lastImport = Cache::get(PostalCodeImporter::LAST_IMPORT_CACHE_KEY);
        $currentCount = (int) DB::table('postal_codes')->count();

        return $this->success([
            'last_import' => $lastImport, // {at, count} o null si nunca se importó
            'current_count' => $currentCount,
        ]);
    }

    /**
     * Normaliza un registro de importación para las respuestas de la API.
     *
     * @return array<string, mixed>
     */
    private function presentImport(PostalCodeImport $import): array
    {
        return [
            'id' => $import->id,
            'status' => $import->status->value,
            'status_label' => $import->status->label(),
            'original_filename' => $import->original_filename,
            'rows_count' => $import->rows_count,
            'states_count' => $import->states_count,
            'municipalities_count' => $import->municipalities_count,
            'sample' => $import->sample,
            'rejection_reason' => $import->rejection_reason,
            'staff_account_id' => $import->staff_account_id,
            'origin_tenant_id' => $import->origin_tenant_id,
            'origin_tenant_slug' => $import->origin_tenant_slug,
            'created_at' => optional($import->created_at)->toISOString(),
            'updated_at' => optional($import->updated_at)->toISOString(),
        ];
    }
}
