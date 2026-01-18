<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates person_references and person_bank_accounts tables.
 *
 * References are contacts that can vouch for the person.
 * Bank accounts are used for loan disbursement and payment collection.
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================
        // PERSON_REFERENCES - Personal/work references
        // =====================================================
        Schema::create('person_references', function (Blueprint $table) {
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

            // Optional: Link to specific application
            // (some references may be application-specific)
            $table->uuid('application_id')->nullable();
            // FK added after applications table exists

            // =====================================================
            // Reference Type
            // =====================================================
            $table->string('type', 20)->default('PERSONAL');
            // PERSONAL, WORK, FAMILY

            // =====================================================
            // Reference Person Data
            // =====================================================
            $table->string('first_name');
            $table->string('last_name_1');
            $table->string('last_name_2')->nullable();
            // full_name is computed in the model via accessor (SQLite doesn't support CONCAT)

            // =====================================================
            // Contact Information
            // =====================================================
            $table->string('phone', 15);
            $table->string('email')->nullable();

            // =====================================================
            // Relationship
            // =====================================================
            $table->string('relationship', 30);
            // FRIEND, NEIGHBOR, COWORKER, BOSS, SIBLING, PARENT, SPOUSE,
            // CHILD, COUSIN, UNCLE, AUNT, GRANDPARENT, IN_LAW, OTHER

            $table->smallInteger('years_known')->nullable();

            // =====================================================
            // Verification
            // =====================================================
            $table->string('status', 20)->default('PENDING');
            // PENDING, VERIFIED, UNREACHABLE, REJECTED, NO_ANSWER

            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->foreign('verified_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();

            $table->text('verification_notes')->nullable();

            // Contact attempts log
            $table->jsonb('contact_attempts')->nullable();
            /*
             * [
             *   {"date": "2026-01-15", "time": "10:30", "result": "no_answer", "by": "uuid"},
             *   {"date": "2026-01-15", "time": "14:00", "result": "verified", "notes": "...", "by": "uuid"}
             * ]
             */

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
            $table->index(['person_id', 'type']);
            $table->index('application_id');
            $table->index('status');
            $table->index('phone');
        });

        // =====================================================
        // PERSON_BANK_ACCOUNTS - Bank accounts for disbursement
        // =====================================================
        Schema::create('person_bank_accounts', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Tenant relationship
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Owner (person OR company - polymorphic)
            $table->string('owner_type', 30); // persons, companies
            $table->uuid('owner_id');
            // Note: FK not created here, handled at application level

            // =====================================================
            // Bank Information
            // =====================================================
            $table->string('bank_name');
            $table->string('bank_code', 10)->nullable(); // SPEI bank code

            // =====================================================
            // Account Details
            // =====================================================
            $table->string('clabe', 18); // CLABE interbancaria (18 digits)
            $table->string('account_number_last4', 4)->nullable(); // For display only
            $table->string('card_number_last4', 4)->nullable(); // If linked to card

            // =====================================================
            // Account Type
            // =====================================================
            $table->string('account_type', 20)->default('CHECKING');
            // CHECKING, SAVINGS, PAYROLL, CREDIT

            $table->string('currency', 3)->default('MXN');

            // =====================================================
            // Account Holder
            // =====================================================
            $table->string('holder_name'); // Name as it appears on account
            $table->string('holder_rfc', 13)->nullable();

            // =====================================================
            // Usage Flags
            // =====================================================
            $table->boolean('is_primary')->default(false);
            // Primary account for disbursement

            $table->boolean('is_for_disbursement')->default(true);
            // Can receive loan disbursement

            $table->boolean('is_for_collection')->default(false);
            // Can be used for payment collection (domiciliación)

            // =====================================================
            // Verification
            // =====================================================
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->foreign('verified_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();

            $table->string('verification_method', 30)->nullable();
            // MICRO_DEPOSIT, BANK_STATEMENT, SPEI_API, MANUAL

            $table->jsonb('verification_data')->nullable();

            // =====================================================
            // Status
            // =====================================================
            $table->string('status', 20)->default('ACTIVE');
            // ACTIVE, INACTIVE, CLOSED, FROZEN

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
            $table->index(['owner_type', 'owner_id']);
            $table->index('clabe');
            $table->index('bank_code');
            $table->index('is_verified');
            $table->index('status');

            // Unique CLABE per tenant (same CLABE can exist in different tenants)
            $table->unique(['tenant_id', 'clabe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_bank_accounts');
        Schema::dropIfExists('person_references');
    }
};
