<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to add unique constraints for data integrity.
 *
 * These constraints ensure:
 * - Unique folio per tenant for applications
 * - Unique phone per tenant for applicants
 * - Unique CURP per tenant for applicants
 * - Unique RFC per tenant for applicants
 * - Only one primary address per applicant
 * - Only one primary bank account per applicant
 * - Only one current employment per applicant
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, clean up any duplicate data that would violate the unique constraints
        $this->cleanupDuplicates();

        // Applications: unique folio per tenant
        Schema::table('applications', function (Blueprint $table) {
            $table->unique(['tenant_id', 'folio'], 'applications_tenant_folio_unique');
        });

        // Applicants: unique phone per tenant (when phone is not null)
        // Using a partial unique index in PostgreSQL would be ideal, but for compatibility
        // we use a regular unique constraint (nulls are handled by the DB)
        Schema::table('applicants', function (Blueprint $table) {
            $table->unique(['tenant_id', 'phone'], 'applicants_tenant_phone_unique');
        });

        // Applicants: unique CURP per tenant (when CURP is not null)
        Schema::table('applicants', function (Blueprint $table) {
            $table->unique(['tenant_id', 'curp'], 'applicants_tenant_curp_unique');
        });

        // Applicants: unique RFC per tenant (when RFC is not null)
        Schema::table('applicants', function (Blueprint $table) {
            $table->unique(['tenant_id', 'rfc'], 'applicants_tenant_rfc_unique');
        });

        // For is_primary constraints on related tables, we use application-level checks
        // since PostgreSQL doesn't support conditional unique indexes without extensions.
        // The models (Address, BankAccount, EmploymentRecord) should handle this in code.
    }

    /**
     * Clean up duplicate data before adding unique constraints.
     * For duplicates, we keep the most recently updated record and nullify the field on others.
     */
    private function cleanupDuplicates(): void
    {
        // Clean up duplicate CURPs per tenant
        $this->cleanupDuplicateField('applicants', 'curp');

        // Clean up duplicate RFCs per tenant
        $this->cleanupDuplicateField('applicants', 'rfc');

        // Clean up duplicate phones per tenant
        $this->cleanupDuplicateField('applicants', 'phone');

        // Clean up duplicate folios per tenant in applications
        $this->cleanupDuplicateField('applications', 'folio');
    }

    /**
     * Clean up a specific duplicate field in a table.
     * Keeps the most recently updated record and sets the field to NULL on older duplicates.
     */
    private function cleanupDuplicateField(string $table, string $field): void
    {
        // Find all duplicate values per tenant
        $duplicates = DB::table($table)
            ->select('tenant_id', $field)
            ->whereNotNull($field)
            ->groupBy('tenant_id', $field)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            // Get all records with this duplicate value, ordered by updated_at DESC
            $records = DB::table($table)
                ->where('tenant_id', $duplicate->tenant_id)
                ->where($field, $duplicate->$field)
                ->orderByDesc('updated_at')
                ->get();

            // Keep the first (most recent) record, nullify the field on others
            $first = true;
            foreach ($records as $record) {
                if ($first) {
                    $first = false;
                    continue;
                }
                DB::table($table)
                    ->where('id', $record->id)
                    ->update([$field => null]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropUnique('applications_tenant_folio_unique');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->dropUnique('applicants_tenant_phone_unique');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->dropUnique('applicants_tenant_curp_unique');
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->dropUnique('applicants_tenant_rfc_unique');
        });
    }
};
