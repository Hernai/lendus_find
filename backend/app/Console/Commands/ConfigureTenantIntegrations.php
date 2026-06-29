<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantApiConfig;
use Illuminate\Console\Command;

/**
 * Configura las integraciones por tenant según la matriz acordada (Finatea,
 * MoneyCapital, Demo). Idempotente: activa los service_types de la matriz y
 * desactiva los de Nubarium/Twilio que no estén en ella.
 *
 * Credenciales: Nubarium de NUBARIUM_API_KEY/SECRET; Twilio de TWILIO_*. Si no
 * están en el env, se crea la fila activa SIN credencial (el admin la pone en
 * Integraciones). No pisa credenciales existentes con vacío.
 *
 *   php artisan tenants:configure-integrations
 */
class ConfigureTenantIntegrations extends Command
{
    protected $signature = 'tenants:configure-integrations';

    protected $description = 'Activa los servicios Nubarium/Twilio por tenant según la matriz (Finatea/MoneyCapital/Demo).';

    /** Matriz: slug => [ provider => [service_types...] ]. */
    private const MATRIX = [
        'finatea' => [
            'nubarium' => ['sms', 'email', 'kyc', 'sdk', 'bank_validation', 'phone_risk', 'email_risk'],
        ],
        'moneycapital' => [
            'nubarium' => ['sms', 'kyc', 'bank_validation', 'phone_risk'],
        ],
        'demo' => [
            'nubarium' => ['sms', 'email', 'kyc', 'sdk', 'bank_validation', 'phone_risk', 'email_risk'],
            'twilio' => ['whatsapp'],
        ],
    ];

    public function handle(): int
    {
        foreach (self::MATRIX as $slug => $providers) {
            $tenant = Tenant::withoutGlobalScopes()->where('slug', $slug)->first();
            if (!$tenant) {
                $this->warn("Tenant '{$slug}' no encontrado, se omite.");
                continue;
            }

            $activeKeys = [];
            foreach ($providers as $provider => $serviceTypes) {
                foreach ($serviceTypes as $st) {
                    $activeKeys[] = "{$provider}:{$st}";
                    TenantApiConfig::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'provider' => $provider, 'service_type' => $st],
                        array_merge(['is_active' => true], $this->credsFor($provider)),
                    );
                }
            }

            // Desactivar los service_types de nubarium/twilio que no están en la matriz.
            $off = 0;
            TenantApiConfig::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereIn('provider', ['nubarium', 'twilio'])
                ->get()
                ->each(function ($c) use ($activeKeys, &$off) {
                    if (!in_array("{$c->provider}:{$c->service_type}", $activeKeys, true) && $c->is_active) {
                        $c->update(['is_active' => false]);
                        $off++;
                    }
                });

            $this->info(sprintf(
                '%-14s OK · %d servicios activos%s',
                $slug,
                count($activeKeys),
                $off ? " · {$off} desactivados" : '',
            ));
        }

        $this->newLine();
        $this->comment('Recuerda poner/editar la credencial por tenant en Integraciones si no vino del env.');

        return self::SUCCESS;
    }

    /**
     * Credenciales desde el env (solo las presentes, para no pisar con vacío).
     *
     * @return array<string, string>
     */
    private function credsFor(string $provider): array
    {
        $creds = match ($provider) {
            'nubarium' => [
                'api_key' => env('NUBARIUM_API_KEY'),
                'api_secret' => env('NUBARIUM_API_SECRET'),
            ],
            'twilio' => [
                'account_sid' => env('TWILIO_ACCOUNT_SID'),
                'auth_token' => env('TWILIO_AUTH_TOKEN'),
                'from_number' => env('TWILIO_WHATSAPP_FROM'),
            ],
            default => [],
        };

        return array_filter($creds, fn ($v) => !empty($v));
    }
}
