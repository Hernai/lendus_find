<?php

namespace Tests\Feature\Auth;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for AuthController.
 *
 * These tests validate the complete authentication flows including
 * OTP-based login, PIN authentication, and staff password login.
 */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    /**
     * Get phone number in proper format (10 digits without +52).
     */
    private function getPhoneNumber(User $user): string
    {
        // Remove +52 prefix if present
        return preg_replace('/^\+52/', '', $user->phone);
    }

    // ==================== OTP Request Tests ====================

    /** @test */
    public function it_can_request_otp_with_valid_phone(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/auth/otp/request', [
                'phone' => '5512345678',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'channel' => 'SMS',
            ]);
    }

    /** @test */
    public function it_can_request_otp_with_email(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/auth/otp/request', [
                'email' => 'test@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'channel' => 'EMAIL',
            ]);
    }

    /** @test */
    public function it_can_request_otp_via_whatsapp_channel(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/auth/otp/request', [
                'phone' => '5512345678',
                'channel' => 'WHATSAPP',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'channel' => 'WHATSAPP',
            ]);
    }

    /** @test */
    public function it_rejects_invalid_phone_format(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/auth/otp/request', [
                'phone' => '123', // Invalid: not 10 digits
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    /** @test */
    public function it_rejects_invalid_email_format(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/auth/otp/request', [
                'email' => 'not-an-email',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function it_requires_either_phone_or_email(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/auth/otp/request', []);

        $response->assertStatus(422);
    }

    // ==================== OTP Verification Tests ====================

    /** @test */
    public function it_can_verify_valid_otp_code(): void
    {
        $otp = OtpCode::generate(
            destination: '5512345678',
            channel: 'SMS',
            purpose: 'LOGIN',
            tenantId: $this->tenant->id
        );

        $response = $this->withTenant()
            ->postJson('/api/auth/otp/verify', [
                'phone' => '5512345678',
                'code' => $otp->code,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'token',
                'user' => ['id', 'phone', 'type'],
            ]);
    }

    /** @test */
    public function it_creates_new_user_on_first_otp_verification(): void
    {
        $otp = OtpCode::generate(
            destination: '5599887766',
            channel: 'SMS',
            purpose: 'LOGIN',
            tenantId: $this->tenant->id
        );

        $this->assertDatabaseMissing('users', [
            'phone' => '5599887766',
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->withTenant()
            ->postJson('/api/auth/otp/verify', [
                'phone' => '5599887766',
                'code' => $otp->code,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'phone' => '5599887766',
            'tenant_id' => $this->tenant->id,
            'type' => 'APPLICANT',
        ]);
    }

    /** @test */
    public function it_rejects_invalid_otp_code(): void
    {
        OtpCode::generate(
            destination: '5512345678',
            channel: 'SMS',
            purpose: 'LOGIN',
            tenantId: $this->tenant->id
        );

        $response = $this->withTenant()
            ->postJson('/api/auth/otp/verify', [
                'phone' => '5512345678',
                'code' => '000000', // Wrong code
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'INVALID_CODE',
            ]);
    }

    /** @test */
    public function it_rejects_expired_otp_code(): void
    {
        $otp = OtpCode::generate(
            destination: '5512345678',
            channel: 'SMS',
            purpose: 'LOGIN',
            tenantId: $this->tenant->id
        );

        // Expire the OTP
        $otp->update(['expires_at' => now()->subMinutes(10)]);

        $response = $this->withTenant()
            ->postJson('/api/auth/otp/verify', [
                'phone' => '5512345678',
                'code' => $otp->code,
            ]);

        $response->assertStatus(401);
    }

    // ==================== Check User Tests ====================

    /** @test */
    public function it_returns_user_status_for_existing_user(): void
    {
        $user = $this->setUpUser();
        $user->update(['phone' => '5512345678']); // Use 10-digit format
        $user->setPin('1234');

        $response = $this->withTenant()
            ->postJson('/api/auth/check-user', [
                'phone' => '5512345678',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'exists' => true,
                'has_pin' => true,
                'is_locked' => false,
            ]);
    }

    /** @test */
    public function it_returns_not_exists_for_unknown_phone(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/auth/check-user', [
                'phone' => '5500000000',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'exists' => false,
                'has_pin' => false,
            ]);
    }

    // ==================== PIN Setup Tests ====================

    /** @test */
    public function it_can_setup_pin_for_authenticated_user(): void
    {
        $user = $this->setUpUser();

        $response = $this->actingAs($user, 'sanctum')
            ->withTenant()
            ->postJson('/api/auth/pin/setup', [
                'pin' => '1234',
                'pin_confirmation' => '1234',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue($user->fresh()->hasPin());
    }

    /** @test */
    public function it_rejects_pin_setup_if_already_set(): void
    {
        $user = $this->setUpUser();
        $user->setPin('1234');

        $response = $this->actingAs($user, 'sanctum')
            ->withTenant()
            ->postJson('/api/auth/pin/setup', [
                'pin' => '5678',
                'pin_confirmation' => '5678',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'PIN_ALREADY_SET',
            ]);
    }

    /** @test */
    public function it_rejects_pin_if_confirmation_mismatch(): void
    {
        $user = $this->setUpUser();

        $response = $this->actingAs($user, 'sanctum')
            ->withTenant()
            ->postJson('/api/auth/pin/setup', [
                'pin' => '1234',
                'pin_confirmation' => '5678',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pin_confirmation']);
    }

    /** @test */
    public function it_rejects_pin_that_is_not_4_digits(): void
    {
        $user = $this->setUpUser();

        $response = $this->actingAs($user, 'sanctum')
            ->withTenant()
            ->postJson('/api/auth/pin/setup', [
                'pin' => '123', // Only 3 digits
                'pin_confirmation' => '123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pin']);
    }

    // ==================== PIN Login Tests ====================

    /** @test */
    public function it_can_login_with_valid_pin(): void
    {
        $user = $this->setUpUser();
        $user->update(['phone' => '5512345678']);
        $user->setPin('1234');

        $response = $this->withTenant()
            ->postJson('/api/auth/pin/login', [
                'phone' => '5512345678',
                'pin' => '1234',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'token',
                'user' => ['id', 'phone', 'type', 'has_pin'],
            ])
            ->assertJson([
                'success' => true,
                'user' => [
                    'has_pin' => true,
                ],
            ]);
    }

    /** @test */
    public function it_rejects_login_with_wrong_pin(): void
    {
        $user = $this->setUpUser();
        $user->update(['phone' => '5512345678']);
        $user->setPin('1234');

        $response = $this->withTenant()
            ->postJson('/api/auth/pin/login', [
                'phone' => '5512345678',
                'pin' => '0000', // Wrong PIN
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'INVALID_PIN',
            ]);
    }

    /** @test */
    public function it_requires_otp_for_user_without_pin(): void
    {
        $user = $this->setUpUser();
        $user->update(['phone' => '5512345678']);
        // No PIN set

        $response = $this->withTenant()
            ->postJson('/api/auth/pin/login', [
                'phone' => '5512345678',
                'pin' => '1234',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'NO_PIN_SET',
                'requires_otp' => true,
            ]);
    }

    /** @test */
    public function it_returns_404_for_unknown_phone_in_pin_login(): void
    {
        $response = $this->withTenant()
            ->postJson('/api/auth/pin/login', [
                'phone' => '5500000000',
                'pin' => '1234',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'USER_NOT_FOUND',
            ]);
    }

    // ==================== Admin/Staff Password Login Tests ====================

    /** @test */
    public function it_allows_admin_login_with_password(): void
    {
        $admin = $this->setUpAdmin();
        $admin->update([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'type' => 'ADMIN',
        ]);

        $response = $this->withTenant()
            ->postJson('/api/admin/auth/login', [
                'email' => 'admin@test.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'is_staff',
                    'permissions',
                ],
            ]);
    }

    /** @test */
    public function it_rejects_admin_login_for_non_staff_users(): void
    {
        $user = $this->setUpUser();
        $user->update([
            'email' => 'applicant@test.com',
            'password' => bcrypt('password123'),
            'type' => 'APPLICANT',
        ]);

        $response = $this->withTenant()
            ->postJson('/api/admin/auth/login', [
                'email' => 'applicant@test.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'UNAUTHORIZED',
            ]);
    }

    /** @test */
    public function it_rejects_admin_login_with_wrong_password(): void
    {
        $admin = $this->setUpAdmin();
        $admin->update([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'type' => 'ADMIN',
        ]);

        $response = $this->withTenant()
            ->postJson('/api/admin/auth/login', [
                'email' => 'admin@test.com',
                'password' => 'wrongpassword',
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'INVALID_CREDENTIALS',
            ]);
    }

    /** @test */
    public function it_rejects_admin_login_for_inactive_account(): void
    {
        $admin = $this->setUpAdmin();
        $admin->update([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'type' => 'ADMIN',
            'is_active' => false,
        ]);

        $response = $this->withTenant()
            ->postJson('/api/admin/auth/login', [
                'email' => 'admin@test.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'ACCOUNT_DISABLED',
            ]);
    }

    // ==================== PIN Change Tests ====================

    /** @test */
    public function it_can_change_pin_with_valid_current_pin(): void
    {
        $user = $this->setUpUser();
        $user->setPin('1234');

        $response = $this->actingAs($user, 'sanctum')
            ->withTenant()
            ->postJson('/api/auth/pin/change', [
                'current_pin' => '1234',
                'new_pin' => '5678',
                'new_pin_confirmation' => '5678',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue($user->fresh()->verifyPin('5678'));
    }

    /** @test */
    public function it_rejects_pin_change_with_wrong_current_pin(): void
    {
        $user = $this->setUpUser();
        $user->setPin('1234');

        $response = $this->actingAs($user, 'sanctum')
            ->withTenant()
            ->postJson('/api/auth/pin/change', [
                'current_pin' => '0000',
                'new_pin' => '5678',
                'new_pin_confirmation' => '5678',
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'INVALID_PIN',
            ]);
    }

    // ==================== Reset PIN with OTP Tests ====================

    /** @test */
    public function it_can_reset_pin_with_valid_otp(): void
    {
        $user = $this->setUpUser();
        $user->update(['phone' => '5512345678']);
        $user->setPin('1234');

        $otp = OtpCode::generate(
            destination: '5512345678',
            channel: 'SMS',
            purpose: 'LOGIN',
            tenantId: $this->tenant->id
        );

        $response = $this->withTenant()
            ->postJson('/api/auth/pin/reset', [
                'phone' => '5512345678',
                'code' => $otp->code,
                'new_pin' => '9999',
                'new_pin_confirmation' => '9999',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'token',
                'user',
            ]);

        $this->assertTrue($user->fresh()->verifyPin('9999'));
    }

    // ==================== Logout Tests ====================

    /** @test */
    public function it_can_logout_authenticated_user(): void
    {
        $user = $this->setUpUser();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->withTenant()
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Token should be deleted
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'test-token',
        ]);
    }

    // ==================== Tenant Isolation Tests ====================

    /** @test */
    public function it_isolates_users_by_tenant(): void
    {
        // Create user in first tenant
        $user = $this->setUpUser();
        $user->update(['phone' => '5512345678']);
        $user->setPin('1234');

        // Create second tenant
        $tenant2 = \App\Models\Tenant::factory()->create([
            'slug' => 'tenant-2',
            'is_active' => true,
        ]);

        // Try to login from second tenant - should not find user
        $response = $this->withHeader('X-Tenant-ID', $tenant2->slug)
            ->postJson('/api/auth/pin/login', [
                'phone' => '5512345678',
                'pin' => '1234',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'USER_NOT_FOUND',
            ]);
    }
}
