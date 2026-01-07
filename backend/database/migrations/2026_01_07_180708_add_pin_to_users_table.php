<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash')->nullable()->after('password');
            $table->timestamp('pin_set_at')->nullable()->after('pin_hash');
            $table->tinyInteger('pin_attempts')->default(0)->after('pin_set_at');
            $table->timestamp('pin_locked_until')->nullable()->after('pin_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'pin_set_at', 'pin_attempts', 'pin_locked_until']);
        });
    }
};
