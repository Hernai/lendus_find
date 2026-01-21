<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop legacy V1 tables that are no longer used.
 *
 * The V2 architecture uses:
 * - staff_accounts + staff_profiles (instead of users for staff)
 * - applicant_accounts + persons (instead of applicants)
 * - person_addresses (instead of addresses)
 * - person_employments (instead of employment_records)
 * - person_bank_accounts (instead of bank_accounts)
 * - person_references (instead of references)
 * - documents_v2 (instead of documents)
 * - applications_v2 (instead of applications)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Helper function to drop FK if it exists
        $dropFk = function (string $table, string $constraint) {
            $exists = DB::selectOne("
                SELECT 1 FROM information_schema.table_constraints
                WHERE constraint_name = ? AND table_name = ?
            ", [$constraint, $table]);

            if ($exists) {
                Schema::table($table, function (Blueprint $t) use ($constraint) {
                    $t->dropForeign($constraint);
                });
            }
        };

        // Drop FKs from applications_v2 that reference legacy tables
        $dropFk('applications_v2', 'applications_v2_company_id_foreign');
        $dropFk('applications_v2', 'applications_v2_submitted_by_member_id_foreign');

        // Drop FKs from api_logs
        $dropFk('api_logs', 'api_logs_applicant_id_foreign');
        $dropFk('api_logs', 'api_logs_application_id_foreign');
        $dropFk('api_logs', 'api_logs_user_id_foreign');

        // Drop FKs from data_verifications
        $dropFk('data_verifications', 'data_verifications_applicant_id_foreign');
        $dropFk('data_verifications', 'data_verifications_verified_by_foreign');
        $dropFk('data_verifications', 'data_verifications_created_by_foreign');
        $dropFk('data_verifications', 'data_verifications_updated_by_foreign');

        // Drop FKs from audit_logs
        $dropFk('audit_logs', 'audit_logs_user_id_foreign');

        // Drop FKs from tenants
        $dropFk('tenants', 'tenants_created_by_foreign');
        $dropFk('tenants', 'tenants_updated_by_foreign');
        $dropFk('tenants', 'tenants_deleted_by_foreign');

        // Drop FKs from products
        $dropFk('products', 'products_created_by_foreign');
        $dropFk('products', 'products_updated_by_foreign');
        $dropFk('products', 'products_deleted_by_foreign');

        // Drop FKs from tenant_branding
        $dropFk('tenant_branding', 'tenant_branding_created_by_foreign');
        $dropFk('tenant_branding', 'tenant_branding_updated_by_foreign');

        // Drop FKs from tenant_api_configs
        $dropFk('tenant_api_configs', 'tenant_api_configs_created_by_foreign');
        $dropFk('tenant_api_configs', 'tenant_api_configs_updated_by_foreign');

        // Drop FKs from webhooks
        if (Schema::hasTable('webhooks')) {
            $dropFk('webhooks', 'webhooks_created_by_foreign');
            $dropFk('webhooks', 'webhooks_updated_by_foreign');
        }

        // 1. Drop webhook_logs (not used, WebhookLog model deleted)
        Schema::dropIfExists('webhook_logs');

        // 2. Drop company-related tables (Company controllers deleted, not used in frontend)
        Schema::dropIfExists('company_addresses');
        Schema::dropIfExists('company_members');
        Schema::dropIfExists('companies');

        // 3. Drop V1 applicant-related tables (replaced by person_* tables)
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('employment_records');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('references');

        // 4. Drop V1 application-related tables (replaced by applications_v2, documents_v2)
        Schema::dropIfExists('application_notes');
        // Note: application_status_history is a V2 table created in
        // 2026_01_18_020010_create_new_applications_table.php - do NOT drop
        Schema::dropIfExists('documents');
        Schema::dropIfExists('applications');

        // 5. Drop V1 applicants table (replaced by applicant_accounts + persons)
        Schema::dropIfExists('applicants');

        // 6. Drop V1 users table (replaced by staff_accounts for staff, applicant_accounts for applicants)
        Schema::dropIfExists('users');

        // Note: applicant_identities and otp_requests are V2 tables created in
        // 2026_01_18_020002_create_applicant_authentication_tables.php
        // They are NOT legacy and must NOT be dropped.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is destructive and cannot be reversed.
        // To restore these tables, run the original migrations.
        throw new \RuntimeException(
            'This migration cannot be reversed. The legacy V1 tables have been permanently removed. ' .
            'To restore them, you would need to run the original V1 migrations.'
        );
    }
};
