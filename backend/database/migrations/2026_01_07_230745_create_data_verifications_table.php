<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('data_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('applicant_id');

            // Field being verified
            $table->string('field_name', 50); // first_name, curp, rfc, phone, email, etc.
            $table->string('field_value')->nullable(); // The value that was verified

            // Verification details
            $table->string('method', 30)->default('MANUAL'); // MANUAL, OTP, API, DOCUMENT, BUREAU
            $table->boolean('is_verified')->default(true);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // Extra data from verification service

            // Who verified
            $table->uuid('verified_by')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('applicant_id')->references('id')->on('applicants')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();

            // Indexes
            $table->index(['applicant_id', 'field_name']);
            $table->unique(['applicant_id', 'field_name']); // Only one verification per field per applicant
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_verifications');
    }
};
