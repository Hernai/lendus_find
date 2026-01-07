<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Type
            $table->enum('type', ['PERSONA_FISICA', 'PERSONA_MORAL'])->default('PERSONA_FISICA');

            // Identification
            $table->string('curp', 18)->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('ine_clave', 20)->nullable();

            // Personal Data (JSON for flexibility)
            $table->json('personal_data'); // first_name, last_name, birth_date, gender, nationality, marital_status

            // Contact Info (JSON)
            $table->json('contact_info'); // phone, email, secondary_phone

            // Address (JSON)
            $table->json('address'); // street, number, neighborhood, postal_code, city, state, country, housing_type, years_living

            // Employment Info (JSON)
            $table->json('employment_info')->nullable(); // employment_status, company_name, position, monthly_income, etc.

            // Bank Info (JSON)
            $table->json('bank_info')->nullable(); // bank_name, clabe, account_number

            // KYC Status
            $table->enum('kyc_status', ['PENDING', 'IN_PROGRESS', 'VERIFIED', 'REJECTED'])->default('PENDING');
            $table->json('kyc_data')->nullable(); // KYC provider response data
            $table->timestamp('kyc_verified_at')->nullable();

            // Signature
            $table->text('signature_base64')->nullable();
            $table->timestamp('signature_date')->nullable();
            $table->string('signature_ip', 45)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->index(['tenant_id', 'curp']);
            $table->index(['tenant_id', 'rfc']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicants');
    }
};
