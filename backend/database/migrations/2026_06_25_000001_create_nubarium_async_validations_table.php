<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Almacén de validaciones ASÍNCRONAS de Nubarium (por webhook).
 *
 * Servicios de Nubarium como validar CLABE, validar tarjeta de débito, IMSS e
 * ISSSTE no responden el resultado en la misma llamada: regresan un
 * `validationCode` y publican el resultado a una URL de callback nuestra más
 * tarde. Esta tabla guarda cada validación pendiente y su resultado cuando
 * llega el webhook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nubarium_async_validations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            // clabe | debit_card | imss_nss | imss_employment | issste
            $table->string('type', 40);
            // Secreto aleatorio embebido en la URL de callback (autentica el webhook).
            $table->string('callback_token', 80)->unique();
            // codigoValidacion que regresa Nubarium al iniciar; clave de correlación.
            $table->string('validation_code')->nullable()->index();
            // pending | completed | failed
            $table->string('status', 20)->default('pending');
            // Lo que enviamos (name, clabe, curp, ...). Sin datos sensibles crudos extra.
            $table->jsonb('request_payload')->nullable();
            // Payload tal cual lo manda el webhook de Nubarium.
            $table->jsonb('result')->nullable();
            $table->string('error')->nullable();
            // Vínculo opcional con la entidad validada (BankAccount, Person, ...).
            $table->string('entity_type')->nullable();
            $table->uuid('entity_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nubarium_async_validations');
    }
};
