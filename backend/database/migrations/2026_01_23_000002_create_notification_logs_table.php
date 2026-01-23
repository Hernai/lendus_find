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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('notification_template_id')->nullable(); // Nullable in case template is deleted
            $table->uuid('recipient_id')->nullable(); // Recipient (applicant_account_id or staff_account_id)
            $table->string('recipient_type')->nullable(); // APPLICANT or STAFF

            // Notification details
            $table->string('channel'); // SMS, WHATSAPP, EMAIL, IN_APP
            $table->string('event'); // Event that triggered this notification
            $table->string('recipient'); // Phone number or email
            $table->string('status'); // PENDING, SENT, DELIVERED, FAILED, READ

            // Content (rendered)
            $table->string('subject')->nullable(); // For EMAIL
            $table->text('body'); // Rendered content (no variables)
            $table->text('html_body')->nullable(); // For EMAIL

            // Delivery tracking
            $table->string('external_id')->nullable(); // Provider message ID (Twilio SID, SendGrid ID, etc.)
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable(); // Provider response, costs, etc.

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'recipient_id', 'channel']);
            $table->index(['tenant_id', 'event']);
            $table->index('external_id');
            $table->index('created_at');

            // Foreign keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('notification_template_id')->references('id')->on('notification_templates')->onDelete('set null');
            // No FK constraints for recipient_id since it can reference either applicant_accounts or staff_accounts
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
