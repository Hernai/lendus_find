<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría del motor de decisión: una fila por evaluación (submit, renovación,
 * gate telefónico o dry-run del probador), con la política/versión aplicada,
 * los insumos congelados, las reglas disparadas y la salida. `executed=false`
 * en modo sombra y dry-run.
 *
 * application_id es nullable: el gate telefónico corre antes de existir la
 * solicitud y el dry-run nunca toca solicitudes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_decisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id')->nullable();
            $table->uuid('person_id')->nullable();
            $table->uuid('account_id')->nullable();   // gate telefónico pre-Person
            $table->uuid('loan_id')->nullable();      // trigger de renovación
            $table->uuid('decision_policy_id');
            $table->unsignedInteger('policy_version');
            $table->string('trigger', 20);            // SUBMIT | RENEWAL | PHONE_GATE | DRY_RUN
            $table->string('mode', 10);               // SHADOW | ACTIVE
            $table->jsonb('inputs')->nullable();
            $table->jsonb('rule_hits')->nullable();
            $table->integer('score')->nullable();
            $table->string('band', 40)->nullable();
            $table->string('outcome', 20);            // OFFER | REVIEW | REJECT | NO_OFFER | BLOCK | ALLOW | FLAG
            $table->jsonb('outcome_detail')->nullable();
            $table->boolean('executed')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'trigger']);
            $table->index('application_id');
            $table->index('person_id');
            $table->index('loan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_decisions');
    }
};
