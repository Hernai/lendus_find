<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds missing foreign key constraints and indexes for data integrity.
 *
 * Issues addressed:
 * 1. users.tenant_id - Missing FK constraint (allows orphaned users)
 * 2. otp_codes.tenant_id - Missing FK constraint (allows orphaned OTP codes)
 * 3. Soft delete columns - Missing indexes (slow queries filtering deleted records)
 * 4. Audit columns - Missing indexes (slow queries filtering by creator/updater)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add FK constraint to users.tenant_id
        Schema::table('users', function (Blueprint $table) {
            // First ensure all existing users have valid tenant_id or null
            // FK with nullOnDelete allows users without tenant (super admins)
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->nullOnDelete();
        });

        // 2. Add FK constraint to otp_codes.tenant_id
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            // Add useful indexes for OTP queries
            $table->index(['tenant_id', 'is_used'], 'idx_otp_tenant_used');
            $table->index(['phone', 'is_used', 'expires_at'], 'idx_otp_phone_valid');
            $table->index(['email', 'is_used', 'expires_at'], 'idx_otp_email_valid');
        });

        // 3. Add indexes on deleted_at for soft delete tables
        $softDeleteTables = [
            'tenants',
            'products',
            'applicants',
            'applications',
            'documents',
            'references',
            'application_notes',
            'addresses',
            'employment_records',
            'bank_accounts',
        ];

        foreach ($softDeleteTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->index('deleted_at', "idx_{$tableName}_deleted");
                });
            }
        }

        // 4. Add indexes on audit columns (created_by, updated_by) for tables that have them
        $auditTables = [
            'applicants',
            'applications',
            'documents',
            'addresses',
            'employment_records',
            'bank_accounts',
            'references',
        ];

        foreach ($auditTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'created_by')) {
                        $table->index('created_by', "idx_{$tableName}_created_by");
                    }
                    if (Schema::hasColumn($tableName, 'updated_by')) {
                        $table->index('updated_by', "idx_{$tableName}_updated_by");
                    }
                });
            }
        }
    }

    public function down(): void
    {
        // Remove FK from users
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
        });

        // Remove FK and indexes from otp_codes
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex('idx_otp_tenant_used');
            $table->dropIndex('idx_otp_phone_valid');
            $table->dropIndex('idx_otp_email_valid');
        });

        // Remove soft delete indexes
        $softDeleteTables = [
            'tenants',
            'products',
            'applicants',
            'applications',
            'documents',
            'references',
            'application_notes',
            'addresses',
            'employment_records',
            'bank_accounts',
        ];

        foreach ($softDeleteTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->dropIndex("idx_{$tableName}_deleted");
                });
            }
        }

        // Remove audit indexes
        $auditTables = [
            'applicants',
            'applications',
            'documents',
            'addresses',
            'employment_records',
            'bank_accounts',
            'references',
        ];

        foreach ($auditTables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'created_by')) {
                        $table->dropIndex("idx_{$tableName}_created_by");
                    }
                    if (Schema::hasColumn($tableName, 'updated_by')) {
                        $table->dropIndex("idx_{$tableName}_updated_by");
                    }
                });
            }
        }
    }
};
