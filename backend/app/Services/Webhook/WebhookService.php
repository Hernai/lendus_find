<?php

namespace App\Services\Webhook;

use App\Jobs\DeliverWebhookJob;
use App\Models\Application;
use App\Models\Loan;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Emite eventos del ciclo a los endpoints suscritos: genera el event_id,
 * construye el payload, crea una entrega por endpoint y despacha el job.
 *
 * Best-effort: un fallo al emitir se registra pero NUNCA revierte el negocio
 * (aprobación, dispersión, pago).
 */
class WebhookService
{
    public function __construct(private WebhookPayloadBuilder $builder)
    {
    }

    /**
     * Emite un evento sobre un recurso (Application o Loan).
     *
     * @param  array<string, mixed>  $extra  ej. ['last_payment' => [...]] para payment.received
     */
    public function emit(string $event, Model $model, array $extra = []): void
    {
        try {
            $tenantId = $model->tenant_id;
            $endpoints = WebhookEndpoint::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->get()
                ->filter(fn (WebhookEndpoint $e) => $e->subscribesTo($event));

            if ($endpoints->isEmpty()) {
                return; // no-op: nadie suscrito
            }

            $eventId = 'evt_' . Str::ulid();
            $data = $this->buildData($event, $model, $extra);
            $tenant = $model->relationLoaded('tenant') ? $model->tenant : null;
            $envelope = $this->builder->envelope($eventId, $event, $tenantId, $tenant?->slug ?? optional($model->tenant)->slug, $data);

            foreach ($endpoints as $endpoint) {
                $delivery = WebhookDelivery::create([
                    'tenant_id' => $tenantId,
                    'webhook_endpoint_id' => $endpoint->id,
                    'event' => $event,
                    'event_id' => $eventId,
                    'idempotency_key' => $endpoint->id . ':' . $eventId,
                    'model_type' => $model::class,
                    'model_id' => $model->getKey(),
                    'payload' => $envelope,
                    'url' => $endpoint->url,
                    'status' => WebhookDelivery::STATUS_PENDING,
                    'attempts' => 0,
                    'max_attempts' => 5,
                ]);

                DeliverWebhookJob::dispatch($delivery->id);
            }
        } catch (\Throwable $e) {
            // La emisión nunca debe tirar el flujo de negocio.
            Log::error('WebhookService::emit falló', [
                'event' => $event,
                'model' => $model::class,
                'model_id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function buildData(string $event, Model $model, array $extra): array
    {
        if ($model instanceof Application) {
            return $this->builder->application($model);
        }

        if ($model instanceof Loan) {
            return $this->builder->loan($model, $extra['last_payment'] ?? null);
        }

        return [];
    }
}
