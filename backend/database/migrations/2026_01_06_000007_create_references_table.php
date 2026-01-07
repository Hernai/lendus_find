<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('applicant_id');
            $table->uuid('application_id')->nullable();

            // Reference Info
            $table->string('first_name');
            $table->string('last_name_1');
            $table->string('last_name_2')->nullable();
            $table->string('full_name');
            $table->string('phone', 15);
            $table->string('email')->nullable();

            // Relationship
            $table->string('relationship'); // PADRE_MADRE, HERMANO, CONYUGE, AMIGO, COMPAÑERO_TRABAJO, etc.
            $table->enum('type', ['PERSONAL', 'WORK'])->default('PERSONAL');

            // Verification
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable();

            // Contact Attempts (JSON array)
            $table->json('contact_attempts')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('applicant_id')->references('id')->on('applicants')->onDelete('cascade');
            $table->foreign('application_id')->references('id')->on('applications')->onDelete('cascade');

            $table->index('applicant_id');
            $table->index('application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('references');
    }
};
