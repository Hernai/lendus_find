<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the new documents table with polymorphic relationships.
 *
 * Documents can be attached to various entities:
 * - person_identifications (INE front/back photos)
 * - person_addresses (proof of address)
 * - person_employments (payslips, employment letters)
 * - companies (incorporation docs, powers of attorney)
 * - applications (additional documents requested)
 *
 * Note: This creates a NEW table. The old 'documents' table migration
 * will need to be handled separately for data migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Create the new documents table with polymorphic support
        // Named 'documents_v2' to avoid conflict with existing 'documents' table
        // A separate migration will handle data migration and table swap

        Schema::create('documents_v2', function (Blueprint $table) {
            // Primary key
            $table->uuid('id')->primary();

            // Tenant relationship
            $table->uuid('tenant_id');
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            // =====================================================
            // Polymorphic Relationship
            // =====================================================
            $table->string('documentable_type', 50);
            // Values: person_identifications, person_addresses, person_employments,
            //         companies, company_addresses, applications

            $table->uuid('documentable_id');

            // =====================================================
            // Document Classification
            // =====================================================
            $table->string('type', 30);
            // Types by category:
            //
            // IDENTITY:
            //   INE_FRONT, INE_BACK, PASSPORT, CURP_DOC, RFC_CONSTANCIA,
            //   DRIVER_LICENSE_FRONT, DRIVER_LICENSE_BACK
            //
            // ADDRESS:
            //   PROOF_OF_ADDRESS, UTILITY_BILL, BANK_STATEMENT_ADDRESS,
            //   LEASE_AGREEMENT, PROPERTY_DEED
            //
            // INCOME:
            //   PAYSLIP, BANK_STATEMENT, TAX_RETURN, IMSS_STATEMENT,
            //   EMPLOYMENT_LETTER, INCOME_AFFIDAVIT
            //
            // COMPANY:
            //   CONSTITUTIVE_ACT, POWER_OF_ATTORNEY, TAX_ID_COMPANY,
            //   FISCAL_SITUATION, LEGAL_REP_ID, SHAREHOLDER_STRUCTURE
            //
            // OTHER:
            //   SELFIE, SIGNATURE, OTHER

            $table->string('category', 20);
            // IDENTITY, ADDRESS, INCOME, COMPANY, VERIFICATION, OTHER

            // =====================================================
            // File Information
            // =====================================================
            $table->string('file_name'); // Original filename
            $table->string('file_path'); // Storage path
            $table->string('storage_disk', 20)->default('local');
            // local, s3, gcs

            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size'); // Bytes

            $table->string('checksum', 64)->nullable();
            // MD5 or SHA-256 for integrity verification

            // =====================================================
            // Status
            // =====================================================
            $table->string('status', 20)->default('PENDING');
            // PENDING, APPROVED, REJECTED, EXPIRED, SUPERSEDED

            $table->text('rejection_reason')->nullable();

            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->foreign('reviewed_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();

            // =====================================================
            // OCR / Data Extraction
            // =====================================================
            $table->boolean('ocr_processed')->default(false);
            $table->timestamp('ocr_processed_at')->nullable();

            $table->jsonb('ocr_data')->nullable();
            /*
             * For INE front:
             * {
             *   "nombre": "JUAN PEREZ GARCIA",
             *   "curp": "PEGJ850101HDFRRS09",
             *   "clave_elector": "...",
             *   "fecha_nacimiento": "1985-01-01",
             *   "raw_text": "..."
             * }
             */

            $table->decimal('ocr_confidence', 5, 2)->nullable();
            // 0.00 to 100.00

            // =====================================================
            // Security
            // =====================================================
            $table->boolean('is_sensitive')->default(false);
            // Marks documents with PII for special handling

            $table->boolean('is_encrypted')->default(false);
            // If true, file is encrypted at rest

            // =====================================================
            // Version History
            // =====================================================
            $table->uuid('previous_version_id')->nullable();
            // Self-referential FK added after table creation

            $table->smallInteger('version_number')->default(1);

            $table->timestamp('replaced_at')->nullable();
            $table->string('replacement_reason', 30)->nullable();
            // REJECTED, EXPIRED, UPDATED, BETTER_QUALITY

            // =====================================================
            // Expiration
            // =====================================================
            $table->date('valid_until')->nullable();
            // For documents that expire (INE, passport)

            $table->boolean('expiration_notified')->default(false);
            // Track if we've notified about upcoming expiration

            // =====================================================
            // Metadata
            // =====================================================
            $table->jsonb('metadata')->nullable();
            /*
             * {
             *   "original_dimensions": {"width": 1920, "height": 1080},
             *   "upload_source": "mobile_app",
             *   "device_info": "...",
             *   "ip_address": "..."
             * }
             */

            $table->text('notes')->nullable();

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
            $table->index(['documentable_type', 'documentable_id']);
            $table->index(['tenant_id', 'documentable_type', 'documentable_id']);
            $table->index('type');
            $table->index('category');
            $table->index('status');
            $table->index('valid_until');
            $table->index('checksum');

            // For finding current version of a document type
            $table->index(['documentable_type', 'documentable_id', 'type', 'status']);
        });

        // Add self-referential FK for version history
        Schema::table('documents_v2', function (Blueprint $table) {
            $table->foreign('previous_version_id')
                ->references('id')
                ->on('documents_v2')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents_v2', function (Blueprint $table) {
            $table->dropForeign(['previous_version_id']);
        });

        Schema::dropIfExists('documents_v2');
    }
};
