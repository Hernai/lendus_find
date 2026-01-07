<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First add as nullable
        Schema::table('application_notes', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('id');
        });

        // Populate tenant_id from application
        DB::statement('
            UPDATE application_notes
            SET tenant_id = applications.tenant_id
            FROM applications
            WHERE application_notes.application_id = applications.id
        ');

        // Now make it not nullable and add constraints
        Schema::table('application_notes', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable(false)->change();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_notes', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
