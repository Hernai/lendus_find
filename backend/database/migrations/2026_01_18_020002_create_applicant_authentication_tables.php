<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates applicant authentication tables with multi-identity support.
 *
 * Allows applicants to login with multiple identifiers (phone, email, WhatsApp)
 * linked to a single account. Supports OTP and PIN authentication.
 *
 * Tables created:
 * - applicant_accounts: Main account with PIN authentication
 * - applicant_identities: Multiple login identifiers per account
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================
        // APPLICANT_ACCOUNTS - Main account for applicants
        // =====================================================
        Schema::create('applicant_accounts', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Tenant relationship
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Link to person (will be added after persons table exists)
            // $table->uuid('person_id')->nullable();

            // PIN Authentication (for quick login after initial OTP)
            $table->string('pin_hash')->nullable();
            $table->timestamp('pin_set_at')->nullable();
            $table->smallInteger('pin_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            // Onboarding progress
            $table->smallInteger('onboarding_step')->default(0);
            $table->boolean('onboarding_completed')->default(false);
            $table->timestamp('onboarding_completed_at')->nullable();

            // Login tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('last_login_method', 20)->nullable();
            // Valid methods: PHONE_OTP, EMAIL_OTP, WHATSAPP_OTP, PIN

            // Device info for security
            $table->jsonb('known_devices')->nullable();
            // Example: [{"device_id": "abc", "last_seen": "2026-01-01", "user_agent": "..."}]

            // Preferences
            $table->jsonb('preferences')->nullable();
            // Example: {"preferred_contact": "whatsapp", "language": "es"}

            // Audit
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('tenant_id');
            $table->index('is_active');
            $table->index(['tenant_id', 'is_active']);
        });

        // =====================================================
        // APPLICANT_IDENTITIES - Multiple login identifiers
        // =====================================================
        Schema::create('applicant_identities', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Account relationship
            $table->uuid('account_id');
            $table->foreign('account_id')
                ->references('id')
                ->on('applicant_accounts')
                ->onDelete('cascade');

            // Identity type
            $table->string('type', 20);
            // Valid types: PHONE, EMAIL, WHATSAPP

            // The actual identifier (phone number or email)
            $table->string('identifier');

            // Verification
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_code', 10)->nullable();
            $table->timestamp('verification_code_expires_at')->nullable();
            $table->smallInteger('verification_attempts')->default(0);

            // Status
            $table->boolean('is_primary')->default(false);
            $table->timestamp('last_used_at')->nullable();

            // Audit
            $table->timestamps();

            // Unique constraint: one identifier per type per account
            $table->unique(['account_id', 'type']);

            // Unique constraint: one identifier globally (no duplicate phones/emails)
            $table->unique(['type', 'identifier']);

            // Indexes
            $table->index('account_id');
            $table->index(['type', 'identifier']);
        });

        // =====================================================
        // OTP_REQUESTS - Track OTP requests for rate limiting
        // =====================================================
        Schema::create('otp_requests', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Can be linked to identity or standalone (for registration)
            $table->uuid('identity_id')->nullable();
            $table->foreign('identity_id')
                ->references('id')
                ->on('applicant_identities')
                ->onDelete('cascade');

            // Target (for when identity doesn't exist yet)
            $table->string('target_type', 20)->nullable(); // PHONE, EMAIL, WHATSAPP
            $table->string('target_value')->nullable();

            // OTP details
            $table->string('code', 10);
            $table->string('channel', 20); // SMS, EMAIL, WHATSAPP
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->smallInteger('attempts')->default(0);

            // Security
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Audit
            $table->timestamps();

            // Indexes for rate limiting queries
            $table->index(['target_type', 'target_value', 'created_at']);
            $table->index(['identity_id', 'created_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_requests');
        Schema::dropIfExists('applicant_identities');
        Schema::dropIfExists('applicant_accounts');
    }
};
