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

        // Persiste el resultado en la cuenta bancaria ligada (si la hay), para
        // que quede visible de forma permanente aunque se cierre el modal.
        $this->persistToBankAccount($validation);

        return response()->json(['received' => true]);
    }

    /**
     * Guarda el resumen de la validación (CLABE / tarjeta) en la cuenta bancaria
     * vinculada, dentro de `verification_data['nubarium_clabe']`, y —si Nubarium
     * confirmó la titularidad (match)— marca la cuenta como verificada
     * automáticamente (verification_method = 'nubarium_clabe', sin actor staff).
     *
     * Si NO coincidió el nombre, solo persiste el resultado: la cuenta queda sin
     * verificar para que un analista la valide manualmente.
     *
     * Sin tenant en el request: resolvemos la cuenta sin el global scope.
     */
    private function persistToBankAccount(NubariumAsyncValidation $validation): void
    {
        if ($validation->entity_type !== \App\Models\BankAccount::class || !$validation->entity_id) {
            return;
        }

        $summary = $validation->clabeSummary();
        if ($summary === null) {
            return;
        }

        $account = \App\Models\BankAccount::withoutGlobalScopes()->find($validation->entity_id);
        if (!$account) {
            return;
        }

        $data = $account->verification_data ?? [];
        $data['nubarium_clabe'] = $summary;
        $account->verification_data = $data;

        // Auto-verificación: solo si coincide la titularidad y aún no está
        // verificada (no pisamos una verificación manual previa).
        if ($validation->status === NubariumAsyncValidation::STATUS_COMPLETED && !$account->is_verified) {
            $account->is_verified = true;
            $account->verified_at = now();
            $account->verified_by = null; // sistema
            $account->verification_method = 'nubarium_clabe';
        }

        $account->save();

        // Registrar el resultado en el historial de Actividad de la solicitud.
        // El webhook es del sistema (sin actor staff); si algo falla, no debe
        // romper el procesamiento del webhook (la cuenta ya quedó persistida).
        try {
            if ($account->entity_type === 'persons' && $account->entity_id) {
                $application = \App\Models\Application::withoutGlobalScopes()
                    ->where('person_id', $account->entity_id)
                    ->latest()
                    ->first();

                if ($application) {
                    $ok = $validation->status === NubariumAsyncValidation::STATUS_COMPLETED;
                    \App\Services\ActivityRecorder::recordApplicationEvent($application, 'BANK_VALIDATION_NUBARIUM', [
                        'from_status' => 'BANK_VALIDATION',
                        'to_status' => 'BANK_VALIDATION',
                        'notes' => $ok
                            ? "Cuenta bancaria '{$account->bank_name}' validada con Nubarium: el titular coincide."
                            : "Validación de CLABE con Nubarium sin coincidencia de titular ('{$account->bank_name}').",
                        'entity' => $account,
                        'metadata' => [
                            'kind' => 'bank_account_nubarium_validation',
                            'bank_name' => $account->bank_name,
                            'nubarium_status' => $validation->status,
                            'auto_verified' => $ok && $account->verification_method === 'nubarium_clabe',
                            'similarity' => $summary['similarity'] ?? null,
                            'holder_name_real' => $summary['holder_name_real'] ?? null,
                            'validation_code' => $summary['validation_code'] ?? null,
                        ],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar la actividad de validación CLABE con Nubarium', [
                'bank_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }
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
