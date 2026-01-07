<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            // Additional INE fields
            $table->string('ine_ocr', 15)->nullable()->after('ine_clave');
            $table->string('ine_folio', 25)->nullable()->after('ine_ocr');

            // Passport fields
            $table->string('passport_number', 15)->nullable()->after('ine_folio');
            $table->date('passport_issue_date')->nullable()->after('passport_number');
            $table->date('passport_expiry_date')->nullable()->after('passport_issue_date');
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn([
                'ine_ocr',
                'ine_folio',
                'passport_number',
                'passport_issue_date',
                'passport_expiry_date',
            ]);
        });
    }
};
