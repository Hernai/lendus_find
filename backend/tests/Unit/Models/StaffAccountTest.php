<?php

namespace Tests\Unit\Models;

use App\Models\StaffAccount;
use App\Models\StaffProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for StaffAccount model.
 *
 * Tests the new staff authentication model that separates
 * staff users from applicants.
 */
class StaffAccountTest extends TestCase
{
    use RefreshDatabase;

    protected ?Tenant $testTenant = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testTenant = Tenant::factory()->create();
    }

    // =====================================================
    // Model Creation Tests
    // =====================================================

    public function test_can_create_staff_account(): void
    {
        $account = StaffAccount::factory()->create([
            'tenant_id' => $this->testTenant->id,
            'email' => 'test@example.com',
            'role' => 'ADMIN',
        ]);

        $this->assertDatabaseHas('staff_accounts', [
            'id' => $account->id,
            'email' => 'test@example.com',
            'role' => 'ADMIN',
            'tenant_id' => $this->testTenant->id,
        ]);
    }

    public function test_staff_account_can_have_profile(): void
    {
        // Create account without auto-profile
        $account = StaffAccount::create([
            'tenant_id' => $this->testTenant->id,
            'email' => 'profile-test@example.com',
            'password' => 'password',
            'role' => 'ANALYST',
            'is_active' => true,
        ]);

        // Verify no profile initially
        $this->assertNull($account->profile);

        // Create profile
        $profile = StaffProfile::create([
            'account_id' => $account->id,
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $account->refresh();

        $this->assertInstanceOf(StaffProfile::class, $account->profile);
        $this->assertEquals($account->id, $account->profile->account_id);
        $this->assertEquals('Test User', $account->profile->full_name);
    }

    public function test_staff_account_belongs_to_tenant(): void
    {
        $account = StaffAccount::factory()->create([
            'tenant_id' => $this->testTenant->id,
        ]);

        $this->assertInstanceOf(Tenant::class, $account->tenant);
        $this->assertEquals($this->testTenant->id, $account->tenant->id);
    }

    // =====================================================
    // Role Check Tests
    // =====================================================

    public function test_analyst_role_checks(): void
    {
        $account = new StaffAccount(['role' => 'ANALYST']);

        $this->assertTrue($account->isAnalyst());
        $this->assertFalse($account->isSupervisor());
        $this->assertFalse($account->isAdmin());
        $this->assertFalse($account->isSuperAdmin());
        $this->assertTrue($account->isStaff());
        $this->assertTrue($account->isAtLeastAnalyst());
        $this->assertFalse($account->isSupervisorOrAbove());
    }

    public function test_supervisor_role_checks(): void
    {
        $account = new StaffAccount(['role' => 'SUPERVISOR']);

        $this->assertFalse($account->isAnalyst());
        $this->assertTrue($account->isSupervisor());
        $this->assertFalse($account->isAdmin());
        $this->assertFalse($account->isSuperAdmin());
        $this->assertTrue($account->isStaff());
        $this->assertTrue($account->isAtLeastAnalyst());
        $this->assertTrue($account->isSupervisorOrAbove());
    }

    public function test_admin_role_checks(): void
    {
        $account = new StaffAccount(['role' => 'ADMIN']);

        $this->assertFalse($account->isAnalyst());
        $this->assertFalse($account->isSupervisor());
        $this->assertTrue($account->isAdmin());
        $this->assertFalse($account->isSuperAdmin());
        $this->assertTrue($account->isStaff());
        $this->assertTrue($account->isAtLeastAnalyst());
        $this->assertTrue($account->isSupervisorOrAbove());
    }

    public function test_super_admin_role_checks(): void
    {
        $account = new StaffAccount(['role' => 'SUPER_ADMIN']);

        $this->assertFalse($account->isAnalyst());
        $this->assertFalse($account->isSupervisor());
        $this->assertTrue($account->isAdmin()); // Super admin IS an admin
        $this->assertTrue($account->isSuperAdmin());
        $this->assertTrue($account->isStaff());
        $this->assertTrue($account->isAtLeastAnalyst());
        $this->assertTrue($account->isSupervisorOrAbove());
    }

    // =====================================================
    // Permission Tests
    // =====================================================

    public function test_analyst_permissions(): void
    {
        $account = new StaffAccount(['role' => 'ANALYST']);

        // Analysts CAN:
        $this->assertTrue($account->canReviewDocuments());
        $this->assertTrue($account->canVerifyReferences());
        $this->assertTrue($account->canChangeApplicationStatus());
        $this->assertTrue($account->canViewReports());

        // Analysts CANNOT:
        $this->assertFalse($account->canViewAllApplications());
        $this->assertFalse($account->canApproveRejectApplications());
        $this->assertFalse($account->canAssignApplications());
        $this->assertFalse($account->canManageProducts());
        $this->assertFalse($account->canManageUsers());
        $this->assertFalse($account->canConfigureTenant());
    }

    public function test_supervisor_permissions(): void
    {
        $account = new StaffAccount(['role' => 'SUPERVISOR']);

        // Supervisors CAN (all analyst permissions plus):
        $this->assertTrue($account->canReviewDocuments());
        $this->assertTrue($account->canVerifyReferences());
        $this->assertTrue($account->canChangeApplicationStatus());
        $this->assertTrue($account->canViewReports());
        $this->assertTrue($account->canViewAllApplications());
        $this->assertTrue($account->canApproveRejectApplications());
        $this->assertTrue($account->canAssignApplications());

        // Supervisors CANNOT:
        $this->assertFalse($account->canManageProducts());
        $this->assertFalse($account->canManageUsers());
        $this->assertFalse($account->canConfigureTenant());
    }

    public function test_admin_permissions(): void
    {
        $account = new StaffAccount(['role' => 'ADMIN']);

        // Admins CAN (all supervisor permissions plus):
        $this->assertTrue($account->canReviewDocuments());
        $this->assertTrue($account->canVerifyReferences());
        $this->assertTrue($account->canChangeApplicationStatus());
        $this->assertTrue($account->canViewReports());
        $this->assertTrue($account->canViewAllApplications());
        $this->assertTrue($account->canApproveRejectApplications());
        $this->assertTrue($account->canAssignApplications());
        $this->assertTrue($account->canManageProducts());
        $this->assertTrue($account->canManageUsers());

        // Admins CANNOT:
        $this->assertFalse($account->canConfigureTenant());
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $account = new StaffAccount(['role' => 'SUPER_ADMIN']);

        $this->assertTrue($account->canReviewDocuments());
        $this->assertTrue($account->canVerifyReferences());
        $this->assertTrue($account->canChangeApplicationStatus());
        $this->assertTrue($account->canViewReports());
        $this->assertTrue($account->canViewAllApplications());
        $this->assertTrue($account->canApproveRejectApplications());
        $this->assertTrue($account->canAssignApplications());
        $this->assertTrue($account->canManageProducts());
        $this->assertTrue($account->canManageUsers());
        $this->assertTrue($account->canConfigureTenant());
    }

    // =====================================================
    // Permissions Array Test
    // =====================================================

    public function test_get_permissions_array(): void
    {
        $account = new StaffAccount(['role' => 'ADMIN']);
        $permissions = $account->getPermissionsArray();

        $this->assertIsArray($permissions);
        $this->assertArrayHasKey('canViewAllApplications', $permissions);
        $this->assertArrayHasKey('canReviewDocuments', $permissions);
        $this->assertArrayHasKey('canVerifyReferences', $permissions);
        $this->assertArrayHasKey('canChangeApplicationStatus', $permissions);
        $this->assertArrayHasKey('canApproveRejectApplications', $permissions);
        $this->assertArrayHasKey('canAssignApplications', $permissions);
        $this->assertArrayHasKey('canManageProducts', $permissions);
        $this->assertArrayHasKey('canManageUsers', $permissions);
        $this->assertArrayHasKey('canViewReports', $permissions);
        $this->assertArrayHasKey('canConfigureTenant', $permissions);

        $this->assertTrue($permissions['canManageProducts']);
        $this->assertFalse($permissions['canConfigureTenant']);
    }

    // =====================================================
    // Utility Method Tests
    // =====================================================

    public function test_record_login_updates_timestamp_and_ip(): void
    {
        $account = StaffAccount::factory()->create([
            'tenant_id' => $this->testTenant->id,
            'last_login_at' => null,
            'last_login_ip' => null,
        ]);

        $account->recordLogin();
        $account->refresh();

        $this->assertNotNull($account->last_login_at);
        $this->assertNotNull($account->last_login_ip);
    }

    // =====================================================
    // Scope Tests
    // =====================================================

    public function test_active_scope(): void
    {
        StaffAccount::factory()->count(3)->create([
            'tenant_id' => $this->testTenant->id,
            'is_active' => true,
        ]);

        StaffAccount::factory()->count(2)->create([
            'tenant_id' => $this->testTenant->id,
            'is_active' => false,
        ]);

        $activeCount = StaffAccount::active()->count();
        $this->assertEquals(3, $activeCount);
    }

    public function test_of_role_scope(): void
    {
        StaffAccount::factory()->count(2)->create([
            'tenant_id' => $this->testTenant->id,
            'role' => 'ANALYST',
        ]);

        StaffAccount::factory()->count(1)->create([
            'tenant_id' => $this->testTenant->id,
            'role' => 'ADMIN',
        ]);

        $analystCount = StaffAccount::ofRole('ANALYST')->count();
        $adminCount = StaffAccount::ofRole('ADMIN')->count();

        $this->assertEquals(2, $analystCount);
        $this->assertEquals(1, $adminCount);
    }

    public function test_for_tenant_scope(): void
    {
        $tenant2 = Tenant::factory()->create();

        StaffAccount::factory()->count(3)->create([
            'tenant_id' => $this->testTenant->id,
        ]);

        StaffAccount::factory()->count(2)->create([
            'tenant_id' => $tenant2->id,
        ]);

        $tenant1Count = StaffAccount::forTenant($this->testTenant->id)->count();
        $tenant2Count = StaffAccount::forTenant($tenant2->id)->count();

        $this->assertEquals(3, $tenant1Count);
        $this->assertEquals(2, $tenant2Count);
    }

    // =====================================================
    // Factory State Tests
    // =====================================================

    public function test_factory_analyst_state(): void
    {
        $account = StaffAccount::factory()->analyst()->create([
            'tenant_id' => $this->testTenant->id,
        ]);

        $this->assertEquals('ANALYST', $account->role);
    }

    public function test_factory_supervisor_state(): void
    {
        $account = StaffAccount::factory()->supervisor()->create([
            'tenant_id' => $this->testTenant->id,
        ]);

        $this->assertEquals('SUPERVISOR', $account->role);
    }

    public function test_factory_admin_state(): void
    {
        $account = StaffAccount::factory()->admin()->create([
            'tenant_id' => $this->testTenant->id,
        ]);

        $this->assertEquals('ADMIN', $account->role);
    }

    public function test_factory_super_admin_state(): void
    {
        $account = StaffAccount::factory()->superAdmin()->create([
            'tenant_id' => $this->testTenant->id,
        ]);

        $this->assertEquals('SUPER_ADMIN', $account->role);
    }

    public function test_factory_inactive_state(): void
    {
        $account = StaffAccount::factory()->inactive()->create([
            'tenant_id' => $this->testTenant->id,
        ]);

        $this->assertFalse($account->is_active);
    }
}
