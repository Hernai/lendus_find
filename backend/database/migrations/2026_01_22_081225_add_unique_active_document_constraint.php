<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds partial unique constraint to enforce Active Document Pattern:
     * Only ONE document per (documentable_type, documentable_id, type) can have is_active = true.
     *
     * This prevents data integrity issues where multiple "active" documents exist for the same type.
     */
    public function up(): void
    {
        // Step 1: Clean up existing data - deactivate duplicate active documents
        // Keep only the most recent one as active
        DB::statement("
            WITH ranked_docs AS (
                SELECT
                    id,
                    ROW_NUMBER() OVER (
                        PARTITION BY documentable_type, documentable_id, type
                        ORDER BY created_at DESC
                    ) as rn
                FROM documents
                WHERE is_active = true
            )
            UPDATE documents
            SET is_active = false, valid_to = NOW()
            WHERE id IN (
                SELECT id FROM ranked_docs WHERE rn > 1
            )
        ");

        // Step 2: Add partial unique index (only for active documents)
        // PostgreSQL supports partial indexes: WHERE is_active = true
        DB::statement("
            CREATE UNIQUE INDEX unique_active_document
            ON documents (documentable_type, documentable_id, type)
            WHERE is_active = true
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_active_document');
    }
};
