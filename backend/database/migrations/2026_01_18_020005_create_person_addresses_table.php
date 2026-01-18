<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates person_addresses table with version history.
 *
 * Stores addresses with full history tracking. When a person moves,
 * a new record is created with valid_from date, and the previous
 * address gets valid_until and is_current=false.
 *
 * Supports multiple address types (home, work, fiscal) simultaneously.
 * Each type can have its own history.
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================
        // PERSON_ADDRESSES - Addresses with history
        // =====================================================
        Schema::create('person_addresses', function (Blueprint $table) {
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
            // Address Type
            // =====================================================
            $table->string('type', 20)->default('HOME');
            // Types: HOME, WORK, FISCAL, BILLING, CORRESPONDENCE, DELIVERY

            // =====================================================
            // Mexican Address Format
            // =====================================================
            $table->string('street');
            $table->string('exterior_number', 20);
            $table->string('interior_number', 20)->nullable();
            $table->string('neighborhood'); // Colonia
            $table->string('municipality'); // Delegación/Municipio
            $table->string('city')->nullable();
            $table->string('state', 5); // CDMX, JAL, NL, etc.
            $table->string('postal_code', 5);
            $table->string('country', 3)->default('MX');

            // =====================================================
            // Additional Details
            // =====================================================
            $table->string('between_streets')->nullable();
            $table->text('references')->nullable(); // "Casa azul con portón negro"

            // =====================================================
            // Geolocation (for delivery, verification)
            // =====================================================
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('geocode_accuracy', 20)->nullable();
            // ROOFTOP, RANGE_INTERPOLATED, GEOMETRIC_CENTER, APPROXIMATE

            // =====================================================
            // Validity Period (for history tracking)
            // =====================================================
            $table->date('valid_from')->nullable();
            // When person started living/using this address

            $table->date('valid_until')->nullable();
            // When person stopped using this address (null if current)

            $table->boolean('is_current')->default(true);

            // =====================================================
            // Residence Duration (for credit analysis)
            // =====================================================
            $table->smallInteger('years_at_address')->nullable();
            $table->smallInteger('months_at_address')->nullable();

            // =====================================================
            // Housing Details (for credit analysis)
            // =====================================================
            $table->string('housing_type', 20)->nullable();
            // OWNED, RENTED, FAMILY, MORTGAGED, EMPLOYER

            $table->decimal('monthly_rent', 12, 2)->nullable();
            // Only if housing_type = RENTED

            // =====================================================
            // Status and Verification
            // =====================================================
            $table->string('status', 20)->default('PENDING');
            // PENDING, VERIFIED, REJECTED

            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->foreign('verified_by')
                ->references('id')
                ->on('staff_accounts')
                ->nullOnDelete();

            $table->string('verification_method', 30)->nullable();
            // DOCUMENT, GEOLOCATION, VISIT, INE_MATCH, UTILITY_BILL

            $table->jsonb('verification_data')->nullable();

            // =====================================================
            // Version History
            // =====================================================
            $table->uuid('previous_version_id')->nullable();

            $table->timestamp('replaced_at')->nullable();

            $table->string('replacement_reason', 20)->nullable();
            // MOVED, CORRECTED, UPDATED

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
            $table->index('postal_code');
            $table->index('status');
            $table->index(['state', 'municipality']);
        });

        // Add self-referential FK for version history
        Schema::table('person_addresses', function (Blueprint $table) {
            $table->foreign('previous_version_id')
                ->references('id')
                ->on('person_addresses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('person_addresses', function (Blueprint $table) {
            $table->dropForeign(['previous_version_id']);
        });

        Schema::dropIfExists('person_addresses');
    }
};
