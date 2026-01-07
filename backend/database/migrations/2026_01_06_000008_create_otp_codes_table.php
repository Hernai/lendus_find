<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();

            // Destination
            $table->string('phone', 15)->nullable();
            $table->string('email')->nullable();

            // Code
            $table->string('code', 6);
            $table->enum('channel', ['SMS', 'WHATSAPP', 'EMAIL'])->default('SMS');

            // Purpose
            $table->string('purpose')->default('LOGIN'); // LOGIN, VERIFY_PHONE, VERIFY_EMAIL, etc.

            // Status
            $table->boolean('is_used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at');

            // Tracking
            $table->integer('attempts')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Provider Response
            $table->string('provider_message_id')->nullable();
            $table->string('provider_status')->nullable();

            $table->timestamps();

            $table->index(['phone', 'code', 'is_used']);
            $table->index(['email', 'code', 'is_used']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
