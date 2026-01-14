<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add audit fields (created_by, updated_by, deleted_by) to main tables.
     * Note: created_at, updated_at, deleted_at already exist via timestamps() and softDeletes()
     */
    public function up(): void
    {
        // Tables to add audit fields to
        $tables = [
            'tenants',
            'products',
            'applicants',
            'applications',
            'documents',
            'references',
            'application_notes',
            'webhooks',
            'addresses',
            'employment_records',
            'bank_accounts',
            'data_verifications',
            'tenant_branding',
            'tenant_api_configs',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    // Add created_by, updated_by, deleted_by (UUID references to users table)
                    if (!Schema::hasColumn($tableName, 'created_by')) {
                        $table->uuid('created_by')->nullable()->after('created_at');
                        $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                    }
                    if (!Schema::hasColumn($tableName, 'updated_by')) {
                        $table->uuid('updated_by')->nullable()->after('updated_at');
                        $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
                    }

                    // Only add deleted_by if table has soft deletes
                    if (Schema::hasColumn($tableName, 'deleted_at') && !Schema::hasColumn($tableName, 'deleted_by')) {
                        $table->uuid('deleted_by')->nullable()->after('deleted_at');
                        $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'tenants',
            'products',
            'applicants',
            'applications',
            'documents',
            'references',
            'application_notes',
            'webhooks',
            'addresses',
            'employment_records',
            'bank_accounts',
            'data_verifications',
            'tenant_branding',
            'tenant_api_configs',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    // Drop foreign keys first (use constraint naming convention)
                    $table->dropForeign([' created_by']);
                    $table->dropForeign(['updated_by']);
                    if (Schema::hasColumn($tableName, 'deleted_by')) {
                        $table->dropForeign(['deleted_by']);
                    }

                    // Drop columns
                    if (Schema::hasColumn($tableName, 'created_by')) {
                        $table->dropColumn('created_by');
                    }
                    if (Schema::hasColumn($tableName, 'updated_by')) {
                        $table->dropColumn('updated_by');
                    }
                    if (Schema::hasColumn($tableName, 'deleted_by')) {
                        $table->dropColumn('deleted_by');
                    }
                });
            }
        }
    }
};
