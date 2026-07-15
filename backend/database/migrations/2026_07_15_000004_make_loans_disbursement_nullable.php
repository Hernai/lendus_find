<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un crédito en PENDING_DISBURSEMENT (cartera externa aún no dispersa) no tiene
 * disbursed_at ni due_date hasta que la cartera confirme. Los hacemos nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE loans ALTER COLUMN disbursed_at DROP NOT NULL');
        DB::statement('ALTER TABLE loans ALTER COLUMN due_date DROP NOT NULL');
    }

    public function down(): void
    {
        // No re-imponemos NOT NULL: podrían existir loans pendientes sin fecha.
    }
};
