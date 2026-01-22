<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            // Increase birth_state column length from 5 to 50 to store full state names
            // RENAPO returns full state names like "CHIAPAS", "CIUDAD DE MEXICO", etc.
            // instead of just codes like "CS", "CDMX"
            $table->string('birth_state', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            // Revert to original 5-character length
            $table->string('birth_state', 5)->nullable()->change();
        });
    }
};
