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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name'); // Internal name (e.g., "Application Submitted")
            $table->string('event'); // Event trigger (e.g., "application.submitted")
            $table->string('channel'); // SMS, WHATSAPP, EMAIL, IN_APP
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(5); // 1 (highest) to 10 (lowest)

            // Content fields
            $table->string('subject')->nullable(); // For EMAIL only
            $table->text('body'); // Template content with Handlebars variables
            $table->text('html_body')->nullable(); // For EMAIL only (HTML version)

            // Metadata
            $table->json('available_variables')->nullable(); // List of available variables for this template
            $table->json('metadata')->nullable(); // Additional settings (from_name, reply_to, etc.)

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'event', 'channel']);
            $table->index(['tenant_id', 'is_active']);

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
