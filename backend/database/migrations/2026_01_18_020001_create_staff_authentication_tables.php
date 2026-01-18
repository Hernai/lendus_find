<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates staff authentication and profile tables.
 *
 * Separates staff users (admin, analyst, supervisor) from applicants.
 * Staff use email/password authentication only.
 *
 * Tables created:
 * - staff_accounts: Authentication and role management
 * - staff_profiles: Personal information and preferences
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================
        // STAFF_ACCOUNTS - Authentication for staff users
        // =====================================================
        Schema::create('staff_accounts', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Tenant relationship
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // Authentication
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();

            // Role (enum stored as string for flexibility)
            $table->string('role', 50)->default('ANALYST');
            // Valid roles: ANALYST, SUPERVISOR, ADMIN, SUPER_ADMIN

            // Status
            $table->boolean('is_active')->default(true);

            // Login tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            // Audit
            $table->timestamps();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            // Indexes
            $table->index('tenant_id');
            $table->index('role');
            $table->index('is_active');
            $table->index(['tenant_id', 'is_active']);
        });

        // =====================================================
        // STAFF_PROFILES - Staff personal information
        // =====================================================
        Schema::create('staff_profiles', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Account relationship (1:1)
            $table->uuid('account_id')->unique();
            $table->foreign('account_id')
                ->references('id')
                ->on('staff_accounts')
                ->onDelete('cascade');

            // Personal information
            $table->string('first_name');
            $table->string('last_name');
            $table->string('last_name_2')->nullable(); // Apellido materno

            // Contact (separate from auth email)
            $table->string('phone', 15)->nullable();

            // Profile
            $table->string('avatar_url')->nullable();
            $table->string('title')->nullable(); // Job title

            // Preferences (JSONB for flexibility)
            $table->jsonb('preferences')->nullable();
            // Example: {"theme": "dark", "notifications": {"email": true, "push": false}}

            // Audit
            $table->timestamps();
        });

        // =====================================================
        // Add self-referential FKs after table creation
        // =====================================================
        Schema::table('staff_accounts', function (Blueprint $table) {
            $table->foreign('created_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();
            $table->foreign('updated_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Drop in reverse order due to FK constraints
        Schema::table('staff_accounts', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });

        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('staff_accounts');
    }
};
