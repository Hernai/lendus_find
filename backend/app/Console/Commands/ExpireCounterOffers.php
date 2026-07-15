<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Services\ApplicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Cancela contraofertas expiradas sin respuesta.
 *
 * Corre cada minuto vía scheduler (routes/console.php). La pantalla del
 * solicitante bloquea aceptar client-side al llegar el countdown a cero, y
 * respondToCounterOffer valida expires_at server-side; este comando cierra el
 * ciclo transicionando la solicitud a CANCELLED y notificando.
 */
class ExpireCounterOffers extends Command
{
    protected $signature = 'counter-offers:expire';

    protected $description = 'Cancela solicitudes en COUNTER_OFFERED cuya contraoferta expiró sin respuesta';

    public function __construct(private ApplicationService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $expired = Application::withoutTenant()
            ->where('status', Application::STATUS_COUNTER_OFFERED)
            ->whereNull('counter_offer_responded_at')
            ->whereRaw("(counter_offer->>'expires_at')::timestamptz < now()")
            ->get();

        foreach ($expired as $application) {
            try {
                $this->service->cancel(
                    $application,
                    null,
                    'system',
                    'La contraoferta expiró sin respuesta'
                );
                $this->info("Contraoferta expirada cancelada: {$application->id}");
            } catch (\Throwable $e) {
                // Una solicitud que falle no debe frenar el barrido de las demás.
                Log::error('No se pudo cancelar contraoferta expirada', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Recordatorio único antes del vencimiento (solo ofertas con
        // reminder_at en el snapshot — las genera el motor de decisión).
        $expiring = Application::withoutTenant()
            ->where('status', Application::STATUS_COUNTER_OFFERED)
            ->whereNull('counter_offer_responded_at')
            ->whereRaw("(counter_offer->>'reminder_at')::timestamptz < now()")
            ->whereRaw("counter_offer->>'reminder_sent_at' IS NULL")
            ->whereRaw("(counter_offer->>'expires_at')::timestamptz > now()")
            ->get();

        foreach ($expiring as $application) {
            try {
                $this->service->sendCounterOfferExpiringReminder($application);
                $this->info("Recordatorio de vencimiento enviado: {$application->id}");
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar el recordatorio de oferta por vencer', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Procesadas: {$expired->count()} expiradas, {$expiring->count()} recordatorios");

        return self::SUCCESS;
    }
}
