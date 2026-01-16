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
        Schema::create('api_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('applicant_id')->nullable();
            $table->uuid('application_id')->nullable();
            $table->uuid('user_id')->nullable();

            // Provider info
            $table->string('provider', 50); // NUBARIUM, RENAPO, SAT, TWILIO, etc.
            $table->string('service', 100); // ine_ocr, curp_validate, face_match, sms_send, etc.
            $table->string('endpoint', 500)->nullable(); // Full URL called

            // Request details
            $table->string('method', 10)->default('POST'); // GET, POST, PUT, etc.
            $table->json('request_headers')->nullable();
            $table->json('request_payload')->nullable(); // Request body (sensitive data should be masked)

            // Response details
            $table->integer('response_status')->nullable(); // HTTP status code
            $table->json('response_headers')->nullable();
            $table->json('response_body')->nullable(); // Response body (sensitive data should be masked)

            // Result
            $table->boolean('success')->default(false);
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();

            // Performance metrics
            $table->integer('duration_ms')->nullable(); // Request duration in milliseconds
            $table->decimal('cost', 10, 4)->nullable(); // Cost of the API call if applicable

            // Additional context
            $table->json('metadata')->nullable(); // Any additional context data
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('tenant_id');
            $table->index('applicant_id');
            $table->index('application_id');
            $table->index('provider');
            $table->index('service');
            $table->index('success');
            $table->index('created_at');
            $table->index(['provider', 'service']);
            $table->index(['tenant_id', 'provider', 'created_at']);

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            $table->foreign('applicant_id')->references('id')->on('applicants')->onDelete('set null');
            $table->foreign('application_id')->references('id')->on('applications')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
