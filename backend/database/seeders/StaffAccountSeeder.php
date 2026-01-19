<?php

namespace Database\Seeders;

use App\Models\StaffAccount;
use App\Models\StaffProfile;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder for staff_accounts table.
 *
 * Creates staff users for the V2 authentication system that uses
 * StaffAccount model instead of User model.
 */
class StaffAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get demo tenant
        $tenant = Tenant::where('slug', 'demo')->first();

        if (!$tenant) {
            $this->command->error('Demo tenant not found. Run DemoTenantSeeder first.');
            return;
        }

        $this->command->info('Creating staff accounts for tenant: ' . $tenant->name);

        // Define staff users to create
        $staffUsers = [
            [
                'email' => 'superadmin@lendus.mx',
                'role' => StaffAccount::ROLE_SUPER_ADMIN,
                'profile' => [
                    'first_name' => 'Super',
                    'last_name' => 'Admin',
                    'phone' => '5500000000',
                    'title' => 'Super Administrador',
                ],
            ],
            [
                'email' => 'admin@lendus.mx',
                'role' => StaffAccount::ROLE_ADMIN,
                'profile' => [
                    'first_name' => 'Admin',
                    'last_name' => 'Demo',
                    'phone' => '5500000001',
                    'title' => 'Administrador',
                ],
            ],
            [
                'email' => 'carlos.ramirez@lendus.mx',
                'role' => StaffAccount::ROLE_SUPERVISOR,
                'profile' => [
                    'first_name' => 'Carlos',
                    'last_name' => 'Ramirez',
                    'last_name_2' => 'Lopez',
                    'phone' => '5500000002',
                    'title' => 'Supervisor de Credito',
                ],
            ],
            [
                'email' => 'patricia.moreno@lendus.mx',
                'role' => StaffAccount::ROLE_ANALYST,
                'profile' => [
                    'first_name' => 'Patricia',
                    'last_name' => 'Moreno',
                    'last_name_2' => 'Garcia',
                    'phone' => '5500000003',
                    'title' => 'Analista de Credito',
                ],
            ],
            [
                'email' => 'roberto.hernandez@lendus.mx',
                'role' => StaffAccount::ROLE_ANALYST,
                'profile' => [
                    'first_name' => 'Roberto',
                    'last_name' => 'Hernandez',
                    'last_name_2' => 'Martinez',
                    'phone' => '5500000004',
                    'title' => 'Analista de Credito Jr',
                ],
            ],
        ];

        foreach ($staffUsers as $userData) {
            // Check if account already exists
            $existingAccount = StaffAccount::where('email', $userData['email'])
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($existingAccount) {
                $this->command->info("  Skipping {$userData['email']} (already exists)");
                continue;
            }

            // Create staff account
            $account = StaffAccount::create([
                'tenant_id' => $tenant->id,
                'email' => $userData['email'],
                'password' => Hash::make('password'),
                'role' => $userData['role'],
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            // Create staff profile
            StaffProfile::create(array_merge(
                ['account_id' => $account->id],
                $userData['profile']
            ));

            $this->command->info("  Created: {$userData['email']} ({$userData['role']})");
        }

        $this->command->newLine();
        $this->command->info('Staff accounts created successfully!');
        $this->command->newLine();
        $this->command->info('Login credentials (all use password: "password"):');
        $this->command->table(
            ['Role', 'Email'],
            [
                ['Super Admin', 'superadmin@lendus.mx'],
                ['Admin', 'admin@lendus.mx'],
                ['Supervisor', 'carlos.ramirez@lendus.mx'],
                ['Analyst', 'patricia.moreno@lendus.mx'],
                ['Analyst', 'roberto.hernandez@lendus.mx'],
            ]
        );
    }
}
