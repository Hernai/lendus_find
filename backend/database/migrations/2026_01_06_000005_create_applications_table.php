<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('applicant_id');
            $table->uuid('product_id');

            // Folio (unique per tenant)
            $table->string('folio')->unique();

            // Loan Details
            $table->decimal('requested_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->integer('term_months');
            $table->enum('payment_frequency', ['WEEKLY', 'BIWEEKLY', 'MONTHLY'])->default('MONTHLY');

            // Calculated Values (from simulation)
            $table->decimal('interest_rate', 5, 2); // Annual rate
            $table->decimal('opening_commission', 5, 2)->default(0);
            $table->decimal('monthly_payment', 12, 2)->nullable();
            $table->decimal('total_to_pay', 12, 2)->nullable();
            $table->decimal('cat', 5, 2)->nullable(); // Costo Anual Total

            // Purpose
            $table->string('purpose')->nullable();
            $table->text('purpose_description')->nullable();

            // Status
            $table->enum('status', [
                'DRAFT',
                'SUBMITTED',
                'IN_REVIEW',
                'DOCS_PENDING',
                'APPROVED',
                'REJECTED',
                'CANCELLED',
                'DISBURSED',
                'ACTIVE',
                'COMPLETED',
                'DEFAULT'
            ])->default('DRAFT');

            // Status History (JSON array)
            $table->json('status_history')->nullable();

            // Assignment
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();

            // Review
            $table->text('rejection_reason')->nullable();
            $table->text('internal_notes')->nullable();

            // Scoring (JSON for bureau/internal scoring data)
            $table->json('scoring_data')->nullable();
            $table->integer('risk_score')->nullable();
            $table->string('risk_level')->nullable(); // LOW, MEDIUM, HIGH

            // Disbursement
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->string('disbursement_reference')->nullable();

            // Extra Fields Data (JSON)
            $table->json('extra_data')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('applicant_id')->references('id')->on('applicants')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('applicant_id');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
