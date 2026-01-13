<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->onDelete('cascade');

            // Recipient and sender
            $table->string('to', 30); // E.164 format (+52 + 10 digits)
            $table->string('from', 30);

            // Message content
            $table->text('body');
            $table->enum('type', ['sms', 'whatsapp'])->default('sms');
            $table->enum('direction', ['inbound', 'outbound-api', 'outbound-call', 'outbound-reply'])->default('outbound-api');

            // Twilio response data
            $table->string('sid', 40)->nullable()->unique(); // Twilio message SID
            $table->enum('status', ['queued', 'sending', 'sent', 'delivered', 'undelivered', 'failed', 'received'])->default('queued');
            $table->integer('num_segments')->default(1);

            // Pricing
            $table->decimal('price', 10, 4)->nullable();
            $table->string('price_unit', 5)->nullable(); // USD, MXN, etc.

            // Error tracking
            $table->integer('error_code')->nullable();
            $table->text('error_message')->nullable();

            // Metadata
            $table->json('metadata')->nullable(); // Additional context (user_id, application_id, etc.)

            // Timestamps
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'created_at']);
            $table->index(['to', 'created_at']);
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
