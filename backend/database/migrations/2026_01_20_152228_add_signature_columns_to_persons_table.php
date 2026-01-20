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
        Schema::table('persons', function (Blueprint $table) {
            $table->text('signature_base64')->nullable()->after('kyc_data');
            $table->timestamp('signature_date')->nullable()->after('signature_base64');
            $table->string('signature_ip', 45)->nullable()->after('signature_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn(['signature_base64', 'signature_date', 'signature_ip']);
        });
    }
};
