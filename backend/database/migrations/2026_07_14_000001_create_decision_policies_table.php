<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Políticas del motor de decisión, versionadas e inmutables una vez creadas.
 *
 * Alcance dual: product_id NULL = política de tenant (filtro telefónico,
 * cooldown); product_id presente = política de producto (scoring, bandas,
 * oferta, graduación). Editar crea una versión nueva; solo una versión puede
 * estar activa por alcance. Ver design de motor-decision-configurable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('product_id')->nullable();
            $table->unsignedInteger('version');
            $table->string('mode', 10)->default('OFF'); // OFF | SHADOW | ACTIVE
            $table->boolean('is_active')->default(false);
            $table->jsonb('rules');
            $table->string('notes', 500)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->uuid('activated_by')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'product_id']);
        });

        // Postgres trata los NULL como distintos en índices únicos, por lo que el
        // alcance de tenant (product_id NULL) necesita sus propios índices parciales.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX decision_policies_product_version_unique
            ON decision_policies (tenant_id, product_id, version)
            WHERE product_id IS NOT NULL AND deleted_at IS NULL
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX decision_policies_tenant_version_unique
            ON decision_policies (tenant_id, version)
            WHERE product_id IS NULL AND deleted_at IS NULL
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX decision_policies_product_active_unique
            ON decision_policies (tenant_id, product_id)
            WHERE product_id IS NOT NULL AND is_active AND deleted_at IS NULL
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX decision_policies_tenant_active_unique
            ON decision_policies (tenant_id)
            WHERE product_id IS NULL AND is_active AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_policies');
    }
};
