<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_branding', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->onDelete('cascade');

            // Colors
            $table->string('primary_color', 7)->default('#6366f1');
            $table->string('secondary_color', 7)->default('#10b981');
            $table->string('accent_color', 7)->default('#f59e0b');
            $table->string('background_color', 7)->default('#ffffff');
            $table->string('text_color', 7)->default('#1f2937');

            // Logos & Images
            $table->string('logo_url', 500)->nullable();
            $table->string('logo_dark_url', 500)->nullable();
            $table->string('favicon_url', 500)->nullable();
            $table->string('login_background_url', 500)->nullable();

            // Typography
            $table->string('font_family', 100)->default('Inter, sans-serif');
            $table->string('heading_font_family', 100)->nullable();

            // UI Style
            $table->string('border_radius', 20)->default('12px');
            $table->enum('button_style', ['rounded', 'pill', 'square'])->default('rounded');

            // Custom CSS (advanced)
            $table->text('custom_css')->nullable();

            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_branding');
    }
};
