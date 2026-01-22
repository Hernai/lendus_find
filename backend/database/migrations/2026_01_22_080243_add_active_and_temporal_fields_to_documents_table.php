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
     * Adds Active Document Pattern and Temporal Validity fields to documents table.
     *
     * - is_active: Only one document per type per person can be active
     * - valid_from/valid_to: Temporal validity for CNBV compliance
     * - superseded_by_id: Audit chain for document replacements
     * - replaced_at: Timestamp when document was replaced (separate from valid_to)
     */
    public function up(): void
    {
        // Add columns
        if (!Schema::hasColumn('documents', 'is_active')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('status');
            });
        }

        if (!Schema::hasColumn('documents', 'valid_from')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->timestamp('valid_from')->nullable()->after('is_active');
            });
        }

        if (!Schema::hasColumn('documents', 'valid_to')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->timestamp('valid_to')->nullable()->after('valid_from');
            });
        }

        if (!Schema::hasColumn('documents', 'superseded_by_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->uuid('superseded_by_id')->nullable()->after('valid_to');
            });
        }

        // Add foreign key for supersession chain
        try {
            Schema::table('documents', function (Blueprint $table) {
                $table->foreign('superseded_by_id')
                    ->references('id')
                    ->on('documents')
                    ->nullOnDelete();
            });
        } catch (\Exception $e) {
            // Foreign key might already exist
        }

        // Add indexes
        try {
            Schema::table('documents', function (Blueprint $table) {
                $table->index(['documentable_type', 'documentable_id', 'type', 'is_active'], 'idx_active_documents');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }

        try {
            Schema::table('documents', function (Blueprint $table) {
                $table->index(['valid_from', 'valid_to'], 'idx_temporal_validity');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }

        try {
            Schema::table('documents', function (Blueprint $table) {
                $table->index('superseded_by_id', 'idx_supersession_chain');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }

        // Set valid_from for existing documents to their created_at date
        DB::statement('UPDATE documents SET valid_from = created_at WHERE valid_from IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes
        try {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropIndex('idx_active_documents');
            });
        } catch (\Exception $e) {
            // Index might not exist
        }

        try {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropIndex('idx_temporal_validity');
            });
        } catch (\Exception $e) {
            // Index might not exist
        }

        try {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropIndex('idx_supersession_chain');
            });
        } catch (\Exception $e) {
            // Index might not exist
        }

        // Drop foreign key
        try {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropForeign(['superseded_by_id']);
            });
        } catch (\Exception $e) {
            // Foreign key might not exist
        }

        // Drop columns
        if (Schema::hasColumn('documents', 'superseded_by_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('superseded_by_id');
            });
        }

        if (Schema::hasColumn('documents', 'valid_to')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('valid_to');
            });
        }

        if (Schema::hasColumn('documents', 'valid_from')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('valid_from');
            });
        }

        if (Schema::hasColumn('documents', 'is_active')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
