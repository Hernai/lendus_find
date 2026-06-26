<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convierte el índice único (tenant_id, clabe) de bank_accounts en un índice
 * único PARCIAL que ignora las filas soft-deleted (WHERE deleted_at IS NULL).
 *
 * Why: la tabla usa SoftDeletes. El índice único plano contaba también las
 * filas borradas, así que cuando el onboarding borra (soft) y vuelve a crear la
 * cuenta con la misma CLABE, el insert chocaba con SQLSTATE[23505]
 * (person_bank_accounts_tenant_id_clabe_unique). Con el índice parcial, solo las
 * cuentas activas deben ser únicas; las borradas dejan libre la CLABE.
 */
return new class extends Migration
{
    private const INDEX = 'person_bank_accounts_tenant_id_clabe_unique';

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return; // los índices parciales son específicos de Postgres
        }

        // Es un UNIQUE CONSTRAINT (no un índice suelto), así que se quita con
        // DROP CONSTRAINT. Los constraints no admiten WHERE, por eso lo
        // reemplazamos por un índice único PARCIAL.
        DB::statement('ALTER TABLE bank_accounts DROP CONSTRAINT IF EXISTS ' . self::INDEX);
        DB::statement('DROP INDEX IF EXISTS ' . self::INDEX);
        DB::statement(
            'CREATE UNIQUE INDEX ' . self::INDEX .
            ' ON bank_accounts (tenant_id, clabe) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS ' . self::INDEX);
        DB::statement(
            'ALTER TABLE bank_accounts ADD CONSTRAINT ' . self::INDEX .
            ' UNIQUE (tenant_id, clabe)'
        );
    }
};
