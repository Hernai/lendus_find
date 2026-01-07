<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('type'); // PERSONAL, PAYROLL, SME, LEASING, FACTORING
            $table->text('description')->nullable();
            $table->string('icon')->nullable();

            // Loan Rules (JSON for flexibility)
            $table->json('rules'); // min/max amounts, terms, rates, commissions, etc.

            // Required Documents (JSON array)
            $table->json('required_docs')->nullable();

            // Extra Fields for this product (JSON)
            $table->json('extra_fields')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
