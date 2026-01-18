<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Restructures tables to follow proper field ordering conventions and removes duplicate columns.
 *
 * Convention for field order:
 * 1. id (PK)
 * 2. tenant_id (FK for multi-tenancy)
 * 3. Parent FKs (application_id, applicant_id, etc.)
 * 4. Business/identifying fields
 * 5. Status/state fields
 * 6. Metadata/JSON fields
 * 7. Audit fields (created_at, updated_at, deleted_at, created_by, updated_by)
 *
 * Changes:
 * - documents: Removes duplicate columns (size, original_name, storage_path, rejection_comment)
 *              Keeps: file_size, file_name, file_path, rejection_reason
 * - references: Reorders to put tenant_id after id
 * - users: Reorders to put tenant_id after id
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================
        // 1. DOCUMENTS TABLE - Remove duplicate columns
        // =====================================================
        // The table has duplicates:
        // - file_size (original) vs size (added later) -> keep file_size
        // - file_name (original) vs original_name (added later) -> keep file_name
        // - file_path (original) vs storage_path (added later) -> keep file_path
        // - rejection_reason (original) vs rejection_comment (added later) -> keep rejection_reason

        Schema::table('documents', function (Blueprint $table) {
            // First, migrate any data from duplicate columns to the canonical ones
        });

        // Migrate data from duplicate columns
        DB::statement('UPDATE documents SET file_size = COALESCE(file_size, size) WHERE file_size IS NULL AND size IS NOT NULL');
        DB::statement('UPDATE documents SET file_name = COALESCE(file_name, original_name) WHERE original_name IS NOT NULL AND file_name IS NULL');
        DB::statement('UPDATE documents SET file_path = COALESCE(file_path, storage_path) WHERE storage_path IS NOT NULL AND file_path IS NULL');
        DB::statement('UPDATE documents SET rejection_reason = COALESCE(rejection_reason, rejection_comment) WHERE rejection_comment IS NOT NULL AND rejection_reason IS NULL');

        // Now drop the duplicate columns
        Schema::table('documents', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('documents', 'size')) {
                $columns[] = 'size';
            }
            if (Schema::hasColumn('documents', 'original_name')) {
                $columns[] = 'original_name';
            }
            if (Schema::hasColumn('documents', 'storage_path')) {
                $columns[] = 'storage_path';
            }
            if (Schema::hasColumn('documents', 'rejection_comment')) {
                $columns[] = 'rejection_comment';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        // =====================================================
        // 2. REFERENCES TABLE - Reorder to put tenant_id after id
        // =====================================================
        // PostgreSQL doesn't support column reordering, so we need to recreate the table
        $this->reorderReferencesTable();

        // =====================================================
        // 3. USERS TABLE - Skip reordering
        // =====================================================
        // Note: Users table has too many FK dependencies to safely reorder.
        // The tenant_id position difference is cosmetic and doesn't affect functionality.
        // Keeping current order to avoid breaking dependent constraints.
    }

    private function reorderReferencesTable(): void
    {
        // Create new table with correct column order
        DB::statement('
            CREATE TABLE references_new (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                applicant_id UUID NOT NULL,
                application_id UUID,

                -- Reference Info
                first_name VARCHAR(255) NOT NULL,
                last_name_1 VARCHAR(255) NOT NULL,
                last_name_2 VARCHAR(255),
                full_name VARCHAR(255) NOT NULL,
                phone VARCHAR(15) NOT NULL,
                email VARCHAR(255),

                -- Relationship
                relationship VARCHAR(255) NOT NULL,
                type VARCHAR(255) DEFAULT \'PERSONAL\',

                -- Verification
                is_verified BOOLEAN DEFAULT FALSE,
                verified_at TIMESTAMP,
                verified_by UUID,
                verification_notes TEXT,
                verification_result VARCHAR(255),

                -- Contact Attempts
                contact_attempts JSON,

                -- Timestamps
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP,

                -- Audit
                created_by UUID,
                updated_by UUID,
                deleted_by UUID
            )
        ');

        // Copy data
        DB::statement('
            INSERT INTO references_new
            SELECT
                id, tenant_id, applicant_id, application_id,
                first_name, last_name_1, last_name_2, full_name, phone, email,
                relationship, type,
                is_verified, verified_at, verified_by, verification_notes, verification_result,
                contact_attempts,
                created_at, updated_at, deleted_at,
                created_by, updated_by, deleted_by
            FROM "references"
        ');

        // Drop old table and rename new one
        Schema::dropIfExists('references');
        DB::statement('ALTER TABLE references_new RENAME TO "references"');

        // Re-add foreign keys and indexes
        Schema::table('references', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('applicant_id')->references('id')->on('applicants')->onDelete('cascade');
            $table->foreign('application_id')->references('id')->on('applications')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();

            $table->index('tenant_id');
            $table->index('applicant_id');
            $table->index('application_id');
            $table->index(['application_id', 'is_verified'], 'idx_references_verified');
            $table->index('deleted_at', 'idx_references_deleted');
        });
    }

    private function reorderUsersTable(): void
    {
        // Users table is more complex due to sessions FK
        // We'll create a new table, migrate data, then swap

        // First, drop dependent tables temporarily
        $sessionsData = DB::table('sessions')->get();
        Schema::dropIfExists('sessions');

        // Create new users table with correct order
        DB::statement('
            CREATE TABLE users_new (
                id UUID PRIMARY KEY,
                tenant_id UUID,

                -- Identity
                name VARCHAR(255) NOT NULL,
                first_name VARCHAR(255),
                last_name VARCHAR(255),
                last_name_2 VARCHAR(255),
                email VARCHAR(255) UNIQUE,
                phone VARCHAR(15) UNIQUE,

                -- Authentication
                email_verified_at TIMESTAMP,
                phone_verified_at TIMESTAMP,
                password VARCHAR(255),
                pin_hash VARCHAR(255),
                pin_set_at TIMESTAMP,
                pin_attempts SMALLINT DEFAULT 0,
                pin_locked_until TIMESTAMP,
                remember_token VARCHAR(100),

                -- Role and Status
                type VARCHAR(255) DEFAULT \'APPLICANT\',
                role VARCHAR(255),
                is_active BOOLEAN DEFAULT TRUE,

                -- Profile
                avatar_url VARCHAR(255),

                -- Login tracking
                last_login_at TIMESTAMP,
                last_login_ip VARCHAR(45),

                -- Audit
                created_at TIMESTAMP,
                updated_at TIMESTAMP,
                created_by UUID,
                updated_by UUID
            )
        ');

        // Copy all data with all columns
        DB::statement('
            INSERT INTO users_new (
                id, tenant_id,
                name, first_name, last_name, last_name_2, email, phone,
                email_verified_at, phone_verified_at, password,
                pin_hash, pin_set_at, pin_attempts, pin_locked_until, remember_token,
                type, role, is_active, avatar_url,
                last_login_at, last_login_ip,
                created_at, updated_at, created_by, updated_by
            )
            SELECT
                id, tenant_id,
                name, first_name, last_name, last_name_2, email, phone,
                email_verified_at, phone_verified_at, password,
                pin_hash, pin_set_at, pin_attempts, pin_locked_until, remember_token,
                type, role, is_active, avatar_url,
                last_login_at, last_login_ip,
                created_at, updated_at, created_by, updated_by
            FROM users
        ');

        // Drop old table and rename
        Schema::dropIfExists('users');
        DB::statement('ALTER TABLE users_new RENAME TO users');

        // Re-add indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index('tenant_id');
            $table->index('type');
            $table->index('phone');
        });

        // Recreate sessions table
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Restore sessions data
        foreach ($sessionsData as $session) {
            DB::table('sessions')->insert((array) $session);
        }
    }

    public function down(): void
    {
        // This migration is not easily reversible
        // The duplicate columns in documents were redundant
        // The field order changes don't affect functionality

        // Re-add the duplicate columns to documents if needed
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'size')) {
                $table->integer('size')->nullable();
            }
            if (!Schema::hasColumn('documents', 'original_name')) {
                $table->string('original_name')->nullable();
            }
            if (!Schema::hasColumn('documents', 'storage_path')) {
                $table->string('storage_path')->nullable();
            }
            if (!Schema::hasColumn('documents', 'rejection_comment')) {
                $table->text('rejection_comment')->nullable();
            }
        });

        // Copy data back to duplicate columns
        DB::statement('UPDATE documents SET size = file_size');
        DB::statement('UPDATE documents SET original_name = file_name');
        DB::statement('UPDATE documents SET storage_path = file_path');
        DB::statement('UPDATE documents SET rejection_comment = rejection_reason');
    }
};
