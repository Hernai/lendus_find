<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('application_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Note Content
            $table->text('content');
            $table->boolean('is_internal')->default(true); // Internal vs visible to applicant

            // Type
            $table->string('type')->default('NOTE'); // NOTE, STATUS_CHANGE, CALL, EMAIL, etc.

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('application_id')->references('id')->on('applications')->onDelete('cascade');

            $table->index(['application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_notes');
    }
};
