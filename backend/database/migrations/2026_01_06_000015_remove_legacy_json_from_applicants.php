<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            // Remove legacy JSON columns
            // Data should have been migrated to normalized tables before running this
            $table->dropColumn([
                'personal_data',
                'contact_info',
                'address',
                'employment_info',
                'bank_info',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            // Restore legacy JSON columns
            $table->json('personal_data')->nullable();
            $table->json('contact_info')->nullable();
            $table->json('address')->nullable();
            $table->json('employment_info')->nullable();
            $table->json('bank_info')->nullable();
        });
    }
};
