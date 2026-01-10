<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_api_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->onDelete('cascade');

            // Provider identification
            $table->string('provider'); // twilio, mailgun, nubarium, circulo_credito, mati, etc.
            $table->string('service_type'); // sms, email, kyc, credit_bureau, whatsapp, etc.

            // Credentials (encrypted)
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->text('account_sid')->nullable(); // For Twilio
            $table->text('auth_token')->nullable();
            $table->string('from_number', 20)->nullable(); // For SMS/WhatsApp
            $table->string('from_email', 100)->nullable(); // For Email
            $table->string('domain', 200)->nullable(); // For Mailgun domain
            $table->string('webhook_url', 500)->nullable();
            $table->string('webhook_secret', 100)->nullable();

            // Additional config as JSON
            $table->json('extra_config')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_sandbox')->default(false); // Test mode
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_success')->nullable();
            $table->text('last_test_error')->nullable();

            $table->timestamps();

            // One config per provider per tenant
            $table->unique(['tenant_id', 'provider', 'service_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_api_configs');
    }
};
