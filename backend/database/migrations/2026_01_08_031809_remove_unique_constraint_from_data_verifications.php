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
        Schema::table('data_verifications', function (Blueprint $table) {
            // Drop the unique constraint to allow multiple historical records
            $table->dropUnique(['applicant_id', 'field_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_verifications', function (Blueprint $table) {
            // Restore the unique constraint if rolling back
            $table->unique(['applicant_id', 'field_name']);
        });
    }
};
