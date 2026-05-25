<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de tokens de dispositivos para push notifications (FCM/APNs/WebPush).
 *
 * Cada registro vincula un token de notificación a un owner polimórfico
 * (ApplicantAccount o StaffAccount). Los tokens revocados (logout, token
 * inválido) se marcan con `revoked_at` para preservar historial.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('owner_type', 60);
            $table->uuid('owner_id');
            $table->string('provider', 16); // fcm | apns | webpush
            $table->text('token');
            $table->string('platform', 16); // ios | android | web
            $table->string('app_version', 32)->nullable();
            $table->string('device_id', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['owner_type', 'owner_id']);
            $table->index('tenant_id');
            $table->unique('token');
            $table->index(['provider', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
