<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Pipeline de seed default — usado por `php artisan db:seed --force`.
     *
     * Esta arquitectura sirve a 3 SOFOMs (instancias `*.lendus.app`) y
     * cada uno tiene su subdominio público asignado a un `tenants.domain`.
     * Como los 3 dominios existen permanentemente, los 3 tenants también
     * deben existir desde el primer deploy — si no, el subdomain resuelve
     * a un tenant fantasma y el frontend cae al fallback equivocado.
     *
     * Por eso este pipeline crea TODO:
     *   - demo.lendus.app           → Lendus Demo (sandbox/QA)
     *   - moneycapital.lendus.app   → MoneyCapital (con unified_auth_screen)
     *   - finatea.lendus.app        → Finatea
     *   - superadmin@lendus.mx      → SUPER_ADMIN global (sin tenant)
     *   - Templates pro + específicos de MC para TODOS los tenants
     *
     * Idempotente: re-correr no duplica (updateOrCreate por slug; templates
     * con firstOrCreate respetan ediciones del cliente).
     *
     * Para agregar un SOFOM nuevo:
     *   1. Crea su seeder (FooSeeder) y su frontend tenant config
     *      (frontend/tenants/foo.tenant.ts con domain = foo.lendus.app)
     *   2. Agrégalo a este pipeline (antes de los template seeders)
     *   3. Despliega — el siguiente `db:seed --force` lo activa
     */
    public function run(): void
    {
        $this->call([
            // 1) Tenants + branding + productos + staff per-tenant
            DemoDataSeeder::class,
            MoneyCapitalSeeder::class,
            FinateaSeeder::class,

            // 2) SUPER_ADMIN global (tenant_id NULL)
            GlobalSuperAdminSeeder::class,

            // 3) Notification templates — corren AL FINAL para aplicar a
            //    todos los tenants ya creados arriba (NotificationTemplateSeeder
            //    itera Tenant::all()).
            NotificationTemplateSeeder::class,
            MoneyCapitalNotificationSeeder::class,
        ]);
    }
}
