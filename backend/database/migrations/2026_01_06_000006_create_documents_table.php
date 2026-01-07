<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->uuid('applicant_id');

            // Document Type
            $table->string('type'); // INE_FRONT, INE_BACK, PROOF_ADDRESS, PROOF_INCOME, etc.
            $table->string('name');
            $table->text('description')->nullable();

            // File Info
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type');
            $table->integer('file_size'); // bytes
            $table->string('storage_disk')->default('s3');

            // Status
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->text('rejection_reason')->nullable();

            // Review
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Metadata (OCR results, validation data)
            $table->json('metadata')->nullable();

            // Security
            $table->string('checksum')->nullable(); // MD5/SHA hash
            $table->boolean('is_sensitive')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('application_id')->references('id')->on('applications')->onDelete('cascade');
            $table->foreign('applicant_id')->references('id')->on('applicants')->onDelete('cascade');

            $table->index(['application_id', 'type']);
            $table->index(['applicant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
