<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bandera para "congelar" el branding de un tenant. Cuando un admin guarda el
 * branding desde el panel, se marca is_locked=true y los seeders dejan de
 * sobreescribirlo en cada deploy (los seeders usan updateOrCreate). Así el
 * branding definitivo configurado a mano NO se revierte a los valores de fábrica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_branding', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_branding', function (Blueprint $table) {
            $table->dropColumn('is_locked');
        });
    }
};
