<?php

namespace App\Jobs;

use App\Models\BankAccount;
use App\Models\Tenant;
use App\Services\ExternalApi\NubariumService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Inicia la validación de CLABE con Nubarium en segundo plano.
 *
 * Se dispara cuando el aplicante captura su CLABE (al crear la cuenta). El POST
 * de inicio a Nubarium puede tardar, así que se ejecuta DESPUÉS de responderle
 * al usuario (dispatchAfterResponse): el usuario continúa el proceso de
 * inmediato y la validación queda corriendo por detrás. El resultado real llega
 * luego por webhook (NubariumWebhookController) y se persiste en la cuenta.
 *
 * Tolerante a fallos: si el tenant no tiene Nubarium configurado o el POST
 * falla, solo se loguea — nunca rompe el alta de la cuenta.
 */
class StartClabeValidationJob
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(
        public string $bankAccountId,
        public string $tenantId,
    ) {
    }

    public function handle(): void
    {
        $account = BankAccount::withoutGlobalScopes()->find($this->bankAccountId);

        // Solo cuentas con CLABE y titular (las de tarjeta usan otra validación).
        if (!$account || empty($account->clabe) || empty($account->holder_name)) {
            return;
        }

        // No reintentar si ya quedó verificada por otra vía.
        if ($account->is_verified) {
            return;
        }

        $tenant = Tenant::withoutGlobalScopes()->find($this->tenantId);
        if (!$tenant) {
            return;
        }

        try {
            (new NubariumService($tenant))->validateClabe(
                $account->holder_name,
                $account->clabe,
                $account,
            );
        } catch (\Throwable $e) {
            Log::warning('StartClabeValidationJob: no se pudo iniciar la validación de CLABE', [
                'bank_account_id' => $this->bankAccountId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
