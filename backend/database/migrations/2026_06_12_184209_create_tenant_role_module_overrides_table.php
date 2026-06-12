<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overrides granulares de módulos del backoffice por (tenant, rol, módulo).
 *
 * El default de cada módulo lo declara el frontend en
 * `frontend/src/constants/admin-modules.ts` (ej. "reports" → SUPERVISOR
 * + ADMIN por default). Esta tabla solo guarda EXCEPCIONES — si no hay
 * fila, gana el default.
 *
 * Quién edita: solo SUPER_ADMIN global desde
 * `/admin/configuracion/modulos`. La validación se hace en el endpoint.
 *
 * No tocamos `Tenant.features` (legacy): los features siguen viviendo
 * en el JSON del tenant para flags como `unified_auth_screen`,
 * `loan_portfolio`, etc. Esta tabla es una CAPA ADICIONAL que cabalga
 * encima del default por rol del catálogo.
 *
 * Ejemplo:
 *   tenant=demo, role=ANALYST, module_key=reports, enabled=true
 *     → en demo los analistas ven el módulo Reportes (default era false)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_role_module_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            // Roles de StaffAccount: ANALYST, SUPERVISOR, ADMIN.
            // (SUPER_ADMIN se omite — siempre lo ve todo.)
            $table->string('role', 32);
            $table->string('module_key', 64);
            $table->boolean('enabled');
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Una sola fila por (tenant, role, module).
            $table->unique(['tenant_id', 'role', 'module_key'], 'tenant_role_module_unique');
            $table->index(['tenant_id', 'role'], 'tenant_role_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_role_module_overrides');
    }
};
