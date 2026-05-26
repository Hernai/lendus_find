<?php

namespace App\Services\ExternalApi;

use App\Models\Loan;
use App\Models\LoanPayment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Stub de OpenPay para cobranza (alternativa a Conekta).
 *
 * Credenciales esperadas en TenantApiConfig:
 *  - provider='openpay', service_type='payment_collection'
 *  - extra_config: { api_url, merchant_id, private_key }
 */
class OpenPayService
{
    /**
     * @return array{id: string, status: string, payment_url: string}
     */
    public function createCharge(Loan $loan, array $payload): array
    {
        $tenant = $loan->tenant;
        $config = $tenant?->getApiConfig('openpay', 'payment_collection');
        $amount = (float) ($payload['amount'] ?? $loan->outstanding_balance);

        if (! $config || ! ($config->extra_config['private_key'] ?? null)) {
            Log::info('OpenPay stub: simulating charge', [
                'loan_id' => $loan->id,
                'amount' => $amount,
            ]);
            $id = 'OPN-MOCK-' . strtoupper(Str::random(10));
            return [
                'id' => $id,
                'status' => 'in_progress',
                'payment_url' => "https://sandbox-api.openpay.mx/checkout/{$id}",
            ];
        }

        $id = 'OPN-' . strtoupper(Str::random(12));
        return [
            'id' => $id,
            'status' => 'in_progress',
            'payment_url' => "https://api.openpay.mx/checkout/{$id}",
        ];
    }

    public function handleWebhook(array $payload): ?LoanPayment
    {
        Log::info('OpenPay webhook received (stub)', $payload);
        return null;
    }
}
