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
        Schema::table('person_addresses', function (Blueprint $table) {
            // Increase state column from varchar(5) to varchar(50) to allow full state names
            $table->string('state', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('person_addresses', function (Blueprint $table) {
            $table->string('state', 5)->change();
        });
    }
};
