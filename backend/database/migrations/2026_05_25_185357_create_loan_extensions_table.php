<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `loan_extensions` — prórrogas/extensiones de fecha de pago.
 * El usuario solicita extensión de 7 o 15 días pagando un fee. Cuando se
 * aprueba, el due_date del Loan se actualiza al new_due_date.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('loan_extensions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('loan_id');
            $table->integer('days_added');
            $table->decimal('fee_amount', 12, 2);
            $table->date('previous_due_date');
            $table->date('new_due_date');
            $table->string('status', 32)->default('PENDING'); // PENDING|APPROVED|REJECTED
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->string('rejection_reason', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('loan_id')->references('id')->on('loans')->cascadeOnDelete();

            $table->index(['loan_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_extensions');
    }
};
