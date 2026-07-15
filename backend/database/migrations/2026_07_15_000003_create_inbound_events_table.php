<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotencia y auditoría de eventos ENTRANTES (cartera → LendusFind):
 * confirmación de dispersión, pago aplicado, acuse de ingesta. Un
 * external_event_id repetido resuelve como 'duplicate' sin re-procesar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('endpoint_id')->nullable();
            $table->string('external_event_id');
            $table->string('type'); // disbursement | payment | ingest_ack
            $table->jsonb('payload')->nullable();
            $table->string('status'); // processed | duplicate | failed
            $table->jsonb('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'external_event_id']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_events');
    }
};
