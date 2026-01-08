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
        Schema::table('data_verifications', function (Blueprint $table) {
            // Add rejection reason for when data is rejected
            $table->string('rejection_reason', 500)->nullable()->after('notes');

            // Add status field - a field can be verified AND rejected (needs correction)
            // PENDING = not yet reviewed
            // VERIFIED = data confirmed correct
            // REJECTED = data incorrect, needs correction
            // CORRECTED = was rejected, user submitted correction (back to pending)
            $table->string('status', 20)->default('PENDING')->after('rejection_reason');

            // Track when correction was requested and when user corrected
            $table->timestamp('rejected_at')->nullable()->after('status');
            $table->timestamp('corrected_at')->nullable()->after('rejected_at');

            // Index for querying rejected fields
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_verifications', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['rejection_reason', 'status', 'rejected_at', 'corrected_at']);
        });
    }
};
