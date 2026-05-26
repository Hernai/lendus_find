<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `features` (JSONB) al tenant — feature flags por SOFOM.
 *
 * Flags reconocidas:
 *  - loan_portfolio: bool — habilita modelos Loan/Payment/Extension + UI
 *  - unified_consent_screen: bool — usa WelcomeConsentView en lugar del
 *    flow tradicional
 *  - phone_score_enabled: bool — invoca PhoneScoreService al verificar OTP
 *  - auto_disbursement: bool — al aceptar oferta dispara StpService.disburse()
 *
 * Default null = ningún flag activo (mantiene comportamiento legacy).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->jsonb('features')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }
};
