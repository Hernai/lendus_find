<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, drop the existing CHECK constraint
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_type_check");

        // Update existing AGENT users to SUPERVISOR
        DB::table('users')->where('type', 'AGENT')->update(['type' => 'SUPERVISOR']);

        // Add new CHECK constraint with SUPERVISOR instead of AGENT
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_type_check CHECK (type::text = ANY (ARRAY['APPLICANT'::character varying, 'SUPERVISOR'::character varying, 'ANALYST'::character varying, 'ADMIN'::character varying, 'SUPER_ADMIN'::character varying]::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new constraint
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_type_check");

        // Revert SUPERVISOR back to AGENT
        DB::table('users')->where('type', 'SUPERVISOR')->update(['type' => 'AGENT']);

        // Re-add original CHECK constraint with AGENT
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_type_check CHECK (type::text = ANY (ARRAY['APPLICANT'::character varying, 'AGENT'::character varying, 'ANALYST'::character varying, 'ADMIN'::character varying, 'SUPER_ADMIN'::character varying]::text[]))");
    }
};
