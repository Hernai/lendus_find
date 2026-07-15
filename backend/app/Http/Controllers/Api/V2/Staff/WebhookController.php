<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Jobs\DeliverWebhookJob;
use App\Models\StaffAccount;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Webhook\WebhookEvent;
use App\Services\Webhook\WebhookPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Gestión de webhooks del tenant: endpoints (CRUD, rotar secreto, activar,
 * sandbox), log de entregas (index, reenviar) y evento de prueba. Permiso
 * canManageProducts; scoping por tenant. El ADMIN gestiona sus integraciones.
 */
class WebhookController extends Controller
{
    use ApiResponses;

    public function __construct(private WebhookPayloadBuilder $builder)
    {
    }

    private function scopedTenantId(StaffAccount $staff): string
    {
        if ($staff->isSuperAdmin()) {
            $tenant = app('tenant');
            if ($tenant && isset($tenant->id)) {
                return (string) $tenant->id;
            }
        }

        return (string) $staff->tenant_id;
    }

    /** Catálogo de eventos disponibles (para el checklist del panel). */
    public function events(): JsonResponse
    {
        return $this->success(['events' => WebhookEvent::toOptions()]);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        $endpoints = WebhookEndpoint::withoutGlobalScopes()
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get();

        return $this->success(['endpoints' => $endpoints->map->toApiArray()->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateEndpoint($request);

        /** @var StaffAccount $staff */
        $staff = $request->user();
        $secret = 'whsec_' . Str::random(40);

        $endpoint = WebhookEndpoint::create([
            'tenant_id' => $this->scopedTenantId($staff),
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => $secret,
            'events' => $validated['events'],
            'is_active' => $validated['is_active'] ?? true,
            'is_sandbox' => $validated['is_sandbox'] ?? false,
            'description' => $validated['description'] ?? null,
        ]);

        // El secreto se muestra UNA sola vez.
        return $this->created(array_merge($endpoint->toApiArray(), ['secret' => $secret]), 'Endpoint creado. Guarda el secreto: no se volverá a mostrar.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $endpoint = $this->find($request, $id);
        if (! $endpoint) {
            return $this->notFound('Endpoint no encontrado.');
        }

        $validated = $this->validateEndpoint($request);
        $endpoint->update([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => $validated['events'],
            'is_active' => $validated['is_active'] ?? $endpoint->is_active,
            'is_sandbox' => $validated['is_sandbox'] ?? $endpoint->is_sandbox,
            'description' => $validated['description'] ?? $endpoint->description,
        ]);

        return $this->success($endpoint->fresh()->toApiArray(), 'Endpoint actualizado.');
    }

    public function rotateSecret(Request $request, string $id): JsonResponse
    {
        $endpoint = $this->find($request, $id);
        if (! $endpoint) {
            return $this->notFound('Endpoint no encontrado.');
        }

        $secret = 'whsec_' . Str::random(40);
        $endpoint->update(['secret' => $secret]);

        return $this->success(['secret' => $secret], 'Secreto rotado. Guárdalo: no se volverá a mostrar.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $endpoint = $this->find($request, $id);
        if (! $endpoint) {
            return $this->notFound('Endpoint no encontrado.');
        }
        $endpoint->delete();

        return $this->success(null, 'Endpoint eliminado.');
    }

    /** Log de entregas con filtros. */
    public function deliveries(Request $request): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        $query = WebhookDelivery::withoutGlobalScopes()
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->orderByDesc('created_at');

        if ($request->filled('endpoint_id')) {
            $query->where('webhook_endpoint_id', $request->input('endpoint_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        $deliveries = $query->limit(100)->get();

        return $this->success(['deliveries' => $deliveries->map->toApiArray()->values()]);
    }

    /** Reenvía una entrega (la vuelve a PENDING y despacha el job). */
    public function retry(Request $request, string $id): JsonResponse
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();
        $delivery = WebhookDelivery::withoutGlobalScopes()
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->find($id);
        if (! $delivery) {
            return $this->notFound('Entrega no encontrada.');
        }

        $delivery->update([
            'status' => WebhookDelivery::STATUS_PENDING,
            'next_retry_at' => null,
            'error_message' => null,
        ]);
        DeliverWebhookJob::dispatch($delivery->id);

        return $this->success(null, 'Entrega reencolada.');
    }

    /** Envía un evento de prueba firmado al endpoint. */
    public function test(Request $request, string $id): JsonResponse
    {
        $endpoint = $this->find($request, $id);
        if (! $endpoint) {
            return $this->notFound('Endpoint no encontrado.');
        }

        $eventId = 'evt_' . Str::ulid();
        $envelope = $this->builder->envelope($eventId, 'webhook.test', $endpoint->tenant_id, null, [
            'message' => 'Evento de prueba de LendusFind. Si verificas la firma correctamente, tu integración está lista.',
        ]);

        $delivery = WebhookDelivery::create([
            'tenant_id' => $endpoint->tenant_id,
            'webhook_endpoint_id' => $endpoint->id,
            'event' => 'webhook.test',
            'event_id' => $eventId,
            'idempotency_key' => $endpoint->id . ':' . $eventId,
            'payload' => $envelope,
            'url' => $endpoint->url,
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempts' => 0,
            'max_attempts' => 1,
        ]);
        DeliverWebhookJob::dispatch($delivery->id);

        return $this->success(['delivery_id' => $delivery->id], 'Evento de prueba enviado. Revisa el log de entregas.');
    }

    private function find(Request $request, string $id): ?WebhookEndpoint
    {
        /** @var StaffAccount $staff */
        $staff = $request->user();

        return WebhookEndpoint::withoutGlobalScopes()
            ->where('tenant_id', $this->scopedTenantId($staff))
            ->whereNull('deleted_at')
            ->find($id);
    }

    private function validateEndpoint(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'url' => 'required|url|starts_with:https://',
            'events' => 'required|array|min:1',
            'events.*' => 'string',
            'is_active' => 'nullable|boolean',
            'is_sandbox' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
        ], [
            'url.starts_with' => 'La URL del webhook debe ser HTTPS.',
        ]);
    }
}
