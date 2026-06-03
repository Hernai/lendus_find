<?php

namespace Database\Seeders;

use App\Models\StaffAccount;
use App\Models\StaffProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder del SUPER_ADMIN global.
 *
 * Crea (o promueve) la cuenta `superadmin@lendus.mx` como SUPER_ADMIN sin
 * tenant_id. Es la única cuenta con acceso a TODOS los tenants — la elige
 * desde el modal/dropdown del backoffice.
 *
 * Idempotente:
 *  - Si la cuenta no existe → la crea con tenant_id=NULL, role=SUPER_ADMIN
 *  - Si la cuenta existe con otro tenant → la promueve (tenant_id=NULL,
 *    role=SUPER_ADMIN)
 *  - Si ya está como super admin global → no hace nada
 *
 * Además limpia las cuentas legacy `superadmin@moneycapital.mx` y
 * `superadmin@finatea.mx` (que eran super admin per-tenant): las degrada
 * a ADMIN del tenant correspondiente para no perder el acceso operativo.
 */
class GlobalSuperAdminSeeder extends Seeder
{
    private const GLOBAL_EMAIL = 'superadmin@lendus.mx';

    public function run(): void
    {
        $this->createOrPromoteGlobalSuperAdmin();
        $this->demoteLegacyPerTenantSuperAdmins();

        $this->command->info('✓ Super admin global: ' . self::GLOBAL_EMAIL . ' (password: password)');
    }

    private function createOrPromoteGlobalSuperAdmin(): void
    {
        $account = StaffAccount::withoutGlobalScope('tenant')
            ->where('email', self::GLOBAL_EMAIL)
            ->first();

        if ($account) {
            // Promueve a global si no lo es ya.
            if ($account->tenant_id !== null || $account->role !== StaffAccount::ROLE_SUPER_ADMIN) {
                $account->update([
                    'tenant_id' => null,
                    'role' => StaffAccount::ROLE_SUPER_ADMIN,
                    'is_active' => true,
                ]);
            }
            return;
        }

        $account = StaffAccount::create([
            'tenant_id' => null,
            'email' => self::GLOBAL_EMAIL,
            'password' => Hash::make('password'),
            'role' => StaffAccount::ROLE_SUPER_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        StaffProfile::firstOrCreate(
            ['account_id' => $account->id],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'phone' => '5500000000',
                'title' => 'Super Administrador Global',
            ],
        );
    }

    private function demoteLegacyPerTenantSuperAdmins(): void
    {
        $legacyEmails = ['superadmin@moneycapital.mx', 'superadmin@finatea.mx'];

        StaffAccount::withoutGlobalScope('tenant')
            ->whereIn('email', $legacyEmails)
            ->where('role', StaffAccount::ROLE_SUPER_ADMIN)
            ->update(['role' => StaffAccount::ROLE_ADMIN]);
    }
}
