<?php

namespace App\Services\Notifications\Push;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente mínimo de FCM HTTP v1.
 *
 * Construye el OAuth token vía JWT firmado con la `private_key` del
 * service account y envía mensajes a un token específico.
 *
 * Devuelve `true` si el envío fue exitoso, `false` si el token está
 * inválido (404/UNREGISTERED) — el caller debe marcarlo revoked.
 * Lanza excepción para errores transitorios (5xx, red).
 */
class FcmClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * @param array{type:string,project_id:string,private_key:string,client_email:string,...} $serviceAccount
     */
    public function __construct(private array $serviceAccount)
    {
        if (empty($serviceAccount['project_id']) || empty($serviceAccount['client_email']) || empty($serviceAccount['private_key'])) {
            throw new \InvalidArgumentException('FCM service account inválido — faltan project_id/client_email/private_key.');
        }
    }

    public function send(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        $accessToken = $this->getAccessToken();
        $projectId = $this->serviceAccount['project_id'];
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $message = [
            'message' => [
                'token' => $deviceToken,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => array_map('strval', $data),
                'android' => [
                    'priority' => 'high',
                    'notification' => ['default_sound' => true, 'default_vibrate_timings' => true],
                ],
            ],
        ];

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->post($url, $message);

        if ($response->successful()) {
            return true;
        }

        $status = $response->status();
        $error = $response->json('error.status') ?? 'UNKNOWN';

        // Tokens inválidos — el caller debe revocar.
        if (in_array($status, [400, 404], true) && in_array($error, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
            return false;
        }

        Log::warning('FCM send failed', [
            'status' => $status,
            'body' => $response->body(),
        ]);
        throw new \RuntimeException("FCM error {$status}: {$error}");
    }

    private function getAccessToken(): string
    {
        $cacheKey = 'fcm:token:' . md5($this->serviceAccount['client_email']);
        return Cache::remember($cacheKey, 3500, function () {
            $now = time();
            $payload = [
                'iss' => $this->serviceAccount['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ];
            $jwt = JWT::encode($payload, $this->serviceAccount['private_key'], 'RS256');

            $response = Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('FCM: no se pudo obtener access token: ' . $response->body());
            }
            return (string) $response->json('access_token');
        });
    }
}
