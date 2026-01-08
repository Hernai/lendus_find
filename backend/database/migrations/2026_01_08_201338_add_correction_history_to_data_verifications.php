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
            // JSON field to store correction history
            // Format: [{ old_value, new_value, corrected_at, reason }]
            $table->json('correction_history')->nullable()->after('corrected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_verifications', function (Blueprint $table) {
            $table->dropColumn('correction_history');
        });
    }
};
