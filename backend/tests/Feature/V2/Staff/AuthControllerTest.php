<?php

namespace Tests\Feature\V2\Staff;

use App\Models\StaffAccount;
use App\Models\StaffProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature tests for Staff Auth Controller v2.
 *
 * Tests the new staff authentication system using StaffAccount model.
 */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    // =====================================================
    // Login Tests
    // =====================================================

    public function test_staff_can_login_with_valid_credentials(): void
    {
        $account = StaffAccount::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
        ]);

        // Refresh to load profile relationship created by factory
        $account->refresh();

        // Update the profile with specific test data
        $account->profile->update([
            'first_name' => 'Test',
            'last_name' => 'Admin',
        ]);

        $response = $this->withTenant()
            ->postJson('/api/v2/staff/auth/login', [
                'email' => 'admin@test.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'token',
                'user' => [
                    'id',
                    'email',
                    'role',
                    'is_staff',
                    'is_active',
                    'profile' => [
                        'first_name',
                        'last_name',
                        'full_name',
                        'initials',
                    ],
                    'permissions',
                ],
            ])
            ->assertJson([
                'success' => true,
                'user' => [
                    'email' => 'admin@test.com',
                    'role' => 'ADMIN',
                    'is_staff' => true,
                ],
            ]);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        StaffAccount::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->withTenant()
            ->postJson('/api/v2/staff/auth/login', [
                'email' => 'admin@test.com',
                'password' => 'wrong-password',
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'INVALID_CREDENTIALS',
            ]);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/v2/staff/auth/login', [
                'email' => 'nonexistent@test.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'INVALID_CREDENTIALS',
            ]);
    }

    public function test_inactive_account_cannot_login(): void
    {
        StaffAccount::factory()->inactive()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'inactive@test.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->withTenant()
            ->postJson('/api/v2/staff/auth/login', [
                'email' => 'inactive@test.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'ACCOUNT_DISABLED',
            ]);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/v2/staff/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    // =====================================================
    // Me Endpoint Tests
    // =====================================================

    public function test_authenticated_staff_can_get_profile(): void
    {
        $account = StaffAccount::factory()->supervisor()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'supervisor@test.com',
            'password' => Hash::make('password123'),
        ]);

        // Refresh to load profile relationship created by factory
        $account->refresh();

        $account->profile->update([
            'first_name' => 'María',
            'last_name' => 'García',
            'last_name_2' => 'López',
        ]);

        $token = $account->createToken('test-token', ['staff'])->plainTextToken;

        $response = $this->withTenant()
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v2/staff/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'email',
                    'role',
                    'is_staff',
                    'profile',
                    'permissions',
                ],
            ])
            ->assertJson([
                'user' => [
                    'email' => 'supervisor@test.com',
                    'role' => 'SUPERVISOR',
                    'profile' => [
                        'first_name' => 'María',
                        'last_name' => 'García',
                        'last_name_2' => 'López',
                        'full_name' => 'María García López',
                        'initials' => 'MG',
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_request_to_me_fails(): void
    {
        $response = $this->withTenant()
            ->getJson('/api/v2/staff/auth/me');

        $response->assertStatus(401);
    }

    // =====================================================
    // Logout Tests
    // =====================================================

    public function test_staff_can_logout(): void
    {
        $account = StaffAccount::factory()->analyst()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $token = $account->createToken('test-token', ['staff'])->plainTextToken;

        $response = $this->withTenant()
            ->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v2/staff/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify token is invalidated
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $account->id,
            'tokenable_type' => StaffAccount::class,
        ]);
    }

    // =====================================================
    // Refresh Token Tests
    // =====================================================

    public function test_staff_can_refresh_token(): void
    {
        $account = StaffAccount::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $token = $account->createToken('test-token', ['staff'])->plainTextToken;

        $response = $this->withTenant()
            ->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v2/staff/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'token',
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verify new token is different from old one
        $newToken = $response->json('token');
        $this->assertNotEquals($token, $newToken);
    }

    // =====================================================
    // Tenant Isolation Tests
    // =====================================================

    public function test_staff_from_different_tenant_cannot_login(): void
    {
        $otherTenant = Tenant::factory()->create();

        StaffAccount::factory()->admin()->create([
            'tenant_id' => $otherTenant->id,
            'email' => 'other-tenant@test.com',
            'password' => Hash::make('password123'),
        ]);

        // Try to login with current tenant context
        $response = $this->withTenant()
            ->postJson('/api/v2/staff/auth/login', [
                'email' => 'other-tenant@test.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'INVALID_CREDENTIALS',
            ]);
    }

    // =====================================================
    // Permission Tests
    // =====================================================

    public function test_admin_has_correct_permissions(): void
    {
        $account = StaffAccount::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $token = $account->createToken('test-token', ['staff'])->plainTextToken;

        $response = $this->withTenant()
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v2/staff/auth/me');

        $response->assertStatus(200);

        $permissions = $response->json('user.permissions');

        // Admins can manage products and users
        $this->assertTrue($permissions['canManageProducts']);
        $this->assertTrue($permissions['canManageUsers']);
        $this->assertTrue($permissions['canApproveRejectApplications']);

        // Admins cannot configure tenant (only super admin)
        $this->assertFalse($permissions['canConfigureTenant']);
    }

    public function test_analyst_has_correct_permissions(): void
    {
        $account = StaffAccount::factory()->analyst()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $token = $account->createToken('test-token', ['staff'])->plainTextToken;

        $response = $this->withTenant()
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v2/staff/auth/me');

        $response->assertStatus(200);

        $permissions = $response->json('user.permissions');

        // Analysts can review documents and references
        $this->assertTrue($permissions['canReviewDocuments']);
        $this->assertTrue($permissions['canVerifyReferences']);
        $this->assertTrue($permissions['canChangeApplicationStatus']);

        // Analysts cannot approve/reject or manage
        $this->assertFalse($permissions['canApproveRejectApplications']);
        $this->assertFalse($permissions['canManageProducts']);
        $this->assertFalse($permissions['canManageUsers']);
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $account = StaffAccount::factory()->superAdmin()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $token = $account->createToken('test-token', ['staff'])->plainTextToken;

        $response = $this->withTenant()
            ->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v2/staff/auth/me');

        $response->assertStatus(200);

        $permissions = $response->json('user.permissions');

        // Super admin has all permissions
        $this->assertTrue($permissions['canViewAllApplications']);
        $this->assertTrue($permissions['canReviewDocuments']);
        $this->assertTrue($permissions['canVerifyReferences']);
        $this->assertTrue($permissions['canChangeApplicationStatus']);
        $this->assertTrue($permissions['canApproveRejectApplications']);
        $this->assertTrue($permissions['canAssignApplications']);
        $this->assertTrue($permissions['canManageProducts']);
        $this->assertTrue($permissions['canManageUsers']);
        $this->assertTrue($permissions['canViewReports']);
        $this->assertTrue($permissions['canConfigureTenant']);
    }

    // =====================================================
    // Login Records Test
    // =====================================================

    public function test_login_records_last_login_info(): void
    {
        $account = StaffAccount::factory()->admin()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'record-test@test.com',
            'password' => Hash::make('password123'),
            'last_login_at' => null,
            'last_login_ip' => null,
        ]);

        $this->assertNull($account->last_login_at);
        $this->assertNull($account->last_login_ip);

        $this->withTenant()
            ->postJson('/api/v2/staff/auth/login', [
                'email' => 'record-test@test.com',
                'password' => 'password123',
            ]);

        $account->refresh();

        $this->assertNotNull($account->last_login_at);
        $this->assertNotNull($account->last_login_ip);
    }
}
