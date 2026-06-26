<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensancha persons.nationality y persons.birth_country de varchar(3) a
 * varchar(50).
 *
 * Why: la tabla `persons` se creó con estas columnas en varchar(3) pensando en
 * códigos ISO (MEX), pero la app captura y valida nombres completos
 * ("MEXICO" / "MEXICANA", validación max:50). Al guardar datos personales con
 * nacionalidad "MEXICO" Postgres lanza 22001 (value too long), rompiendo el
 * onboarding (updatePersonalData) y, por tanto, la creación de solicitudes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE persons ALTER COLUMN nationality TYPE varchar(50)');
            DB::statement('ALTER TABLE persons ALTER COLUMN birth_country TYPE varchar(50)');

            return;
        }

        Schema::table('persons', function (Blueprint $table) {
            $table->string('nationality', 50)->default('MX')->change();
            $table->string('birth_country', 50)->default('MX')->change();
        });
    }

    public function down(): void
    {
        // No-op: volver a varchar(3) truncaría datos ya guardados.
    }
};
