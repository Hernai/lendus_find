<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El CAT (Costo Anual Total) de microcréditos de muy corto plazo (BULLET a
 * días, estilo MoneyCapital) puede superar el 9999.99% que permite
 * numeric(8,4). Se amplía a numeric(12,4) para no desbordar al crear la
 * solicitud.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE applications ALTER COLUMN cat TYPE numeric(12,4)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE applications ALTER COLUMN cat TYPE numeric(8,4)');
    }
};
