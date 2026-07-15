<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Webhook\WebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Entrega una fila de webhook (POST firmado). 2xx → SENT; fallo → backoff
 * exponencial (RETRYING); agotados los intentos → FAILED + notificación
 * interna. El re-encolado de las RETRYING vencidas lo hace el comando
 * webhooks:dispatch-retries.
 */
class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Backoff por número de intento (segundos): 1m, 5m, 30m, 2h, 6h. */
    private const BACKOFF = [60, 300, 1800, 7200, 21600];

    public int $tries = 1; // el reintento lo maneja el estado, no la cola
    public int $timeout = 30;

    public function __construct(public string $deliveryId)
    {
    }

    public function handle(WebhookSigner $signer): void
    {
        $delivery = WebhookDelivery::withoutGlobalScopes()
            ->with('endpoint')
            ->find($this->deliveryId);

        if (! $delivery || ! $delivery->isDeliverable() || ! $delivery->endpoint) {
            return;
        }

        $endpoint = $delivery->endpoint;
        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = $signer->headers($body, $endpoint->secret, $delivery->event, $delivery->id);

        $delivery->attempts++;
        $delivery->last_attempt_at = now();
        $delivery->signature = $headers['X-LendusFind-Signature'];

        try {
            $response = Http::withHeaders($headers)
                ->timeout(20)
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $delivery->response_code = $response->status();
            $delivery->response_body = mb_substr((string) $response->body(), 0, 2000);

            if ($response->successful()) {
                $delivery->status = WebhookDelivery::STATUS_SENT;
                $delivery->next_retry_at = null;
                $delivery->error_message = null;
                $delivery->save();
                $endpoint->forceFill(['last_success_at' => now()])->save();

                return;
            }

            $this->scheduleRetryOrFail($delivery, $endpoint, "HTTP {$response->status()}");
        } catch (\Throwable $e) {
            $delivery->response_code = null;
            $this->scheduleRetryOrFail($delivery, $endpoint, $e->getMessage());
        }
    }

    private function scheduleRetryOrFail(WebhookDelivery $delivery, $endpoint, string $error): void
    {
        $delivery->error_message = mb_substr($error, 0, 500);

        if ($delivery->attempts >= $delivery->max_attempts) {
            $delivery->status = WebhookDelivery::STATUS_FAILED;
            $delivery->next_retry_at = null;
            $delivery->save();
            $endpoint->forceFill(['last_failure_at' => now()])->save();
            $this->notifyFailure($delivery);

            return;
        }

        $backoff = self::BACKOFF[min($delivery->attempts - 1, count(self::BACKOFF) - 1)];
        $delivery->status = WebhookDelivery::STATUS_RETRYING;
        $delivery->next_retry_at = now()->addSeconds($backoff);
        $delivery->save();
    }

    /**
     * Registra que una entrega agotó reintentos (WEBHOOK_FAILED). El log es el
     * canal interno; el panel de entregas expone el estado FAILED para reenvío.
     */
    private function notifyFailure(WebhookDelivery $delivery): void
    {
        Log::warning('WEBHOOK_FAILED: entrega agotó reintentos', [
            'delivery_id' => $delivery->id,
            'tenant_id' => $delivery->tenant_id,
            'event' => $delivery->event,
            'url' => $delivery->url,
            'attempts' => $delivery->attempts,
            'last_error' => $delivery->error_message,
        ]);
    }
}
