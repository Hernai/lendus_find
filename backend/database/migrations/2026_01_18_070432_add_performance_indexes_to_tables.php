<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add performance indexes to frequently queried tables.
 *
 * These indexes optimize:
 * - OTP lookups by target and status
 * - API logs filtering by tenant and date
 * - Bank account lookups by owner and primary status
 * - Document queries by documentable and status
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Índices para otp_requests - optimiza verificación de OTP
        if (Schema::hasTable('otp_requests')) {
            Schema::table('otp_requests', function (Blueprint $table) {
                // Índice para búsqueda de OTP por target y fecha (rate limiting)
                $table->index(
                    ['target_type', 'target_value', 'created_at'],
                    'otp_requests_target_created_idx'
                );
                // Índice para verificación de OTP válido
                $table->index(
                    ['target_type', 'target_value', 'expires_at', 'verified_at'],
                    'otp_requests_target_validity_idx'
                );
            });
        }

        // Índices para api_logs - optimiza dashboard y reportes
        if (Schema::hasTable('api_logs')) {
            Schema::table('api_logs', function (Blueprint $table) {
                // Índice para filtrado por tenant y fecha
                $table->index(['tenant_id', 'created_at'], 'api_logs_tenant_created_idx');
                // Índice para estadísticas de éxito/fallo
                $table->index(['tenant_id', 'success', 'created_at'], 'api_logs_tenant_success_idx');
            });
        }

        // Índices para person_bank_accounts - optimiza búsqueda de cuenta primaria
        if (Schema::hasTable('person_bank_accounts')) {
            Schema::table('person_bank_accounts', function (Blueprint $table) {
                // Índice para búsqueda de cuenta primaria por owner
                $table->index(
                    ['owner_type', 'owner_id', 'is_primary'],
                    'person_bank_accounts_owner_primary_idx'
                );
                // Índice para búsqueda de cuenta de desembolso
                $table->index(
                    ['owner_type', 'owner_id', 'is_for_disbursement'],
                    'person_bank_accounts_owner_disbursement_idx'
                );
            });
        }

        // Índices para documents_v2 - optimiza queries polimórficas
        if (Schema::hasTable('documents_v2')) {
            Schema::table('documents_v2', function (Blueprint $table) {
                // Índice para búsqueda por documentable y estado
                $table->index(
                    ['documentable_type', 'documentable_id', 'status'],
                    'documents_v2_documentable_status_idx'
                );
                // Índice para documentos pendientes de revisión
                $table->index(
                    ['tenant_id', 'status', 'created_at'],
                    'documents_v2_tenant_status_idx'
                );
            });
        }

        // Índices para applications_v2 - optimiza listados y filtros
        if (Schema::hasTable('applications_v2')) {
            Schema::table('applications_v2', function (Blueprint $table) {
                // Índice para listado por tenant y estado
                $table->index(
                    ['tenant_id', 'status', 'created_at'],
                    'applications_v2_tenant_status_idx'
                );
                // Índice para aplicaciones asignadas
                $table->index(
                    ['tenant_id', 'assigned_to_id', 'status'],
                    'applications_v2_assigned_status_idx'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('otp_requests')) {
            Schema::table('otp_requests', function (Blueprint $table) {
                $table->dropIndex('otp_requests_target_created_idx');
                $table->dropIndex('otp_requests_target_validity_idx');
            });
        }

        if (Schema::hasTable('api_logs')) {
            Schema::table('api_logs', function (Blueprint $table) {
                $table->dropIndex('api_logs_tenant_created_idx');
                $table->dropIndex('api_logs_tenant_success_idx');
            });
        }

        if (Schema::hasTable('person_bank_accounts')) {
            Schema::table('person_bank_accounts', function (Blueprint $table) {
                $table->dropIndex('person_bank_accounts_owner_primary_idx');
                $table->dropIndex('person_bank_accounts_owner_disbursement_idx');
            });
        }

        if (Schema::hasTable('documents_v2')) {
            Schema::table('documents_v2', function (Blueprint $table) {
                $table->dropIndex('documents_v2_documentable_status_idx');
                $table->dropIndex('documents_v2_tenant_status_idx');
            });
        }

        if (Schema::hasTable('applications_v2')) {
            Schema::table('applications_v2', function (Blueprint $table) {
                $table->dropIndex('applications_v2_tenant_status_idx');
                $table->dropIndex('applications_v2_assigned_status_idx');
            });
        }
    }
};
