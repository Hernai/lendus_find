<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Endpoints suscriptores de webhooks por tenant. Cada uno tiene su secreto de
 * firma (encriptado con Crypt), la lista de eventos suscritos y flags de
 * estado. La tabla `webhooks` existente pasa a ser el log de entregas y
 * referencia a este endpoint. Ver cambio OpenSpec integracion-webhooks-cartera.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('url');
            $table->text('secret'); // encriptado (Crypt) — HMAC de las entregas
            $table->jsonb('events'); // ["application.approved", ...] o ["*"]
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sandbox')->default(false);
            $table->string('description', 500)->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
