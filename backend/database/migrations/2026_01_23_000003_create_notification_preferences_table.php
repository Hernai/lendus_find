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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('recipient_id'); // applicant_account_id or staff_account_id
            $table->string('recipient_type'); // APPLICANT or STAFF

            // Preferences per channel
            $table->boolean('sms_enabled')->default(true);
            $table->boolean('whatsapp_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);

            // Event-specific opt-outs (JSONB array of event names)
            $table->json('disabled_events')->nullable(); // ["application.submitted", "document.approved"]

            $table->timestamps();

            // Indexes
            $table->unique(['tenant_id', 'recipient_id', 'recipient_type']);
            $table->index('recipient_id');

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            // No FK constraints for recipient_id since it can reference either applicant_accounts or staff_accounts
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
