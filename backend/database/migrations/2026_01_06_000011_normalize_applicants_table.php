<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            // Personal Data - Extract from JSON to columns
            $table->string('first_name', 100)->nullable()->after('type');
            $table->string('last_name_1', 100)->nullable()->after('first_name');
            $table->string('last_name_2', 100)->nullable()->after('last_name_1');
            $table->string('full_name', 300)->nullable()->after('last_name_2');
            $table->date('birth_date')->nullable()->after('full_name');
            $table->char('gender', 1)->nullable()->after('birth_date'); // M, F, O
            $table->string('marital_status', 20)->nullable()->after('gender'); // SOLTERO, CASADO, DIVORCIADO, VIUDO, UNION_LIBRE
            $table->string('nationality', 50)->default('MEXICANA')->after('marital_status');
            $table->string('birth_state', 50)->nullable()->after('nationality');
            $table->string('birth_country', 50)->default('MEXICO')->after('birth_state');

            // Contact Info - Extract from JSON to columns
            $table->string('phone', 15)->nullable()->after('ine_clave');
            $table->string('phone_secondary', 15)->nullable()->after('phone');
            $table->string('email', 255)->nullable()->after('phone_secondary');

            // Education level (common field for scoring)
            $table->string('education_level', 50)->nullable()->after('email'); // PRIMARIA, SECUNDARIA, PREPARATORIA, LICENCIATURA, MAESTRIA, DOCTORADO

            // Dependents (common field for scoring)
            $table->tinyInteger('dependents_count')->default(0)->after('education_level');

            // Remove old JSON columns (keep for backward compatibility during migration)
            // We'll drop them in a separate migration after data migration
            // $table->dropColumn(['personal_data', 'contact_info']);

            // Indexes for common queries
            $table->index('phone');
            $table->index('email');
            $table->index('birth_date');
            $table->index(['tenant_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['email']);
            $table->dropIndex(['birth_date']);
            $table->dropIndex(['tenant_id', 'full_name']);

            $table->dropColumn([
                'first_name',
                'last_name_1',
                'last_name_2',
                'full_name',
                'birth_date',
                'gender',
                'marital_status',
                'nationality',
                'birth_state',
                'birth_country',
                'phone',
                'phone_secondary',
                'email',
                'education_level',
                'dependents_count',
            ]);
        });
    }
};
