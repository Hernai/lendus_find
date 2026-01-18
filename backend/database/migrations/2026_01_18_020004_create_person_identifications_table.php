<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates person_identifications table with version history.
 *
 * Stores official identification documents (CURP, RFC, INE, passport, etc.)
 * with full history tracking. When an INE expires and is renewed,
 * a new record is created and linked to the previous version.
 *
 * This allows:
 * - Tracking document history (all INEs a person has had)
 * - Maintaining audit trail for compliance
 * - Knowing which document was valid at any point in time
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================
        // PERSON_IDENTIFICATIONS - Official ID documents
        // =====================================================
        Schema::create('person_identifications', function (Blueprint $table) {
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
            // Document Type
            // =====================================================
            $table->string('type', 30);
            // Types: CURP, RFC, INE, PASSPORT, FM2, FM3, VISA,
            //        DRIVER_LICENSE, PROFESSIONAL_ID, MILITARY_ID

            // =====================================================
            // Primary Identifier Value
            // =====================================================
            // The main value: CURP string, RFC string, INE clave de elector, etc.
            $table->string('identifier_value')->nullable();

            // =====================================================
            // Document-Specific Data (varies by type)
            // =====================================================
            $table->jsonb('document_data')->nullable();
            /*
             * For INE:
             * {
             *   "cic": "123456789012",           // 12 dígitos
             *   "clave_elector": "ABC...",       // 18 chars
             *   "ocr": "1234567890123",          // 13 dígitos
             *   "folio": "1234567890",           // 10 dígitos
             *   "seccion": "1234",               // Sección electoral
             *   "emision": 2020,                 // Año de emisión
             *   "vigencia": 2030                 // Año de vigencia
             * }
             *
             * For PASSPORT:
             * {
             *   "passport_number": "G12345678",
             *   "issuing_country": "MX",
             *   "issuing_authority": "SRE"
             * }
             *
             * For RFC:
             * {
             *   "homoclave": "AB1",
             *   "constancia_url": "...",
             *   "regimen": "PERSONA_FISICA"
             * }
             */

            // =====================================================
            // Validity Period
            // =====================================================
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();

            // =====================================================
            // Current Version Flag
            // =====================================================
            // Only one identification of each type should be current
            $table->boolean('is_current')->default(true);

            // =====================================================
            // Status
            // =====================================================
            $table->string('status', 20)->default('PENDING');
            // PENDING, VERIFIED, EXPIRED, REVOKED, SUPERSEDED

            // =====================================================
            // Verification
            // =====================================================
            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->foreign('verified_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();

            $table->string('verification_method', 30)->nullable();
            // MANUAL, OCR, INE_API, RENAPO_API, SAT_API

            $table->jsonb('verification_data')->nullable();
            // API response data, OCR extracted data, etc.

            $table->decimal('verification_confidence', 5, 2)->nullable();
            // 0.00 to 100.00 - confidence score from OCR/API

            // =====================================================
            // Version History (linked list)
            // =====================================================
            $table->uuid('previous_version_id')->nullable();
            // Self-referential FK added after table creation

            $table->timestamp('replaced_at')->nullable();
            // When this version was replaced by a newer one

            $table->string('replacement_reason', 30)->nullable();
            // EXPIRED, RENEWED, CORRECTED, LOST, STOLEN, SUPERSEDED

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
            $table->index(['person_id', 'type', 'is_current']);
            $table->index(['type', 'identifier_value']);
            $table->index('status');
            $table->index('expires_at');
        });

        // Add self-referential FK for version history
        Schema::table('person_identifications', function (Blueprint $table) {
            $table->foreign('previous_version_id')
                ->references('id')
                ->on('person_identifications')
                ->nullOnDelete();
        });

        // =====================================================
        // Partial unique index: only one current per type per person
        // PostgreSQL specific - ensures data integrity
        // =====================================================
        // Note: This is handled at application level for portability
        // If using PostgreSQL exclusively, uncomment:
        // DB::statement('CREATE UNIQUE INDEX idx_person_id_type_current
        //     ON person_identifications (person_id, type)
        //     WHERE is_current = true AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('person_identifications', function (Blueprint $table) {
            $table->dropForeign(['previous_version_id']);
        });

        Schema::dropIfExists('person_identifications');
    }
};
