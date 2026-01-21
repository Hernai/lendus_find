<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add created_by and updated_by audit columns to business tables that are missing them.
 *
 * Tables that DON'T need audit columns (system/logs):
 * - migrations, password_reset_tokens, sessions, cache, cache_locks
 * - jobs, job_batches, failed_jobs, personal_access_tokens
 * - api_logs, sms_logs, audit_logs (already audit/log tables)
 * - otp_codes, otp_requests (ephemeral)
 */
return new class extends Migration
{
    /**
     * Tables that need audit columns added.
     */
    private array $tables = [
        'applicant_accounts',
        'applicant_identities',
        'staff_profiles',
        'application_status_history',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    if (!Schema::hasColumn($table, 'created_by')) {
                        $t->uuid('created_by')->nullable()->after('updated_at');
                    }
                    if (!Schema::hasColumn($table, 'updated_by')) {
                        $t->uuid('updated_by')->nullable()->after('created_by');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    if (Schema::hasColumn($table, 'created_by')) {
                        $t->dropColumn('created_by');
                    }
                    if (Schema::hasColumn($table, 'updated_by')) {
                        $t->dropColumn('updated_by');
                    }
                });
            }
        }
    }
};
