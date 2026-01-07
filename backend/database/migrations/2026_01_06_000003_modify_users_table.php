<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add UUID as primary (if not already)
            // Note: In a fresh install, you might want to recreate the table

            // Tenant relationship
            $table->uuid('tenant_id')->nullable()->after('id');

            // Contact info
            $table->string('phone', 15)->nullable()->unique()->after('email');

            // User type and role
            $table->enum('type', ['APPLICANT', 'ADMIN', 'AGENT', 'SUPER_ADMIN'])->default('APPLICANT')->after('phone');
            $table->string('role')->nullable()->after('type');

            // Profile
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('avatar_url')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            // Indexes
            $table->index('tenant_id');
            $table->index('type');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'tenant_id',
                'phone',
                'type',
                'role',
                'first_name',
                'last_name',
                'avatar_url',
                'is_active',
                'phone_verified_at',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
