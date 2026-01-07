<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('applicant_id');

            // Address Type
            $table->enum('type', ['HOME', 'WORK', 'FISCAL', 'CORRESPONDENCE'])->default('HOME');
            $table->boolean('is_primary')->default(false);

            // Street Address
            $table->string('street', 200);
            $table->string('ext_number', 20);
            $table->string('int_number', 20)->nullable();
            $table->string('neighborhood', 100); // Colonia
            $table->string('municipality', 100)->nullable(); // Municipio/Delegacion

            // Location
            $table->string('postal_code', 5);
            $table->string('city', 100);
            $table->string('state', 50);
            $table->string('country', 50)->default('MEXICO');

            // Additional Info
            $table->string('between_streets', 200)->nullable(); // Entre calles
            $table->text('references')->nullable(); // Referencias para ubicar

            // Housing Info (important for credit scoring)
            $table->enum('housing_type', [
                'PROPIA_PAGADA',
                'PROPIA_HIPOTECA',
                'RENTADA',
                'FAMILIAR',
                'PRESTADA',
                'OTRO'
            ])->nullable();
            $table->decimal('monthly_rent', 10, 2)->nullable(); // If rented
            $table->tinyInteger('years_at_address')->nullable();
            $table->tinyInteger('months_at_address')->nullable();

            // Geolocation (for risk analysis)
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Verification
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_method')->nullable(); // DOCUMENT, GEOLOCATION, VISIT

            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('applicant_id')->references('id')->on('applicants')->onDelete('cascade');

            // Indexes for common queries
            $table->index(['applicant_id', 'type']);
            $table->index(['applicant_id', 'is_primary']);
            $table->index('postal_code'); // For geographic analysis
            $table->index('state'); // For regional reports
            $table->index(['tenant_id', 'state']); // For tenant regional reports
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
