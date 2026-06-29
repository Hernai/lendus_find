<?php

namespace App\Jobs;

use App\Models\ApplicantAccount;
use App\Models\RiskAssessment;
use App\Models\Tenant;
use App\Models\TenantApiConfig;
use App\Services\ExternalApi\Nubarium\NubariumRiskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Corre el riesgo de contacto (Nubarium Phone/Email Risk) tras confirmar el OTP
 * y persiste el resultado en risk_assessments. En cola (no bloquea el login).
 *
 * Gateado por el service_type del tenant: sólo corre si nubarium+phone_risk
 * (o email_risk) está activo. Así MoneyCapital corre phone_risk pero no email_risk.
 */
class RunContactRiskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public string $accountId,
        public string $channel, // 'PHONE' | 'EMAIL' | 'WHATSAPP'
    ) {
    }

    public function handle(): void
    {
        $channel = strtoupper($this->channel);
        // WhatsApp/SMS evalúan el teléfono; EMAIL evalúa el correo.
        $serviceType = $channel === 'EMAIL'
            ? RiskAssessment::TYPE_EMAIL_RISK
            : RiskAssessment::TYPE_PHONE_RISK;

        $account = ApplicantAccount::withoutGlobalScopes()->find($this->accountId);
        if (!$account) {
            return;
        }

        // Gating por tenant: el service_type debe estar activo (Nubarium).
        $active = TenantApiConfig::where('tenant_id', $account->tenant_id)
            ->where('provider', 'nubarium')
            ->where('service_type', $serviceType)
            ->where('is_active', true)
            ->exists();
        if (!$active) {
            return;
        }

        $identifier = $serviceType === RiskAssessment::TYPE_EMAIL_RISK
            ? $account->primary_email
            : $account->primary_phone;
        if (!$identifier) {
            return;
        }

        $tenant = Tenant::withoutGlobalScopes()->find($account->tenant_id);
        if (!$tenant) {
            return;
        }

        try {
            $svc = new NubariumRiskService($tenant);
            $res = $serviceType === RiskAssessment::TYPE_EMAIL_RISK
                ? $svc->emailRisk($identifier)
                : $svc->phoneRisk($identifier);

            RiskAssessment::create([
                'tenant_id' => $account->tenant_id,
                'account_id' => $account->id,
                'person_id' => $account->person?->id,
                'type' => $serviceType,
                'provider' => 'nubarium',
                'identifier' => $identifier,
                'status' => ($res['success'] ?? false)
                    ? RiskAssessment::STATUS_COMPLETED
                    : RiskAssessment::STATUS_FAILED,
                'score' => $res['score'] ?? null,
                'level' => $res['level'] ?? null,
                'recommendation' => $res['recommendation'] ?? null,
                'result' => $res['raw'] ?? null,
                'error' => ($res['success'] ?? false) ? null : ($res['error'] ?? 'unknown'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('RunContactRiskJob falló', [
                'account_id' => $this->accountId,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
