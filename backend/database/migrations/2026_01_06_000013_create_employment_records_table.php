<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('applicant_id');

            // Employment Status
            $table->boolean('is_current')->default(true);
            $table->enum('employment_type', [
                'EMPLEADO',           // Empleado formal
                'INDEPENDIENTE',      // Freelancer/Independiente
                'EMPRESARIO',         // Dueño de negocio
                'PENSIONADO',         // Pensionado/Jubilado
                'ESTUDIANTE',         // Estudiante
                'HOGAR',              // Ama de casa
                'DESEMPLEADO',        // Desempleado
                'OTRO'
            ]);

            // Company Info
            $table->string('company_name', 200)->nullable();
            $table->string('company_rfc', 13)->nullable();
            $table->string('company_industry', 100)->nullable(); // Giro/Industria
            $table->enum('company_size', ['MICRO', 'PEQUENA', 'MEDIANA', 'GRANDE'])->nullable();

            // Position Info
            $table->string('position', 100)->nullable(); // Puesto
            $table->string('department', 100)->nullable();
            $table->enum('contract_type', [
                'INDEFINIDO',
                'TEMPORAL',
                'POR_OBRA',
                'HONORARIOS',
                'COMISION',
                'OTRO'
            ])->nullable();

            // Dates
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('seniority_months')->nullable(); // Calculated or provided

            // Income (critical for credit scoring)
            $table->decimal('monthly_income', 12, 2)->nullable(); // Ingreso mensual bruto
            $table->decimal('monthly_net_income', 12, 2)->nullable(); // Ingreso neto
            $table->enum('payment_frequency', ['SEMANAL', 'QUINCENAL', 'MENSUAL'])->default('MENSUAL');
            $table->enum('income_type', [
                'NOMINA',
                'HONORARIOS',
                'MIXTO',
                'COMISIONES',
                'NEGOCIO_PROPIO',
                'PENSION',
                'OTRO'
            ])->nullable();

            // Additional Income
            $table->decimal('other_income', 12, 2)->nullable();
            $table->string('other_income_source', 200)->nullable();

            // Contact at work
            $table->string('work_phone', 15)->nullable();
            $table->string('work_phone_ext', 10)->nullable();
            $table->string('supervisor_name', 200)->nullable();
            $table->string('supervisor_phone', 15)->nullable();

            // Work Address (FK to addresses or inline)
            $table->uuid('work_address_id')->nullable();

            // Verification
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->text('verification_notes')->nullable();
            $table->string('verification_method')->nullable(); // RECIBO_NOMINA, CONSTANCIA, LLAMADA

            // Proof document reference
            $table->uuid('proof_document_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('applicant_id')->references('id')->on('applicants')->onDelete('cascade');
            $table->foreign('work_address_id')->references('id')->on('addresses')->nullOnDelete();
            $table->foreign('proof_document_id')->references('id')->on('documents')->nullOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();

            // Indexes
            $table->index(['applicant_id', 'is_current']);
            $table->index('monthly_income'); // For income-based filtering
            $table->index(['tenant_id', 'employment_type']); // For reports
            $table->index('company_rfc'); // For employer analysis
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_records');
    }
};
