<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `loan_payments` — historial de pagos recibidos contra un Loan.
 * Cada pago aplica al outstanding_balance del Loan padre. El canal puede
 * ser CONEKTA/OPENPAY (gateway web) o STP (transferencia interbancaria)
 * o MANUAL (registrado por staff).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('loan_id');
            $table->decimal('amount', 12, 2);
            $table->timestamp('paid_at')->nullable();
            $table->string('status', 32)->default('PENDING'); // PENDING|COMPLETED|FAILED|REFUNDED
            $table->string('channel', 32);                     // CONEKTA|OPENPAY|STP|MANUAL
            $table->string('provider', 32)->nullable();
            $table->string('provider_reference', 128)->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('loan_id')->references('id')->on('loans')->cascadeOnDelete();

            $table->index(['loan_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
    }
};
