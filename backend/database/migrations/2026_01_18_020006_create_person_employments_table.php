<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates person_employments table with employment history.
 *
 * Stores current and past employment records for credit analysis.
 * Tracks income, employer data, and verification status.
 *
 * Employment history is important for:
 * - Evaluating job stability
 * - Verifying income claims
 * - Understanding career progression
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================
        // PERSON_EMPLOYMENTS - Employment history
        // =====================================================
        Schema::create('person_employments', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Tenant relationship
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Person relationship
            $table->uuid('person_id');
            $table->foreign('person_id')
                ->references('id')
                ->on('persons')
                ->onDelete('cascade');

            // =====================================================
            // Employment Type
            // =====================================================
            $table->string('employment_type', 30);
            // EMPLOYEE, SELF_EMPLOYED, BUSINESS_OWNER, FREELANCER,
            // RETIRED, PENSIONER, STUDENT, UNEMPLOYED

            $table->boolean('is_current')->default(true);

            // =====================================================
            // Employer Information
            // =====================================================
            $table->string('employer_name')->nullable();
            $table->string('employer_rfc', 13)->nullable();
            $table->string('employer_phone', 15)->nullable();
            $table->string('employer_address')->nullable();

            // =====================================================
            // Industry
            // =====================================================
            $table->string('industry_code', 10)->nullable(); // SCIAN code
            $table->string('industry_description')->nullable();
            $table->string('company_size', 20)->nullable();
            // MICRO, SMALL, MEDIUM, LARGE

            // =====================================================
            // Position Details
            // =====================================================
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('employee_number')->nullable();

            // =====================================================
            // Contract Type
            // =====================================================
            $table->string('contract_type', 20)->nullable();
            // PERMANENT, TEMPORARY, CONTRACT, SEASONAL, TRIAL

            // =====================================================
            // Employment Period
            // =====================================================
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable(); // Null if current

            // Calculated values (can be stored for query optimization)
            $table->smallInteger('years_employed')->nullable();
            $table->smallInteger('months_employed')->nullable();

            // =====================================================
            // Income Information
            // =====================================================
            $table->decimal('monthly_income', 12, 2)->nullable();
            $table->decimal('additional_income', 12, 2)->nullable();
            // Bonuses, commissions, overtime

            $table->string('payment_frequency', 20)->nullable();
            // WEEKLY, BIWEEKLY, MONTHLY

            $table->string('income_currency', 3)->default('MXN');

            // =====================================================
            // Income Verification
            // =====================================================
            $table->boolean('income_verified')->default(false);
            $table->timestamp('income_verified_at')->nullable();
            $table->uuid('income_verified_by')->nullable();
            $table->foreign('income_verified_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();

            $table->string('income_verification_method', 30)->nullable();
            // PAYSLIP, BANK_STATEMENT, TAX_RETURN, EMPLOYER_LETTER, IMSS

            $table->decimal('verified_income', 12, 2)->nullable();
            // The income amount that was verified (may differ from claimed)

            // =====================================================
            // Employment Verification
            // =====================================================
            $table->string('status', 20)->default('PENDING');
            // PENDING, VERIFIED, REJECTED, UNREACHABLE

            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->foreign('verified_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();

            $table->string('verification_method', 30)->nullable();
            // PHONE_CALL, DOCUMENT, IMSS_API, EMAIL

            $table->text('verification_notes')->nullable();

            $table->jsonb('verification_data')->nullable();
            // Stores call logs, IMSS response, etc.

            // =====================================================
            // Metadata
            // =====================================================
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();

            // =====================================================
            // Audit
            // =====================================================
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            // =====================================================
            // Indexes
            // =====================================================
            $table->index('tenant_id');
            $table->index('person_id');
            $table->index(['person_id', 'is_current']);
            $table->index('employer_rfc');
            $table->index('status');
            $table->index('employment_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_employments');
    }
};
