<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial y máquina de estado de las importaciones del catálogo de códigos
 * postales cargadas desde el panel.
 *
 * Es el registro de auditoría (quién cargó, desde qué tenant, qué archivo,
 * resultado) y a la vez el estado del import (PENDING_PARSE → PARSED →
 * APPLYING → APPLIED, o REJECTED). Su `id` es el `importId` que nombra el
 * canal Reverb de progreso. Tabla GLOBAL: sin tenant scoping — el catálogo es
 * compartido y todo SUPER_ADMIN debe ver el historial completo. El tenant de
 * origen queda como columna informativa, no como filtro
 * (ver cambio OpenSpec catalogo-cp-autoservicio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postal_code_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('staff_account_id')->nullable();   // actor (FK informativa)
            $table->uuid('origin_tenant_id')->nullable();   // tenant de origen (informativo)
            $table->string('origin_tenant_slug')->nullable();
            $table->string('original_filename');
            $table->string('status')->default('PENDING_PARSE');
            $table->integer('rows_count')->default(0);
            $table->integer('states_count')->default(0);
            $table->integer('municipalities_count')->default(0);
            $table->jsonb('sample')->nullable();            // muestra de filas para el preview
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_code_imports');
    }
};
