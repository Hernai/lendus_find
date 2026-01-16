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
     * Adds CORRECTIONS_PENDING status to applications table.
     * Only runs on PostgreSQL which supports CHECK constraints.
     * SQLite stores values as TEXT and doesn't enforce CHECK constraints.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // Only run constraint modifications on PostgreSQL
        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE applications
                DROP CONSTRAINT IF EXISTS applications_status_check
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
        // SQLite and MySQL don't enforce CHECK constraints the same way
        // The status validation is handled at the application level
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("
                ALTER TABLE applications
                DROP CONSTRAINT IF EXISTS applications_status_check
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
    }
};
