<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('min_amount', 15, 2)->default(0)->after('description');
            $table->decimal('max_amount', 15, 2)->default(0)->after('min_amount');
            $table->integer('min_term_months')->default(1)->after('max_amount');
            $table->integer('max_term_months')->default(12)->after('min_term_months');
            $table->decimal('interest_rate', 8, 2)->default(0)->after('max_term_months');
            $table->decimal('opening_commission', 8, 2)->default(0)->after('interest_rate');
            $table->decimal('late_fee_rate', 8, 2)->default(0)->after('opening_commission');
            $table->json('payment_frequencies')->nullable()->after('late_fee_rate');
            $table->json('required_documents')->nullable()->after('payment_frequencies');
            $table->json('eligibility_rules')->nullable()->after('required_documents');
        });

        // Migrate data from rules JSON column to new columns
        $products = DB::table('products')->get();

        foreach ($products as $product) {
            $rules = json_decode($product->rules, true) ?? [];
            $requiredDocs = json_decode($product->required_docs, true) ?? [];

            DB::table('products')
                ->where('id', $product->id)
                ->update([
                    'min_amount' => $rules['min_amount'] ?? 0,
                    'max_amount' => $rules['max_amount'] ?? 0,
                    'min_term_months' => $rules['min_term'] ?? $rules['min_term_months'] ?? 1,
                    'max_term_months' => $rules['max_term'] ?? $rules['max_term_months'] ?? 12,
                    'interest_rate' => $rules['interest_rate'] ?? 0,
                    'opening_commission' => $rules['opening_commission'] ?? 0,
                    'late_fee_rate' => $rules['late_fee_rate'] ?? 0,
                    'payment_frequencies' => json_encode($rules['payment_frequencies'] ?? ['MONTHLY']),
                    'required_documents' => json_encode($requiredDocs),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'min_amount',
                'max_amount',
                'min_term_months',
                'max_term_months',
                'interest_rate',
                'opening_commission',
                'late_fee_rate',
                'payment_frequencies',
                'required_documents',
                'eligibility_rules',
            ]);
        });
    }
};
