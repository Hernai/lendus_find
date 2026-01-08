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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable(); // Who performed the action
            $table->uuid('applicant_id')->nullable(); // Affected applicant
            $table->uuid('application_id')->nullable(); // Affected application

            // Action details
            $table->string('action', 50); // LOGIN, LOGOUT, OTP_REQUESTED, STEP_COMPLETED, etc.
            $table->string('entity_type', 100)->nullable(); // User, Applicant, Application, Document
            $table->uuid('entity_id')->nullable();

            // Changes tracking
            $table->json('old_values')->nullable(); // Values before change
            $table->json('new_values')->nullable(); // Values after change
            $table->json('metadata')->nullable(); // Additional context

            // Request information
            $table->string('ip_address', 45)->nullable(); // IPv6 compatible
            $table->text('user_agent')->nullable();

            // Geolocation
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('country', 2)->nullable(); // ISO country code

            // Device info (parsed from user agent)
            $table->string('device_type', 20)->nullable(); // mobile, desktop, tablet
            $table->string('browser', 50)->nullable();
            $table->string('browser_version', 20)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('os_version', 20)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes for common queries
            $table->index('tenant_id');
            $table->index('user_id');
            $table->index('applicant_id');
            $table->index('application_id');
            $table->index('action');
            $table->index('entity_type');
            $table->index('created_at');
            $table->index(['tenant_id', 'action']);
            $table->index(['tenant_id', 'created_at']);

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
