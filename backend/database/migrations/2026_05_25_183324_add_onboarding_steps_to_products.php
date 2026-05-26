<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `onboarding_steps` (JSONB) a `products`. Cada producto puede
 * declarar qué pantallas se le muestran al solicitante durante el flujo
 * de onboarding. Si es null, el frontend cae al pipeline legacy de 8
 * pasos. Si está poblado, monta `<OnboardingStepRenderer>` por cada
 * entrada del array.
 *
 * Estructura típica (cada elemento es un step):
 *   { "id": "education", "type": "select",
 *     "field": "education_level", "enum": "EducationLevel",
 *     "required": true }
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->jsonb('onboarding_steps')->nullable()->after('extra_fields');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('onboarding_steps');
        });
    }
};
