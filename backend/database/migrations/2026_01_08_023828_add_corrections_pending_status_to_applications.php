<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For PostgreSQL: Recreate the status column with the new enum value
        DB::statement("
            ALTER TABLE applications
            DROP CONSTRAINT applications_status_check
        ");

        DB::statement("
            ALTER TABLE applications
            ADD CONSTRAINT applications_status_check
            CHECK (status IN (
                'DRAFT',
                'SUBMITTED',
                'IN_REVIEW',
                'DOCS_PENDING',
                'CORRECTIONS_PENDING',
                'APPROVED',
                'REJECTED',
                'CANCELLED',
                'DISBURSED',
                'ACTIVE',
                'COMPLETED',
                'DEFAULT'
            ))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove CORRECTIONS_PENDING from the constraint
        DB::statement("
            ALTER TABLE applications
            DROP CONSTRAINT applications_status_check
        ");

        DB::statement("
            ALTER TABLE applications
            ADD CONSTRAINT applications_status_check
            CHECK (status IN (
                'DRAFT',
                'SUBMITTED',
                'IN_REVIEW',
                'DOCS_PENDING',
                'APPROVED',
                'REJECTED',
                'CANCELLED',
                'DISBURSED',
                'ACTIVE',
                'COMPLETED',
                'DEFAULT'
            ))
        ");
    }
};
