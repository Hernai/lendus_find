<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plazo aprobado en días para productos BULLET (MoneyCapital y similares).
 * Gemela de `requested_term_days`; se llena al aceptar una contraoferta o
 * aprobar una solicitud de un producto cuyo plazo se mide en días.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedSmallInteger('approved_term_days')->nullable()->after('approved_term_months');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('approved_term_days');
        });
    }
};
