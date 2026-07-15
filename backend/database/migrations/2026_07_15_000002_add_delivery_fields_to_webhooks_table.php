<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reactiva la tabla `webhooks` (dormida) como LOG DE ENTREGAS: cada fila es un
 * intento de entrega de un evento a un endpoint. `event_id` es el mismo para
 * todas las entregas de un evento (idempotencia del receptor); `idempotency_key`
 * es único por (endpoint, event_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->uuid('webhook_endpoint_id')->nullable()->after('tenant_id');
            $table->string('event_id')->nullable()->after('event'); // "evt_<ulid>" — idempotencia del receptor
            $table->string('idempotency_key')->nullable()->after('event_id');
            $table->text('signature')->nullable()->after('response_body');

            $table->foreign('webhook_endpoint_id')->references('id')->on('webhook_endpoints')->cascadeOnDelete();
            $table->unique(['webhook_endpoint_id', 'event_id'], 'webhooks_endpoint_event_unique');
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropForeign(['webhook_endpoint_id']);
            $table->dropUnique('webhooks_endpoint_event_unique');
            $table->dropIndex(['event_id']);
            $table->dropColumn(['webhook_endpoint_id', 'event_id', 'idempotency_key', 'signature']);
        });
    }
};
