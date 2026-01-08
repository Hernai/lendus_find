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
        Schema::table('applicants', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->timestamp('identity_verified_at')->nullable()->after('kyc_verified_at');
            $table->uuid('identity_verified_by')->nullable()->after('identity_verified_at');

            // Add foreign key constraint
            $table->foreign('identity_verified_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropForeign(['identity_verified_by']);
            $table->dropColumn(['phone_verified_at', 'email_verified_at', 'identity_verified_at', 'identity_verified_by']);
        });
    }
};
