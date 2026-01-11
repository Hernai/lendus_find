<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Change logo columns from VARCHAR(500) to TEXT to support base64 encoded images.
     */
    public function up(): void
    {
        Schema::table('tenant_branding', function (Blueprint $table) {
            $table->text('logo_url')->nullable()->change();
            $table->text('logo_dark_url')->nullable()->change();
            $table->text('favicon_url')->nullable()->change();
            $table->text('login_background_url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_branding', function (Blueprint $table) {
            $table->string('logo_url', 500)->nullable()->change();
            $table->string('logo_dark_url', 500)->nullable()->change();
            $table->string('favicon_url', 500)->nullable()->change();
            $table->string('login_background_url', 500)->nullable()->change();
        });
    }
};
