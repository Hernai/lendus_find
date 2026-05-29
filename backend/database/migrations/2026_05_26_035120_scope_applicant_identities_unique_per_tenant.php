<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scope la unicidad de applicant_identities por tenant.
 *
 * Antes: índice global `(type, identifier)` impedía que un mismo teléfono
 * existiera en dos tenants. En un SaaS multi-SOFOM eso es incorrecto: un
 * cliente puede solicitar crédito con distintas marcas, cada una con su
 * propia cuenta.
 *
 * Agregamos `tenant_id` denormalizado (backfill desde applicant_accounts)
 * y reescribimos el índice como `(tenant_id, type, identifier)`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE applicant_identities DROP CONSTRAINT IF EXISTS applicant_identities_type_identifier_unique');

        if (!Schema::hasColumn('applicant_identities', 'tenant_id')) {
            Schema::table('applicant_identities', function ($table) {
                $table->uuid('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        DB::statement(<<<'SQL'
            UPDATE applicant_identities ai
            SET tenant_id = aa.tenant_id
            FROM applicant_accounts aa
            WHERE ai.account_id = aa.id
              AND ai.tenant_id IS NULL
        SQL);

        DB::statement('ALTER TABLE applicant_identities ALTER COLUMN tenant_id SET NOT NULL');
        DB::statement('CREATE UNIQUE INDEX applicant_identities_tenant_type_identifier_unique ON applicant_identities (tenant_id, type, identifier)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS applicant_identities_tenant_type_identifier_unique');
        DB::statement('CREATE UNIQUE INDEX applicant_identities_type_identifier_unique ON applicant_identities (type, identifier)');

        if (Schema::hasColumn('applicant_identities', 'tenant_id')) {
            Schema::table('applicant_identities', function ($table) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
