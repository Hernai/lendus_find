<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Rename V2 tables to their final names without the V2 suffix.
 *
 * Now that V1 tables have been dropped, we can use the clean names:
 * - applications_v2 → applications
 * - documents_v2 → documents
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename applications_v2 to applications
        Schema::rename('applications_v2', 'applications');

        // Rename documents_v2 to documents
        Schema::rename('documents_v2', 'documents');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename back to V2 names
        Schema::rename('applications', 'applications_v2');
        Schema::rename('documents', 'documents_v2');
    }
};
