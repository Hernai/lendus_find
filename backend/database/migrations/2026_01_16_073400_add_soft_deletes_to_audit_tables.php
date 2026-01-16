<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds soft deletes to audit-related tables for data retention compliance.
     * This allows "deleting" records while maintaining audit trail.
     */
    public function up(): void
    {
        // Add soft deletes to otp_codes (for audit trail of OTP attempts)
        if (Schema::hasTable('otp_codes') && !Schema::hasColumn('otp_codes', 'deleted_at')) {
            Schema::table('otp_codes', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add soft deletes to webhooks (for tracking webhook history)
        if (Schema::hasTable('webhooks') && !Schema::hasColumn('webhooks', 'deleted_at')) {
            Schema::table('webhooks', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add soft deletes to sms_logs (for SMS audit trail)
        if (Schema::hasTable('sms_logs') && !Schema::hasColumn('sms_logs', 'deleted_at')) {
            Schema::table('sms_logs', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Add soft deletes to api_logs (for API call audit trail)
        if (Schema::hasTable('api_logs') && !Schema::hasColumn('api_logs', 'deleted_at')) {
            Schema::table('api_logs', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Note: audit_logs table intentionally does NOT have soft deletes
        // as it should be immutable for compliance purposes
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['otp_codes', 'webhooks', 'sms_logs', 'api_logs'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
