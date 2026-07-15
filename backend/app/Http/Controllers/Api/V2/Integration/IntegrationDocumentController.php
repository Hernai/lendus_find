<?php

namespace App\Http\Controllers\Api\V2\Integration;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Descarga de documentos del expediente (INE, selfie, comprobante, etc.) para
 * el sistema externo. La URL viaja ESTABLE en el payload del webhook; aquí se
 * resuelve el archivo con una URL firmada FRESCA (S3/GCS) o streameándolo
 * (disco local), de modo que nunca se sirve una URL firmada vencida.
 *
 * Auth: token con ability `integration`, scoped por tenant. Ver §7 de
 * docs/integracion/webhooks.md.
 */
class IntegrationDocumentController extends Controller
{
    use ApiResponses;

    /** GET /api/v2/integration/documents/{id}/download */
    public function download(Request $request, string $id)
    {
        $user = $request->user();
        if (! $user || ! $user->tokenCan('integration')) {
            return $this->forbidden('El token no tiene permiso de integración.');
        }

        $document = Document::where('id', $id)
            ->where('tenant_id', (string) $user->tenant_id)
            ->first();

        if (! $document) {
            return $this->notFound('Documento no encontrado.');
        }

        $disk = Storage::disk($document->storage_disk);
        if (! $disk->exists($document->file_path)) {
            return $this->notFound('El archivo no está disponible.');
        }

        // S3/GCS: redirige a una URL firmada fresca (15 min). Local: streamea.
        if (in_array($document->storage_disk, ['s3', 'gcs'], true)) {
            return redirect()->away($disk->temporaryUrl($document->file_path, now()->addMinutes(15)));
        }

        return $disk->download($document->file_path);
    }
}
