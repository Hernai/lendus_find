<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds tenant_id to references table for proper multi-tenancy scoping.
     * This was missing and caused potential cross-tenant data leakage.
     */
    public function up(): void
    {
        Schema::table('references', function (Blueprint $table) {
            // Add tenant_id column after id
            $table->uuid('tenant_id')->nullable()->after('id');
        });

        // Populate tenant_id from the related application's tenant
        // Note: 'references' is a reserved word in PostgreSQL, SQLite, etc., so we use quotes
        DB::statement('
            UPDATE "references"
            SET tenant_id = (
                SELECT applications.tenant_id
                FROM applications
                WHERE applications.id = "references".application_id
            )
            WHERE tenant_id IS NULL
        ');

        // Now make it non-nullable and add foreign key
        Schema::table('references', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable(false)->change();
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Add index for better query performance
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('references', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
