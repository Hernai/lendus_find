<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename person-specific tables to generic entity polymorphic tables.
 *
 * - person_bank_accounts → bank_accounts (owner_type/owner_id → entity_type/entity_id)
 * - person_addresses → addresses (person_id → entity_type/entity_id)
 *
 * This allows both persons and companies to have bank accounts and addresses.
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================
        // 1. Rename person_bank_accounts to bank_accounts
        // =====================================================
        // Already has owner_type/owner_id, just rename columns to entity_type/entity_id
        if (Schema::hasTable('person_bank_accounts')) {
            // Rename columns first (while table still has old name)
            Schema::table('person_bank_accounts', function (Blueprint $table) {
                $table->renameColumn('owner_type', 'entity_type');
                $table->renameColumn('owner_id', 'entity_id');
            });

            // Rename table
            Schema::rename('person_bank_accounts', 'bank_accounts');

            // Update index names to match new table name
            // Note: PostgreSQL handles index renaming automatically with table rename in most cases
        }

        // =====================================================
        // 2. Rename person_addresses to addresses
        // =====================================================
        if (Schema::hasTable('person_addresses')) {
            // Add entity_type column, rename person_id to entity_id
            Schema::table('person_addresses', function (Blueprint $table) {
                // Add entity_type column
                $table->string('entity_type', 100)->default('persons')->after('tenant_id');
                // Rename person_id to entity_id
                $table->renameColumn('person_id', 'entity_id');
            });

            // Rename table
            Schema::rename('person_addresses', 'addresses');

            // Update the index to include entity_type
            Schema::table('addresses', function (Blueprint $table) {
                // Create new composite index for polymorphic lookup
                $table->index(['entity_type', 'entity_id', 'type', 'is_current'], 'addresses_entity_type_lookup');
            });
        }

        // =====================================================
        // 3. Update entity_type values for existing data
        // =====================================================
        // Bank accounts: update 'persons' to 'persons' (already correct)
        // Addresses: default is already 'persons'

        // Ensure consistency in entity_type values
        DB::table('bank_accounts')
            ->where('entity_type', 'persons')
            ->orWhere('entity_type', 'App\\Models\\Person')
            ->update(['entity_type' => 'persons']);
    }

    public function down(): void
    {
        // Reverse addresses
        if (Schema::hasTable('addresses')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropIndexIfExists('addresses_entity_type_lookup');
            });

            Schema::rename('addresses', 'person_addresses');

            Schema::table('person_addresses', function (Blueprint $table) {
                $table->dropColumn('entity_type');
                $table->renameColumn('entity_id', 'person_id');
            });
        }

        // Reverse bank_accounts
        if (Schema::hasTable('bank_accounts')) {
            Schema::rename('bank_accounts', 'person_bank_accounts');

            Schema::table('person_bank_accounts', function (Blueprint $table) {
                $table->renameColumn('entity_type', 'owner_type');
                $table->renameColumn('entity_id', 'owner_id');
            });
        }
    }
};
