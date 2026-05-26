<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `loan_rewards` — recompensas ganadas por el applicant
 * (pago puntual, referidos, milestones).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('loan_rewards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('applicant_account_id');
            $table->uuid('loan_id')->nullable();
            $table->string('type', 32); // PUNCTUAL_PAYMENT|REFERRAL|MILESTONE
            $table->integer('points')->default(0);
            $table->string('description', 255);
            $table->timestamp('earned_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('applicant_account_id')->references('id')->on('applicant_accounts');
            $table->foreign('loan_id')->references('id')->on('loans')->nullOnDelete();

            $table->index(['applicant_account_id', 'type']);
            $table->index('redeemed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_rewards');
    }
};
