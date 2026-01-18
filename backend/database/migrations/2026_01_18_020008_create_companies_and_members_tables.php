<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates companies (personas morales) and company_members tables.
 *
 * Companies are legal entities that can apply for business loans.
 * Members are individuals who have roles within the company.
 *
 * Similar to how Clara/Konfío handle business accounts:
 * - One person creates a company
 * - Can invite other members with different roles/permissions
 * - Each member can view company finances based on their permissions
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================
        // COMPANIES - Legal entities (personas morales)
        // =====================================================
        Schema::create('companies', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Tenant relationship
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Creator (the account that registered the company)
            $table->uuid('created_by_account_id');
            $table->foreign('created_by_account_id')
                ->references('id')
                ->on('applicant_accounts')
                ->onDelete('restrict');

            // =====================================================
            // Company Identity
            // =====================================================
            $table->string('legal_name'); // Razón social
            $table->string('trade_name')->nullable(); // Nombre comercial
            $table->string('rfc', 13)->nullable(); // RFC persona moral (12 chars + homoclave)

            // =====================================================
            // Legal Structure
            // =====================================================
            $table->string('legal_entity_type', 20)->nullable();
            // SA, SAPI, SA_DE_CV, SAPI_DE_CV, SC, SRL, SRLCV, AC, SC_RL, SOFOM

            $table->date('incorporation_date')->nullable();
            $table->string('notary_number')->nullable();
            $table->string('commercial_folio')->nullable(); // Folio mercantil

            // =====================================================
            // Industry
            // =====================================================
            $table->string('industry_code', 10)->nullable(); // SCIAN code
            $table->string('industry_description')->nullable();
            $table->string('main_activity')->nullable();

            // =====================================================
            // Size
            // =====================================================
            $table->string('company_size', 20)->nullable();
            // MICRO, SMALL, MEDIUM, LARGE

            $table->integer('employees_count')->nullable();
            $table->decimal('annual_revenue', 15, 2)->nullable();
            $table->string('annual_revenue_currency', 3)->default('MXN');

            // =====================================================
            // Contact
            // =====================================================
            $table->string('website')->nullable();
            $table->string('main_phone', 15)->nullable();
            $table->string('main_email')->nullable();

            // =====================================================
            // Status
            // =====================================================
            $table->string('status', 30)->default('PENDING_VERIFICATION');
            // PENDING_VERIFICATION, VERIFIED, SUSPENDED, CLOSED

            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->foreign('verified_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();

            // =====================================================
            // KYB (Know Your Business)
            // =====================================================
            $table->string('kyb_status', 20)->default('PENDING');
            // PENDING, IN_PROGRESS, VERIFIED, REJECTED

            $table->timestamp('kyb_verified_at')->nullable();
            $table->jsonb('kyb_data')->nullable();
            // Aggregated verification results

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
            $table->index('rfc');
            $table->index('status');
            $table->index('kyb_status');
            $table->index('created_by_account_id');

            // Unique RFC per tenant
            $table->unique(['tenant_id', 'rfc']);
        });

        // =====================================================
        // COMPANY_ADDRESSES - Company address history
        // =====================================================
        Schema::create('company_addresses', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Tenant relationship
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Company relationship
            $table->uuid('company_id');
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');

            // Address Type
            $table->string('type', 20)->default('FISCAL');
            // FISCAL, HEADQUARTERS, BRANCH, WAREHOUSE

            // Mexican Address Format (same as person_addresses)
            $table->string('street');
            $table->string('exterior_number', 20);
            $table->string('interior_number', 20)->nullable();
            $table->string('neighborhood');
            $table->string('municipality');
            $table->string('city')->nullable();
            $table->string('state', 5);
            $table->string('postal_code', 5);
            $table->string('country', 3)->default('MX');

            $table->string('between_streets')->nullable();
            $table->text('references')->nullable();

            // Geolocation
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Validity
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_current')->default(true);

            // Verification
            $table->string('status', 20)->default('PENDING');
            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->foreign('verified_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();

            // Version history
            $table->uuid('previous_version_id')->nullable();
            $table->timestamp('replaced_at')->nullable();

            // Audit
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('tenant_id');
            $table->index('company_id');
            $table->index(['company_id', 'type', 'is_current']);
        });

        // =====================================================
        // COMPANY_MEMBERS - People who work for the company
        // =====================================================
        Schema::create('company_members', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Tenant relationship
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Company relationship
            $table->uuid('company_id');
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');

            // The person (individual)
            $table->uuid('person_id');
            $table->foreign('person_id')
                ->references('id')
                ->on('persons')
                ->onDelete('cascade');

            // Their login account
            $table->uuid('account_id');
            $table->foreign('account_id')
                ->references('id')
                ->on('applicant_accounts')
                ->onDelete('cascade');

            // =====================================================
            // Role in Company
            // =====================================================
            $table->string('role', 30);
            // OWNER, LEGAL_REP, ADMIN, FINANCE, OPERATIONS, VIEWER

            $table->string('title')->nullable();
            // Job title: "CEO", "Director General", "CFO", "Contador"

            // =====================================================
            // Legal Representative
            // =====================================================
            $table->boolean('is_legal_representative')->default(false);
            // Can legally bind the company

            $table->string('power_type')->nullable();
            // GENERAL, LIMITED, SPECIAL (tipo de poder)

            $table->date('power_granted_date')->nullable();
            $table->date('power_expiry_date')->nullable();

            // =====================================================
            // Shareholder
            // =====================================================
            $table->boolean('is_shareholder')->default(false);
            $table->decimal('ownership_percentage', 5, 2)->nullable();
            // 0.00 to 100.00

            // =====================================================
            // Permissions (granular access control)
            // =====================================================
            $table->jsonb('permissions')->nullable();
            /*
             * {
             *   "can_apply_credit": true,
             *   "can_view_applications": true,
             *   "can_sign_contracts": false,
             *   "can_view_balances": true,
             *   "can_view_statements": true,
             *   "can_download_documents": true,
             *   "can_invite_members": false,
             *   "can_manage_members": false,
             *   "can_edit_company_info": false
             * }
             */

            // =====================================================
            // Status
            // =====================================================
            $table->string('status', 20)->default('INVITED');
            // INVITED, ACTIVE, SUSPENDED, REMOVED

            $table->timestamp('invited_at')->nullable();
            $table->uuid('invited_by')->nullable();
            // Self-referential FK will be added after table creation

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('removed_at')->nullable();

            // =====================================================
            // KYC for this member
            // =====================================================
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->foreign('verified_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();

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
            $table->index('company_id');
            $table->index('person_id');
            $table->index('account_id');
            $table->index(['company_id', 'role']);
            $table->index(['company_id', 'status']);
            $table->index('is_legal_representative');

            // A person can only be member of a company once
            $table->unique(['company_id', 'person_id']);
        });

        // Add self-referential FK after table creation
        Schema::table('company_members', function (Blueprint $table) {
            $table->foreign('invited_by')
                ->references('id')
                ->on('company_members')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_members', function (Blueprint $table) {
            $table->dropForeign(['invited_by']);
        });
        Schema::dropIfExists('company_members');
        Schema::dropIfExists('company_addresses');
        Schema::dropIfExists('companies');
    }
};
