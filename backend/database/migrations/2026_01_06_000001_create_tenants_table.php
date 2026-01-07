<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('legal_name')->nullable();
            $table->string('rfc', 13)->nullable();

            // Branding
            $table->json('branding')->nullable(); // primary_color, secondary_color, logo_url, etc.

            // Configuration
            $table->json('settings')->nullable(); // otp_provider, kyc_provider, limits, etc.
            $table->json('webhook_config')->nullable(); // url, secret_key, events

            // Contact
            $table->string('email')->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('website')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
