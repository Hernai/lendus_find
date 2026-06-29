<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluaciones de riesgo por solicitante (Nubarium y, a futuro, PLD / Buró /
 * Círculo de Crédito). Tabla genérica y extensible: una fila por evaluación.
 *
 * Hoy: phone_risk / email_risk (Nubarium API Plus), disparadas al confirmar el
 * OTP. La verificación ocurre a veces antes de existir la Person, por eso se
 * liga a account_id y, si existe, person_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('account_id')->nullable();   // ApplicantAccount
            $table->uuid('person_id')->nullable();
            // phone_risk | email_risk | (futuro) pld | credit_bureau | circulo
            $table->string('type', 40);
            $table->string('provider', 40)->default('nubarium');
            $table->string('identifier')->nullable();  // teléfono/correo evaluado
            $table->string('status', 20)->default('completed'); // completed | failed
            $table->integer('score')->nullable();
            $table->string('level', 40)->nullable();   // very-low/low/.. o LOW/MEDIUM/HIGH
            $table->string('recommendation', 40)->nullable();
            $table->jsonb('result')->nullable();       // respuesta cruda de Nubarium
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
            $table->index('account_id');
            $table->index('person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};
