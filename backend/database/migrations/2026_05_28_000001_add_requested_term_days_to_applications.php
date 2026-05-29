<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plazo en días para productos BULLET (MoneyCapital y similares) cuyo plazo
 * se mide en días, no en meses. Complementa a `requested_term_months`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedSmallInteger('requested_term_days')->nullable()->after('requested_term_months');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('requested_term_days');
        });
    }
};
