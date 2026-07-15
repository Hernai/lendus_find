<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Models\InboundEvent;
use App\Models\Loan;
use App\Models\WebhookEndpoint;
use App\Services\LoanService;
use App\Services\Webhook\WebhookSigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API ENTRANTE (cartera externa → LendusFind): confirmación de dispersión,
 * pago aplicado y acuse de ingesta. Autenticada por HMAC del endpoint
 * (la cartera firma, LendusFind verifica) e idempotente por external_event_id.
 * Ver docs/integracion/webhooks.md §7.
 */
class InboundIntegrationController extends Controller
{
    use ApiResponses;

    public function __construct(
        private WebhookSigner $signer,
        private LoanService $loanService,
    ) {}

    /** POST /api/webhooks/inbound/{endpoint}/disbursement */
    public function disbursement(Request $request, string $endpoint): JsonResponse
    {
        return $this->process($request, $endpoint, InboundEvent::TYPE_DISBURSEMENT, [
            'external_event_id' => 'required|string',
            'loan_id' => 'required|uuid',
            'external_id' => 'nullable|string',
            'external_system' => 'nullable|string',
            'disbursement_reference' => 'nullable|string',
            'disbursed_at' => 'nullable|date',
        ], function (array $data, WebhookEndpoint $ep) {
            $loan = Loan::withoutGlobalScopes()
                ->where('tenant_id', $ep->tenant_id)
                ->find($data['loan_id']);
            if (! $loan) {
                throw new \InvalidArgumentException('Crédito no encontrado.');
            }
            $loan = $this->loanService->confirmDisbursement($loan, $data);

            return [
                'loan_status' => $loan->status instanceof \BackedEnum ? $loan->status->value : $loan->status,
                'application_status' => $loan->application?->fresh()->status,
            ];
        });
    }

    /** POST /api/webhooks/inbound/{endpoint}/payments */
    public function payments(Request $request, string $endpoint): JsonResponse
    {
        return $this->process($request, $endpoint, InboundEvent::TYPE_PAYMENT, [
            'external_event_id' => 'required|string',
            'loan_id' => 'required|uuid',
            'amount' => 'required|numeric|min:0.01',
            'channel' => 'nullable|string',
            'provider_reference' => 'nullable|string',
            'paid_at' => 'nullable|date',
        ], function (array $data, WebhookEndpoint $ep) {
            $loan = Loan::withoutGlobalScopes()
                ->where('tenant_id', $ep->tenant_id)
                ->find($data['loan_id']);
            if (! $loan) {
                throw new \InvalidArgumentException('Crédito no encontrado.');
            }
            $this->loanService->recordPayment($loan, [
                'amount' => $data['amount'],
                'channel' => $data['channel'] ?? 'STP',
                'provider_reference' => $data['provider_reference'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
            ]);

            return ['outstanding_balance' => (float) $loan->fresh()->outstanding_balance];
        });
    }

    /** POST /api/webhooks/inbound/{endpoint}/ingest-ack */
    public function ingestAck(Request $request, string $endpoint): JsonResponse
    {
        return $this->process($request, $endpoint, InboundEvent::TYPE_INGEST_ACK, [
            'external_event_id' => 'required|string',
            'application_id' => 'required|uuid',
            'external_id' => 'required|string',
            'external_system' => 'nullable|string',
        ], function (array $data, WebhookEndpoint $ep) {
            $app = \App\Models\Application::withoutGlobalScopes()
                ->where('tenant_id', $ep->tenant_id)
                ->find($data['application_id']);
            if (! $app) {
                throw new \InvalidArgumentException('Solicitud no encontrada.');
            }
            if ($app->status === \App\Models\Application::STATUS_APPROVED) {
                $app->markSynced($data['external_id'], $data['external_system'] ?? 'CARTERA', []);
            }

            return ['application_status' => $app->fresh()->status];
        });
    }

    /**
     * Verifica la firma, deduplica por external_event_id y ejecuta el handler.
     */
    private function process(Request $request, string $endpointId, string $type, array $rules, \Closure $handler): JsonResponse
    {
        $endpoint = WebhookEndpoint::withoutGlobalScopes()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->find($endpointId);

        if (! $endpoint) {
            return $this->error('ENDPOINT_NOT_FOUND', 'Endpoint no encontrado o inactivo.', 404);
        }

        // Verificación HMAC sobre el cuerpo crudo.
        $rawBody = $request->getContent();
        $signature = $request->header('X-LendusFind-Signature', '');
        $timestamp = $request->header('X-LendusFind-Timestamp', '');
        if (! $this->signer->verify($rawBody, $endpoint->secret, $signature, $timestamp)) {
            return $this->error('INVALID_SIGNATURE', 'Firma inválida o timestamp fuera de ventana.', 401);
        }

        $data = $request->validate($rules);

        // Idempotencia por external_event_id.
        $existing = InboundEvent::withoutGlobalScopes()
            ->where('tenant_id', $endpoint->tenant_id)
            ->where('external_event_id', $data['external_event_id'])
            ->first();
        if ($existing) {
            return $this->success(array_merge(['status' => InboundEvent::STATUS_DUPLICATE], (array) $existing->result));
        }

        try {
            $result = $handler($data, $endpoint);

            InboundEvent::create([
                'tenant_id' => $endpoint->tenant_id,
                'endpoint_id' => $endpoint->id,
                'external_event_id' => $data['external_event_id'],
                'type' => $type,
                'payload' => $data,
                'status' => InboundEvent::STATUS_PROCESSED,
                'result' => $result,
                'received_at' => now(),
            ]);

            return $this->success(array_merge(['status' => InboundEvent::STATUS_PROCESSED], $result));
        } catch (\InvalidArgumentException $e) {
            return $this->validationError($e->getMessage(), ['inbound' => [$e->getMessage()]]);
        }
    }
}
