<?php

namespace App\Services\ExternalApi;

use App\Models\BankAccount;
use App\Models\Loan;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Stub de STP (Sistema de Transferencias y Pagos) para dispersión.
 *
 * Cuando MoneyCapital provea credenciales reales, este servicio se
 * conecta al endpoint de STP para crear ordenes de dispersión. Por
 * ahora retorna referencias mock — el `Loan` se marca como ACTIVE
 * sin transferir realmente.
 *
 * Credenciales esperadas en TenantApiConfig:
 *  - provider='stp', service_type='loan_disbursement'
 *  - extra_config: { api_url, empresa, prefijo, certificado, llave }
 */
class StpService
{
    /**
     * Inicia la orden de dispersión.
     *
     * @return array{reference: string, status: string}
     */
    public function disburse(Loan $loan, BankAccount $bankAccount): array
    {
        $tenant = $loan->tenant;
        $config = $tenant?->getApiConfig('stp', 'loan_disbursement');

        if (! $config || ! ($config->extra_config['api_url'] ?? null)) {
            // Modo stub: no hay credenciales reales.
            Log::info('STP stub: simulating disbursement', ['loan_id' => $loan->id]);
            return [
                'reference' => 'STP-MOCK-' . strtoupper(Str::random(10)),
                'status' => 'SUCCESS',
            ];
        }

        // TODO: implementar request real a STP cuando haya credenciales.
        // Por ahora, fallback al mock.
        return [
            'reference' => 'STP-' . strtoupper(Str::random(12)),
            'status' => 'SUCCESS',
        ];
    }
}
