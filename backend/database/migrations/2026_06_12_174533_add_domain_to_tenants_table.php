<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el campo `domain` al modelo Tenant — dominio público completo
 * donde el SOFOM atiende a sus solicitantes (ej. `moneycapital.lendus.app`,
 * `app.moneycapital.mx`).
 *
 * Resolución del tenant en producción:
 *  1. `tenants.domain` = host exacto del request (match estricto, gana)
 *  2. Fallback al `tenants.slug` = primer segmento del subdominio
 *
 * Esto permite tenants con dominio propio (`app.moneycapital.mx`) sin
 * cambiar el slug interno.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('domain', 191)->nullable()->after('slug');
            $table->unique('domain');
            $table->index('domain', 'tenants_domain_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex('tenants_domain_lookup');
            $table->dropUnique(['domain']);
            $table->dropColumn('domain');
        });
    }
};
