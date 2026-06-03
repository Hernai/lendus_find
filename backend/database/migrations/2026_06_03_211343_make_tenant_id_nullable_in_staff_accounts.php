<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite super admin global (sin tenant): tenant_id nullable.
 *
 * El SUPER_ADMIN global puede ver y administrar cualquier tenant.
 * Los demás roles (ADMIN, SUPERVISOR, ANALYST) siguen requiriendo
 * tenant_id obligatorio — la validación se hace a nivel de aplicación
 * (AuthController + IdentifyTenant), no a nivel de schema.
 *
 * Cambios:
 *  - DROP FK existente (cascade delete) y recreate con onDelete:set null
 *    para que si se borra el tenant del staff, el campo quede NULL.
 *  - tenant_id pasa a nullable.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('staff_accounts', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->uuid('tenant_id')->nullable()->change();
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Antes de rollback hay que asegurarse de que no haya cuentas
        // con tenant_id NULL (asignar a un tenant o eliminarlas).
        Schema::table('staff_accounts', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->uuid('tenant_id')->nullable(false)->change();
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
        });
    }
};
