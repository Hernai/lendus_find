<?php

namespace App\Services\Webhook;

/**
 * Firma HMAC-SHA256 de webhooks salientes y verificación de requests entrantes.
 * Base firmada: "{timestamp}.{body}". Ventana anti-replay de ±5 min.
 * Ver docs/integracion/webhooks.md §5.
 */
class WebhookSigner
{
    /** Tolerancia de timestamp en segundos (anti-replay). */
    public const TIMESTAMP_TOLERANCE = 300;

    /** Firma un cuerpo con el secreto; devuelve "sha256=<hex>". */
    public function sign(string $body, string $secret, int $timestamp): string
    {
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    /**
     * Verifica una firma entrante en tiempo constante y dentro de la ventana.
     */
    public function verify(string $body, string $secret, string $signature, string $timestamp, ?int $now = null): bool
    {
        $now ??= time();
        if (! ctype_digit((string) $timestamp) || abs($now - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            return false;
        }

        $expected = $this->sign($body, $secret, (int) $timestamp);

        return hash_equals($expected, $signature);
    }

    /**
     * Cabeceras HTTP para una entrega saliente.
     *
     * @return array<string, string>
     */
    public function headers(string $body, string $secret, string $event, string $deliveryId, ?int $timestamp = null): array
    {
        $timestamp ??= time();

        return [
            'Content-Type' => 'application/json',
            'X-LendusFind-Signature' => $this->sign($body, $secret, $timestamp),
            'X-LendusFind-Timestamp' => (string) $timestamp,
            'X-LendusFind-Event' => $event,
            'X-LendusFind-Delivery' => $deliveryId,
        ];
    }
}
