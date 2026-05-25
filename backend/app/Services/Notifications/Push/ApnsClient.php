<?php

namespace App\Services\Notifications\Push;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cliente mínimo de APNs (HTTP/2 + token-based authentication).
 *
 * Usa curl con `CURL_HTTP_VERSION_2_0` porque APNs solo acepta HTTP/2.
 *
 * Devuelve `true` si fue exitoso, `false` si el token está inválido
 * (`BadDeviceToken`, `Unregistered`, `DeviceTokenNotForTopic`) — el
 * caller debe marcarlo revoked. Lanza excepción para errores transitorios.
 */
class ApnsClient
{
    /**
     * @param array{key_id:string,team_id:string,bundle_id:string,p8_key:string,environment?:string} $config
     */
    public function __construct(private array $config)
    {
        foreach (['key_id', 'team_id', 'bundle_id', 'p8_key'] as $required) {
            if (empty($config[$required])) {
                throw new \InvalidArgumentException("APNs config inválida — falta {$required}.");
            }
        }
    }

    public function send(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        $host = ($this->config['environment'] ?? 'production') === 'sandbox'
            ? 'https://api.sandbox.push.apple.com'
            : 'https://api.push.apple.com';

        $url = "{$host}/3/device/{$deviceToken}";
        $jwt = $this->getJwt();

        $payload = [
            'aps' => [
                'alert' => ['title' => $title, 'body' => $body],
                'sound' => 'default',
                'badge' => 1,
            ],
            'data' => $data,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "apns-topic: {$this->config['bundle_id']}",
                'apns-push-type: alert',
                'apns-priority: 10',
                'content-type: application/json',
                "authorization: bearer {$jwt}",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \RuntimeException("APNs curl error: {$err}");
        }

        if ($status === 200) {
            return true;
        }

        $reason = '';
        if ($response) {
            $decoded = json_decode($response, true);
            $reason = $decoded['reason'] ?? '';
        }

        // Tokens inválidos — caller debe revocar.
        if (in_array($reason, ['BadDeviceToken', 'Unregistered', 'DeviceTokenNotForTopic'], true)) {
            return false;
        }

        Log::warning('APNs send failed', ['status' => $status, 'reason' => $reason, 'body' => $response]);
        throw new \RuntimeException("APNs error {$status}: {$reason}");
    }

    private function getJwt(): string
    {
        $cacheKey = 'apns:jwt:' . md5($this->config['key_id']);
        return Cache::remember($cacheKey, 3000, function () {
            $now = time();
            $payload = ['iss' => $this->config['team_id'], 'iat' => $now];
            $header = ['kid' => $this->config['key_id']];
            return JWT::encode($payload, $this->config['p8_key'], 'ES256', null, $header);
        });
    }
}
