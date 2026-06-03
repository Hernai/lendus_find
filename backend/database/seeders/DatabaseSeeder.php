<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    /**
     * Seed default (fresh install). Solo crea el tenant `demo` con sus
     * productos, staff y notification templates profesionales — listo
     * para desarrollo, QA y como base inicial de producción.
     *
     * Los tenants reales (MoneyCapital, Finatea, etc.) se activan
     * manualmente cuando el SOFOM se incorpora, en este orden:
     *
     *   php artisan db:seed --class=MoneyCapitalSeeder --force
     *   php artisan db:seed --class=NotificationTemplateSeeder --force
     *   php artisan db:seed --class=MoneyCapitalNotificationSeeder --force
     *
     *   php artisan db:seed --class=FinateaSeeder --force
     *   php artisan db:seed --class=NotificationTemplateSeeder --force
     *
     * NotificationTemplateSeeder es idempotente y aplica los templates
     * profesionales a TODOS los tenants existentes en cada corrida.
     */
    public function run(): void
    {
        $this->call([
            DemoDataSeeder::class,
            NotificationTemplateSeeder::class,
        ]);
    }
}
