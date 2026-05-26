<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `card_number_encrypted` (text) a `bank_accounts` para soportar
 * la captura del PAN completo de 16 dígitos de tarjeta débito (MoneyCapital
 * permite débito como medio de dispersión alternativo a CLABE).
 *
 * El PAN se cifra con Laravel Crypt en el modelo (getter/setter).
 * `card_number_last4` ya existe para mostrar en UI.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->text('card_number_encrypted')->nullable()->after('card_number_last4');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('card_number_encrypted');
        });
    }
};
