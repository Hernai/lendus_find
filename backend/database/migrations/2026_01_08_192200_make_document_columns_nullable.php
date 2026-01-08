<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Make original columns nullable since code uses different column names
            $table->string('name')->nullable()->change();
            $table->string('file_path')->nullable()->change();
            $table->string('file_name')->nullable()->change();
            $table->integer('file_size')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('file_path')->nullable(false)->change();
            $table->string('file_name')->nullable(false)->change();
            $table->integer('file_size')->nullable(false)->change();
        });
    }
};
