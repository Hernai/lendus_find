<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Increases precision of opening_commission from decimal(5,2) to decimal(12,2)
     * to support larger commission amounts.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL syntax
            DB::statement('ALTER TABLE applications ALTER COLUMN opening_commission TYPE decimal(12, 2)');
        } elseif ($driver === 'mysql') {
            // MySQL syntax
            DB::statement('ALTER TABLE applications MODIFY COLUMN opening_commission DECIMAL(12, 2)');
        }
        // SQLite doesn't support ALTER COLUMN, but handles decimal as REAL anyway
        // For SQLite in tests, we skip this migration as precision is not enforced
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE applications ALTER COLUMN opening_commission TYPE decimal(5, 2)');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE applications MODIFY COLUMN opening_commission DECIMAL(5, 2)');
        }
    }
};
