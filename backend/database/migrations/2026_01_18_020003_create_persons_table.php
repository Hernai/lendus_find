<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the persons table - base entity for individual data.
 *
 * A person represents a physical individual (persona física).
 * This table only contains immutable or rarely-changing data.
 *
 * Related tables store:
 * - person_identifications: CURP, RFC, INE, etc. (with history)
 * - person_addresses: Addresses (with history)
 * - person_employments: Employment records (with history)
 * - person_references: Personal/work references
 * - person_bank_accounts: Bank accounts for disbursement
 *
 * The "applicant" concept is contextual:
 * - A person becomes an "applicant" when they submit an application
 * - applications.person_id links to this table
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================
        // PERSONS - Base table for individuals
        // =====================================================
        Schema::create('persons', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Tenant relationship
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Link to authentication account
            $table->uuid('account_id')->nullable()->unique();
            $table->foreign('account_id')
                ->references('id')
                ->on('applicant_accounts')
                ->nullOnDelete();

            // =====================================================
            // Name (rarely changes)
            // =====================================================
            $table->string('first_name');
            $table->string('last_name_1'); // Apellido paterno
            $table->string('last_name_2')->nullable(); // Apellido materno
            // full_name is computed in the model via accessor (SQLite doesn't support CONCAT)

            // =====================================================
            // Birth data (immutable)
            // =====================================================
            $table->date('birth_date')->nullable();
            $table->string('birth_state', 5)->nullable(); // Estado: CDMX, JAL, MEX, etc.
            $table->string('birth_country', 3)->default('MX'); // ISO 3166-1 alpha-3

            // =====================================================
            // Demographics
            // =====================================================
            $table->string('gender', 1)->nullable(); // M, F
            $table->string('nationality', 3)->default('MX');
            $table->string('marital_status', 20)->nullable();
            // SINGLE, MARRIED, DIVORCED, WIDOWED, FREE_UNION

            // =====================================================
            // Education (current level)
            // =====================================================
            $table->string('education_level', 30)->nullable();
            // PRIMARY, SECONDARY, HIGH_SCHOOL, BACHELOR, MASTER, DOCTORATE, TECHNICAL

            // =====================================================
            // Dependents
            // =====================================================
            $table->smallInteger('dependents_count')->default(0);

            // =====================================================
            // Profile completeness tracking
            // =====================================================
            $table->smallInteger('profile_completeness')->default(0); // 0-100%
            $table->jsonb('missing_data')->nullable();
            // Example: ["birth_date", "marital_status"]

            // =====================================================
            // KYC Status (aggregated from verifications)
            // =====================================================
            $table->string('kyc_status', 20)->default('PENDING');
            // PENDING, IN_PROGRESS, VERIFIED, REJECTED, EXPIRED
            $table->timestamp('kyc_verified_at')->nullable();
            $table->uuid('kyc_verified_by')->nullable();
            $table->jsonb('kyc_data')->nullable();
            // Aggregated KYC results from various verifications

            // =====================================================
            // Audit
            // =====================================================
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            // =====================================================
            // Indexes
            // =====================================================
            $table->index('tenant_id');
            $table->index('kyc_status');
            $table->index(['tenant_id', 'kyc_status']);
            $table->index('created_at');

            // For searching by name
            $table->index(['last_name_1', 'last_name_2', 'first_name']);
        });

        // =====================================================
        // Add person_id to applicant_accounts (circular reference)
        // =====================================================
        Schema::table('applicant_accounts', function (Blueprint $table) {
            $table->uuid('person_id')->nullable()->after('tenant_id');
            $table->foreign('person_id')
                ->references('id')
                ->on('persons')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applicant_accounts', function (Blueprint $table) {
            $table->dropForeign(['person_id']);
            $table->dropColumn('person_id');
        });

        Schema::dropIfExists('persons');
    }
};
