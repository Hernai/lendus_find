<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos del motor de decisión en solicitudes:
 * - renewal_of_loan_id: la solicitud fue auto-creada por el motor al liquidarse
 *   ese préstamo (renovación, sin re-onboarding).
 * - cooldown_waived_*: exención auditada del cooldown post-rechazo sobre la
 *   solicitud rechazada (solo staff con permiso de aprobar/rechazar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->uuid('renewal_of_loan_id')->nullable()->after('metadata');
            $table->timestamp('cooldown_waived_at')->nullable()->after('renewal_of_loan_id');
            $table->uuid('cooldown_waived_by')->nullable()->after('cooldown_waived_at');
            $table->string('cooldown_waived_reason', 500)->nullable()->after('cooldown_waived_by');

            $table->index('renewal_of_loan_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['renewal_of_loan_id']);
            $table->dropColumn([
                'renewal_of_loan_id',
                'cooldown_waived_at',
                'cooldown_waived_by',
                'cooldown_waived_reason',
            ]);
        });
    }
};
