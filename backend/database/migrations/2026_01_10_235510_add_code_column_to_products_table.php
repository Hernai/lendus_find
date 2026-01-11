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
            $table->string('code', 20)->nullable()->after('name');
        });

        // Generate codes for existing products based on their type
        $products = DB::table('products')->get();
        $counters = [];

        foreach ($products as $product) {
            $prefix = match ($product->type) {
                'PERSONAL' => 'PERS',
                'AUTO' => 'AUTO',
                'HIPOTECARIO' => 'HIPO',
                'PYME' => 'PYME',
                'NOMINA' => 'NOMI',
                'ARRENDAMIENTO' => 'ARRE',
                default => 'PROD',
            };

            $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;
            $code = $prefix . '-' . str_pad($counters[$prefix], 3, '0', STR_PAD_LEFT);

            DB::table('products')
                ->where('id', $product->id)
                ->update(['code' => $code]);
        }

        // Now make the column required and add unique index
        Schema::table('products', function (Blueprint $table) {
            $table->string('code', 20)->nullable(false)->change();
            $table->unique(['tenant_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->dropColumn('code');
        });
    }
};
