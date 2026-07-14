<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// =============================================================================
// Schedule
// =============================================================================
// Requiere `php artisan schedule:work` corriendo (systemd service) o cron
// que invoque `php artisan schedule:run` cada minuto. Documentado en
// deploy-ops skill.

// Contraofertas — cancelar las expiradas sin respuesta (ExpireCounterOffers).
// El barrido es barato (query indexable por status) y da granularidad de 1 min
// al countdown que ve el solicitante.
Schedule::command('counter-offers:expire')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// MaxMind GeoLite2-City — actualizar la DB cada miercoles 03:00 America/Mexico_City.
// MaxMind libera nueva version los martes; corremos miercoles para asegurar
// que la version mas reciente este disponible.
Schedule::command('audit-logs:update-geoip-db')
    ->weeklyOn(3, '03:00') // 3 = miercoles
    ->timezone('America/Mexico_City')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Resolver lat/lng/city de audit_logs nuevos cada hora. Batch chico
// (1000 IPs unicas) para que cada corrida termine en <5s.
Schedule::command('audit-logs:resolve-geo --limit=1000 --since="2 hours"')
    ->hourlyAt(15) // minute 15 de cada hora (despues del traffic peak en :00)
    ->timezone('America/Mexico_City')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
