<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\NubariumAsyncValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recibe los callbacks de las validaciones ASÍNCRONAS de Nubarium
 * (CLABE, tarjeta de débito, IMSS, ISSSTE).
 *
 * Ruta PÚBLICA: Nubarium la invoca desde fuera, sin nuestro auth ni header de
 * tenant. La seguridad es el `token` secreto y aleatorio embebido en la URL
 * (uno por validación). Como no hay tenant en el request, buscamos el registro
 * sin el global scope de HasTenant.
 */
class NubariumWebhookController extends Controller
{
    public function handle(Request $request, string $type, string $token): JsonResponse
    {
        $validation = NubariumAsyncValidation::withoutGlobalScopes()
            ->where('callback_token', $token)
            ->where('type', $type)
            ->first();

        if (!$validation) {
            // 200 para no revelar qué tokens existen; logueamos para diagnóstico.
            Log::warning('Nubarium webhook: token no encontrado', ['type' => $type]);

            return response()->json(['received' => false]);
        }

        // Idempotente: si ya se procesó, no lo pisamos.
        if (!$validation->isPending()) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        $body = $request->all();

        // Cross-check del validationCode si Nubarium lo incluye en el callback.
        $incomingCode = $body['validationCode'] ?? $body['codigoValidacion'] ?? null;
        if ($validation->validation_code && $incomingCode && $incomingCode !== $validation->validation_code) {
            Log::warning('Nubarium webhook: validationCode no coincide', ['id' => $validation->id]);

            return response()->json(['received' => false], 422);
        }

        $validation->update([
            'status' => $this->deriveStatus($body),
            'result' => $body,
            'received_at' => now(),
        ]);

        return response()->json(['received' => true]);
    }

    /**
     * Deriva completed/failed del cuerpo del webhook. Nubarium responde con
     * status/estatus + messageCode/claveMensaje (200 incluso en errores).
     *
     * @param  array<string, mixed>  $body
     */
    private function deriveStatus(array $body): string
    {
        $status = strtoupper((string) ($body['status'] ?? $body['estatus'] ?? ''));
        $code = $body['messageCode'] ?? $body['claveMensaje'] ?? null;
        $ok = $status === 'OK' && ($code === 0 || $code === '0' || $code === null);

        return $ok
            ? NubariumAsyncValidation::STATUS_COMPLETED
            : NubariumAsyncValidation::STATUS_FAILED;
    }
}
