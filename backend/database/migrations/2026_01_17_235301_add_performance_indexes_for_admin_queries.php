<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds performance indexes to optimize admin panel queries.
 *
 * These indexes support:
 * - Application filtering by status + date range
 * - Stale application detection (status + updated_at)
 * - Document status filtering
 * - Data verification lookups
 * - Reference verification status
 * - API log queries by provider/service
 */
return new class extends Migration
{
    public function up(): void
    {
        // Applications table - optimize admin list queries
        Schema::table('applications', function (Blueprint $table) {
            // Composite index for stale application queries
            // (where status in [...] and updated_at < X)
            $table->index(['tenant_id', 'status', 'updated_at'], 'idx_applications_stale');

            // Composite index for filtering by assigned_to + status
            $table->index(['tenant_id', 'assigned_to', 'status'], 'idx_applications_assigned_status');

            // Index for product filtering
            $table->index(['tenant_id', 'product_id', 'status'], 'idx_applications_product_status');
        });

        // Documents table - optimize document review queries
        Schema::table('documents', function (Blueprint $table) {
            // Index for filtering documents by status within application
            $table->index(['application_id', 'status'], 'idx_documents_app_status');

            // Index for tenant-wide document stats
            $table->index(['tenant_id', 'status', 'created_at'], 'idx_documents_tenant_status');
        });

        // Data verifications - optimize field verification lookups
        Schema::table('data_verifications', function (Blueprint $table) {
            // Index for getting most recent verification per field
            $table->index(['applicant_id', 'field_name', 'created_at'], 'idx_verifications_field_date');

            // Index for locked field lookups
            $table->index(['applicant_id', 'is_verified', 'is_locked'], 'idx_verifications_locked');
        });

        // References table - optimize verification status queries
        Schema::table('references', function (Blueprint $table) {
            // Index for unverified references lookup
            $table->index(['application_id', 'is_verified'], 'idx_references_verified');
        });

        // Bank accounts - optimize verification queries
        Schema::table('bank_accounts', function (Blueprint $table) {
            // Index for primary account lookup
            $table->index(['applicant_id', 'is_primary', 'is_active'], 'idx_bank_accounts_primary');

            // Index for verification status
            $table->index(['applicant_id', 'is_verified'], 'idx_bank_accounts_verified');
        });

        // Audit logs - optimize document history queries
        Schema::table('audit_logs', function (Blueprint $table) {
            // Composite index for document history lookup
            $table->index(['entity_type', 'entity_id', 'created_at'], 'idx_audit_entity_date');
        });

        // API logs - optimize provider/service filtering
        if (Schema::hasTable('api_logs')) {
            Schema::table('api_logs', function (Blueprint $table) {
                // Index for filtering by provider
                $table->index(['applicant_id', 'provider', 'created_at'], 'idx_api_logs_provider');

                // Index for success/failure stats
                $table->index(['tenant_id', 'success', 'created_at'], 'idx_api_logs_success');
            });
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('idx_applications_stale');
            $table->dropIndex('idx_applications_assigned_status');
            $table->dropIndex('idx_applications_product_status');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_documents_app_status');
            $table->dropIndex('idx_documents_tenant_status');
        });

        Schema::table('data_verifications', function (Blueprint $table) {
            $table->dropIndex('idx_verifications_field_date');
            $table->dropIndex('idx_verifications_locked');
        });

        Schema::table('references', function (Blueprint $table) {
            $table->dropIndex('idx_references_verified');
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_bank_accounts_primary');
            $table->dropIndex('idx_bank_accounts_verified');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_entity_date');
        });

        if (Schema::hasTable('api_logs')) {
            Schema::table('api_logs', function (Blueprint $table) {
                $table->dropIndex('idx_api_logs_provider');
                $table->dropIndex('idx_api_logs_success');
            });
        }
    }
};
