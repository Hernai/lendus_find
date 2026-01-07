<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('applicant_id');

            // Account Purpose
            $table->enum('type', ['DISBURSEMENT', 'PAYMENT', 'BOTH'])->default('BOTH');
            $table->boolean('is_primary')->default(false);

            // Bank Info
            $table->string('bank_name', 100);
            $table->string('bank_code', 10)->nullable(); // Clave SPEI del banco

            // Account Details
            $table->string('clabe', 18); // CLABE Interbancaria (18 digits)
            $table->string('account_number', 20)->nullable();
            $table->string('card_number_last4', 4)->nullable(); // Last 4 digits only for reference

            // Account Type
            $table->enum('account_type', [
                'DEBITO',
                'NOMINA',
                'AHORRO',
                'CHEQUES',
                'INVERSION',
                'OTRO'
            ])->default('DEBITO');

            // Account Holder
            $table->string('holder_name', 200);
            $table->string('holder_rfc', 13)->nullable();
            $table->boolean('is_own_account')->default(true); // Is it the applicant's own account?

            // Verification (SPEI validation)
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_method')->nullable(); // SPEI_VALIDATION, PENNY_TEST, MANUAL
            $table->string('verification_reference')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('applicant_id')->references('id')->on('applicants')->onDelete('cascade');

            // Indexes
            $table->index(['applicant_id', 'is_primary']);
            $table->index(['applicant_id', 'type']);
            $table->unique(['tenant_id', 'clabe']); // CLABE should be unique per tenant
            $table->index('bank_code'); // For bank-based reports
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
