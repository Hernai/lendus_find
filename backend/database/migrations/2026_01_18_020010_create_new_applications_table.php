<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the new applications table that references the normalized structure.
 *
 * Applications now reference:
 * - person_id (for individual applicants) OR
 * - company_id (for business applicants)
 *
 * The snapshot_data stores references to specific versions of:
 * - Identification used
 * - Address used
 * - Employment used
 * - Bank account used
 *
 * This ensures that if a person updates their data after applying,
 * the application retains the original data used at application time.
 *
 * Note: This creates 'applications_v2'. Data migration handled separately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications_v2', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Tenant relationship
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Product relationship
            $table->uuid('product_id');
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('restrict');

            // =====================================================
            // Applicant (Person OR Company)
            // =====================================================
            $table->string('applicant_type', 20);
            // INDIVIDUAL, COMPANY

            // For individual applicants
            $table->uuid('person_id')->nullable();
            $table->foreign('person_id')
                ->references('id')
                ->on('persons')
                ->onDelete('restrict');

            // For company applicants
            $table->uuid('company_id')->nullable();
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('restrict');

            // The account that submitted the application
            $table->uuid('submitted_by_account_id')->nullable();
            $table->foreign('submitted_by_account_id')
                ->references('id')
                ->on('applicant_accounts')
                ->onDelete('restrict');

            // For company applications: which member submitted
            $table->uuid('submitted_by_member_id')->nullable();
            $table->foreign('submitted_by_member_id')
                ->references('id')
                ->on('company_members')
                ->nullOnDelete();

            // =====================================================
            // Snapshot of Data at Application Time
            // =====================================================
            // References to specific record versions used for this application
            $table->jsonb('snapshot_references')->nullable();
            /*
             * {
             *   "identification_id": "uuid",      // person_identifications.id
             *   "address_id": "uuid",             // person_addresses.id
             *   "employment_id": "uuid",          // person_employments.id
             *   "bank_account_id": "uuid",        // person_bank_accounts.id
             *   "references": ["uuid1", "uuid2"]  // person_references.id[]
             * }
             */

            // Full data snapshot (denormalized for historical record)
            $table->jsonb('snapshot_data')->nullable();
            /*
             * {
             *   "personal": {...},
             *   "identification": {...},
             *   "address": {...},
             *   "employment": {...},
             *   "bank_account": {...}
             * }
             */

            // =====================================================
            // Loan Details
            // =====================================================
            $table->decimal('requested_amount', 15, 2);
            $table->smallInteger('requested_term_months');
            $table->string('purpose', 100)->nullable();
            $table->text('purpose_description')->nullable();

            // =====================================================
            // Calculated Values (from simulator/product rules)
            // =====================================================
            $table->decimal('interest_rate', 8, 4)->nullable();
            // Annual interest rate as decimal (0.24 = 24%)

            $table->decimal('monthly_payment', 15, 2)->nullable();
            $table->decimal('total_interest', 15, 2)->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('cat', 8, 4)->nullable();
            // Costo Anual Total (Mexican regulatory requirement)

            // =====================================================
            // Approved Values (may differ from requested)
            // =====================================================
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->smallInteger('approved_term_months')->nullable();
            $table->decimal('approved_interest_rate', 8, 4)->nullable();
            $table->decimal('approved_monthly_payment', 15, 2)->nullable();

            // =====================================================
            // Status Workflow
            // =====================================================
            $table->string('status', 30)->default('DRAFT');
            // DRAFT, SUBMITTED, IN_REVIEW, DOCS_PENDING, ANALYST_REVIEW,
            // SUPERVISOR_REVIEW, APPROVED, REJECTED, CANCELLED, SYNCED

            $table->timestamp('status_changed_at')->nullable();
            $table->uuid('status_changed_by')->nullable();
            // Can be staff_accounts.id or applicant_accounts.id

            $table->string('status_changed_by_type', 30)->nullable();
            // staff_accounts, applicant_accounts

            // =====================================================
            // Submission
            // =====================================================
            $table->timestamp('submitted_at')->nullable();
            $table->string('submission_ip', 45)->nullable();
            $table->string('submission_device')->nullable();

            // =====================================================
            // Assignment (for analyst workflow)
            // =====================================================
            $table->uuid('assigned_to')->nullable();
            $table->foreign('assigned_to')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();

            $table->timestamp('assigned_at')->nullable();

            $table->uuid('assigned_by')->nullable();
            $table->foreign('assigned_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();

            // =====================================================
            // Decision
            // =====================================================
            $table->string('decision', 20)->nullable();
            // APPROVED, REJECTED, COUNTER_OFFER

            $table->timestamp('decision_at')->nullable();

            $table->uuid('decision_by')->nullable();
            $table->foreign('decision_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();

            $table->text('decision_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            // =====================================================
            // Counter Offer (if applicable)
            // =====================================================
            $table->jsonb('counter_offer')->nullable();
            /*
             * {
             *   "amount": 50000,
             *   "term_months": 12,
             *   "interest_rate": 0.28,
             *   "monthly_payment": 4500,
             *   "reason": "...",
             *   "expires_at": "2026-01-30"
             * }
             */

            $table->boolean('counter_offer_accepted')->nullable();
            $table->timestamp('counter_offer_responded_at')->nullable();

            // =====================================================
            // Verification Checklist
            // =====================================================
            $table->jsonb('verification_checklist')->nullable();
            /*
             * {
             *   "identity_verified": true,
             *   "address_verified": true,
             *   "employment_verified": true,
             *   "references_verified": 2,
             *   "bank_verified": true,
             *   "documents_approved": 5,
             *   "documents_pending": 0
             * }
             */

            // =====================================================
            // Risk Assessment
            // =====================================================
            $table->string('risk_level', 20)->nullable();
            // LOW, MEDIUM, HIGH, VERY_HIGH

            $table->jsonb('risk_data')->nullable();
            // Bureau scores, internal scoring, etc.

            // =====================================================
            // Webhook/Integration
            // =====================================================
            $table->timestamp('synced_at')->nullable();
            $table->string('external_id')->nullable();
            $table->string('external_system')->nullable();
            // SAP, LENDUS, CORE_BANKING, etc.

            $table->jsonb('sync_data')->nullable();
            // Response from external system

            // =====================================================
            // Expiration
            // =====================================================
            $table->timestamp('expires_at')->nullable();
            // Draft applications expire after X days

            $table->boolean('expiration_notified')->default(false);

            // =====================================================
            // Metadata
            // =====================================================
            $table->text('notes')->nullable();
            // Internal notes visible to staff

            $table->jsonb('metadata')->nullable();

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
            $table->index('product_id');
            $table->index('person_id');
            $table->index('company_id');
            $table->index('status');
            $table->index('decision');
            $table->index('assigned_to');
            $table->index('submitted_at');
            $table->index('created_at');

            // Common queries
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_to', 'status']);
            $table->index(['tenant_id', 'person_id', 'status']);
            $table->index(['tenant_id', 'company_id', 'status']);
        });

        // =====================================================
        // APPLICATION_STATUS_HISTORY - Track status changes
        // =====================================================
        Schema::create('application_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('application_id');
            $table->foreign('application_id')
                ->references('id')
                ->on('applications_v2')
                ->onDelete('cascade');

            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);

            $table->uuid('changed_by')->nullable();
            $table->string('changed_by_type', 30)->nullable();
            // staff_accounts, applicant_accounts, system

            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamp('created_at');

            $table->index('application_id');
            $table->index('created_at');
        });

        // =====================================================
        // Add FK for references to applications
        // =====================================================
        Schema::table('person_references', function (Blueprint $table) {
            $table->foreign('application_id')
                ->references('id')
                ->on('applications_v2')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('person_references', function (Blueprint $table) {
            $table->dropForeign(['application_id']);
        });

        Schema::dropIfExists('application_status_history');
        Schema::dropIfExists('applications_v2');
    }
};
