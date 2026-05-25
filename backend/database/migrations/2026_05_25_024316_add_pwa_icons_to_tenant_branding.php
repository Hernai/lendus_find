<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega iconos PWA al branding de tenant.
 *
 * - icon_192_url / icon_512_url: iconos estándar PWA.
 * - maskable_icon_url: variante "maskable" para Android (zona segura).
 * - pwa_name / pwa_short_name: textos del manifest (caen en `name` y `short_name`).
 * - pwa_theme_color / pwa_background_color: hex usados por el manifest.
 *
 * Si las columnas pwa_* quedan vacías, el ManifestController hará fallback
 * a primary_color y al nombre del tenant.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_branding', function (Blueprint $table) {
            $table->text('icon_192_url')->nullable()->after('favicon_url');
            $table->text('icon_512_url')->nullable()->after('icon_192_url');
            $table->text('maskable_icon_url')->nullable()->after('icon_512_url');
            $table->string('pwa_name', 120)->nullable()->after('maskable_icon_url');
            $table->string('pwa_short_name', 12)->nullable()->after('pwa_name');
            $table->string('pwa_theme_color', 9)->nullable()->after('pwa_short_name');
            $table->string('pwa_background_color', 9)->nullable()->after('pwa_theme_color');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_branding', function (Blueprint $table) {
            $table->dropColumn([
                'icon_192_url',
                'icon_512_url',
                'maskable_icon_url',
                'pwa_name',
                'pwa_short_name',
                'pwa_theme_color',
                'pwa_background_color',
            ]);
        });
    }
};
