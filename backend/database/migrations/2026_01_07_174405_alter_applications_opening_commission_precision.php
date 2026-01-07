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
        // Use raw SQL for PostgreSQL to alter column type
        DB::statement('ALTER TABLE applications ALTER COLUMN opening_commission TYPE decimal(12, 2)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE applications ALTER COLUMN opening_commission TYPE decimal(5, 2)');
    }
};
