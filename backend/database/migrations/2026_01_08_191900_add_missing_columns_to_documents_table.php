<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Add missing columns that the code expects
            if (!Schema::hasColumn('documents', 'original_name')) {
                $table->string('original_name')->nullable()->after('type');
            }
            if (!Schema::hasColumn('documents', 'storage_path')) {
                $table->string('storage_path')->nullable()->after('file_path');
            }
            if (!Schema::hasColumn('documents', 'size')) {
                $table->integer('size')->nullable()->after('file_size');
            }
            if (!Schema::hasColumn('documents', 'rejection_comment')) {
                $table->text('rejection_comment')->nullable()->after('rejection_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['original_name', 'storage_path', 'size', 'rejection_comment']);
        });
    }
};
