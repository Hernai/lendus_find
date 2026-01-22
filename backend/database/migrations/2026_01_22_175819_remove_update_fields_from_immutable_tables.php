<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove updated_at and updated_by columns from immutable audit/log tables.
     * These tables should only have created_at since records are never updated.
     */
    public function up(): void
    {
        // application_status_history: Remove updated_by and created_by
        // (uses changed_by and changed_by_type instead)
        Schema::table('application_status_history', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'updated_by']);
        });

        // otp_codes: Remove updated_at
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });

        // otp_requests: Remove updated_at
        Schema::table('otp_requests', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });

        // api_logs: Remove updated_at
        Schema::table('api_logs', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });

        // sms_logs: Remove updated_at
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // application_status_history: Restore updated_by and created_by
        Schema::table('application_status_history', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->after('created_at');
            $table->uuid('updated_by')->nullable()->after('created_by');
        });

        // otp_codes: Restore updated_at
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        // otp_requests: Restore updated_at
        Schema::table('otp_requests', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        // api_logs: Restore updated_at
        Schema::table('api_logs', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });

        // sms_logs: Restore updated_at
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });
    }
};
