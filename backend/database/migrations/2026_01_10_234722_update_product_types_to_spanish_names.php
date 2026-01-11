<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Updates product types from old English names to new Spanish names.
     */
    public function up(): void
    {
        // Map old types to new types
        $typeMap = [
            'PAYROLL' => 'NOMINA',
            'SME' => 'PYME',
            'LEASING' => 'ARRENDAMIENTO',
            'FACTORING' => 'PYME', // Convert factoring to PYME as closest match
        ];

        foreach ($typeMap as $oldType => $newType) {
            DB::table('products')
                ->where('type', $oldType)
                ->update(['type' => $newType]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Map new types back to old types
        $typeMap = [
            'NOMINA' => 'PAYROLL',
            'PYME' => 'SME',
            'ARRENDAMIENTO' => 'LEASING',
        ];

        foreach ($typeMap as $newType => $oldType) {
            DB::table('products')
                ->where('type', $newType)
                ->update(['type' => $oldType]);
        }
    }
};
