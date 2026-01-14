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
            // Add created_by, updated_by (UUID references to users table)
            if (!Schema::hasColumn('users', 'created_by')) {
                $table->uuid('created_by')->nullable()->after('created_at');
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'updated_by')) {
                $table->uuid('updated_by')->nullable()->after('updated_at');
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            }

            // Only add deleted_by if table has soft deletes (deleted_at column)
            if (Schema::hasColumn('users', 'deleted_at') && !Schema::hasColumn('users', 'deleted_by')) {
                $table->uuid('deleted_by')->nullable()->after('deleted_at');
                $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('users', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }
            if (Schema::hasColumn('users', 'deleted_by')) {
                $table->dropForeign(['deleted_by']);
                $table->dropColumn('deleted_by');
            }
        });
    }
};
