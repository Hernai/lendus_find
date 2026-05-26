<?php

namespace App\Services\ExternalApi;

use App\Models\Loan;
use App\Models\LoanPayment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Stub de Conekta para cobranza.
 *
 * Cuando MoneyCapital provea API keys reales (sk_live_xxx), este
 * servicio se conecta a Conekta para procesar pagos con tarjeta.
 * Mientras tanto retorna URLs falsas y referencias mock.
 *
 * Credenciales esperadas en TenantApiConfig:
 *  - provider='conekta', service_type='payment_collection'
 *  - extra_config: { api_url, public_key, private_key }
 */
class ConektaService
{
    /**
     * Crea una orden de cobro en Conekta y retorna la URL de pago.
     *
     * @return array{id: string, status: string, payment_url: string}
     */
    public function createCharge(Loan $loan, array $payload): array
    {
        $tenant = $loan->tenant;
        $config = $tenant?->getApiConfig('conekta', 'payment_collection');
        $amount = (float) ($payload['amount'] ?? $loan->outstanding_balance);

        if (! $config || ! ($config->extra_config['private_key'] ?? null)) {
            Log::info('Conekta stub: simulating charge', [
                'loan_id' => $loan->id,
                'amount' => $amount,
            ]);
            $id = 'CKT-MOCK-' . strtoupper(Str::random(10));
            return [
                'id' => $id,
                'status' => 'pending_payment',
                'payment_url' => "https://pay.conekta.com/checkout/{$id}",
            ];
        }

        // TODO: implementar request real a Conekta cuando haya credenciales.
        $id = 'CKT-' . strtoupper(Str::random(12));
        return [
            'id' => $id,
            'status' => 'pending_payment',
            'payment_url' => "https://pay.conekta.com/checkout/{$id}",
        ];
    }

    /**
     * Procesa el webhook que confirma el pago.
     */
    public function handleWebhook(array $payload): ?LoanPayment
    {
        Log::info('Conekta webhook received (stub)', $payload);
        // TODO: parsear payload, encontrar Loan por metadata, crear LoanPayment.
        return null;
    }
}
