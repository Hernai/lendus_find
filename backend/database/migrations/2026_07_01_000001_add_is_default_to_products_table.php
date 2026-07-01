<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca UN producto por tenant como predeterminado. Se usa cuando el cliente
 * entra por "Iniciar sesión" (sin pasar por el simulador): el onboarding crea
 * la solicitud con este producto y su monto/plazo default; el admin los ajusta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
