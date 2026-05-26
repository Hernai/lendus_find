<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `loans` — préstamos desembolsados (módulo opt-in para tenants
 * con `features.loan_portfolio=true`, ej. MoneyCapital).
 *
 * Una Application transiciona a DISBURSED y se crea un Loan ligado.
 * El Loan vive su propio ciclo independiente (ACTIVE → COMPLETED o
 * DEFAULT), con pagos, prórrogas y recompensas asociadas.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('application_id');
            $table->uuid('applicant_account_id');
            $table->uuid('person_id');
            $table->uuid('bank_account_id')->nullable();

            // Términos del préstamo
            $table->decimal('principal_amount', 12, 2);
            $table->decimal('interest_rate', 6, 2); // anual %
            $table->integer('term_days');
            $table->decimal('opening_commission_amount', 12, 2)->default(0);
            $table->decimal('total_to_pay', 12, 2);

            // Estado
            $table->string('status', 32)->default('DISBURSED'); // DISBURSED|ACTIVE|COMPLETED|DEFAULT|RESTRUCTURED
            $table->timestamp('disbursed_at')->nullable();
            $table->date('due_date');
            $table->timestamp('completed_at')->nullable();

            // Balance
            $table->decimal('outstanding_balance', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('late_fee_accrued', 12, 2)->default(0);

            // Dispersión
            $table->string('disbursement_provider', 32)->nullable(); // STP|MANUAL
            $table->string('disbursement_reference', 128)->nullable();

            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('application_id')->references('id')->on('applications')->restrictOnDelete();
            $table->foreign('applicant_account_id')->references('id')->on('applicant_accounts');
            $table->foreign('person_id')->references('id')->on('persons');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->nullOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index(['applicant_account_id', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
