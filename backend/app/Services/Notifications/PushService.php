<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use App\Models\Tenant;
use App\Services\Notifications\Push\ApnsClient;
use App\Services\Notifications\Push\FcmClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dispatcher de push notifications.
 *
 * Para un destinatario polimórfico (StaffAccount o ApplicantAccount):
 * - Carga sus device_tokens activos.
 * - Despacha cada token al cliente FCM o APNs según `provider`.
 * - Marca como revoked los tokens que devolvieron inválido.
 *
 * Credenciales por tenant viven en `TenantApiConfig` filas:
 *   - provider='fcm', service_type='push', extra_config={...service_account.json}
 *   - provider='apns', service_type='push',
 *     extra_config={key_id, team_id, bundle_id, p8_key, environment}
 *
 * Si el tenant no tiene credenciales del provider correspondiente, los
 * tokens se ignoran silenciosamente (con log de warning).
 */
class PushService
{
    /**
     * Envía push a todos los dispositivos activos del owner.
     *
     * @return array{sent:int,failed:int,revoked:int}
     */
    public function sendTo(
        string $tenantId,
        string $ownerType,
        string $ownerId,
        string $title,
        string $body,
        array $data = [],
    ): array {
        $result = ['sent' => 0, 'failed' => 0, 'revoked' => 0];

        $tokens = DeviceToken::active()
            ->where('tenant_id', $tenantId)
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->get();

        if ($tokens->isEmpty()) {
            return $result;
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            Log::warning('PushService: tenant no encontrado', ['tenant_id' => $tenantId]);
            return $result;
        }

        $fcm = $this->buildFcmClient($tenant);
        $apns = $this->buildApnsClient($tenant);

        foreach ($tokens as $token) {
            try {
                $delivered = match ($token->provider) {
                    DeviceToken::PROVIDER_FCM => $fcm?->send($token->token, $title, $body, $data) ?? null,
                    DeviceToken::PROVIDER_APNS => $apns?->send($token->token, $title, $body, $data) ?? null,
                    default => null,
                };

                if ($delivered === true) {
                    $token->forceFill(['last_seen_at' => now()])->save();
                    $result['sent']++;
                } elseif ($delivered === false) {
                    $token->revoke();
                    $result['revoked']++;
                } else {
                    // El provider no está configurado para este tenant.
                    $result['failed']++;
                }
            } catch (Throwable $e) {
                Log::warning('Push send failed', [
                    'tenant_id' => $tenantId,
                    'token_id' => $token->id,
                    'provider' => $token->provider,
                    'error' => $e->getMessage(),
                ]);
                $result['failed']++;
            }
        }

        return $result;
    }

    private function buildFcmClient(Tenant $tenant): ?FcmClient
    {
        $config = $tenant->getApiConfig('fcm', 'push');
        if (! $config) return null;
        $sa = $config->extra_config['service_account_json'] ?? null;
        if (! is_array($sa)) {
            // Permitir también string JSON serializado.
            $sa = is_string($sa) ? json_decode($sa, true) : null;
        }
        if (! is_array($sa)) {
            Log::warning('PushService: FCM service_account_json inválido', ['tenant_id' => $tenant->id]);
            return null;
        }
        try {
            return new FcmClient($sa);
        } catch (Throwable $e) {
            Log::warning('PushService: no se pudo crear FcmClient', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildApnsClient(Tenant $tenant): ?ApnsClient
    {
        $config = $tenant->getApiConfig('apns', 'push');
        if (! $config) return null;
        $cfg = $config->extra_config ?? [];
        try {
            return new ApnsClient($cfg);
        } catch (Throwable $e) {
            Log::warning('PushService: no se pudo crear ApnsClient', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
