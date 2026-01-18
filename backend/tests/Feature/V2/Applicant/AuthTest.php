<?php

namespace Tests\Feature\V2\Applicant;

use App\Models\ApplicantAccount;
use App\Models\ApplicantIdentity;
use App\Models\OtpRequest;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    // =====================================================
    // OTP Request Tests
    // =====================================================

    public function test_can_request_otp_for_phone(): void
    {
        $response = $this->postJson('/api/v2/applicant/auth/otp/request', [
            'type' => 'phone',
            'identifier' => '5512345678',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['expires_in', 'masked_target'],
            ]);
    }

    public function test_can_request_otp_for_email(): void
    {
        $response = $this->postJson('/api/v2/applicant/auth/otp/request', [
            'type' => 'email',
            'identifier' => 'test@example.com',
            'channel' => 'email',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_request_otp_validates_type(): void
    {
        $response = $this->postJson('/api/v2/applicant/auth/otp/request', [
            'type' => 'invalid',
            'identifier' => '5512345678',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_request_otp_requires_identifier(): void
    {
        $response = $this->postJson('/api/v2/applicant/auth/otp/request', [
            'type' => 'phone',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_request_otp_rate_limited(): void
    {
        // Create 3 recent OTP requests
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->recent()->count(3)->create();

        $response = $this->postJson('/api/v2/applicant/auth/otp/request', [
            'type' => 'phone',
            'identifier' => '5512345678',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'error' => 'RATE_LIMIT_EXCEEDED',
            ]);
    }

    // =====================================================
    // OTP Verification Tests
    // =====================================================

    public function test_can_verify_otp_and_create_new_account(): void
    {
        // Create a valid OTP
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->withCode('123456')->valid()->create();

        $response = $this->postJson('/api/v2/applicant/auth/otp/verify', [
            'type' => 'phone',
            'identifier' => '5512345678',
            'code' => '123456',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'is_new_user' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'token',
                'is_new_user',
                'user' => ['id', 'phone', 'has_pin', 'onboarding_step'],
            ]);

        // Verify account was created
        $this->assertDatabaseHas('applicant_accounts', [
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertDatabaseHas('applicant_identities', [
            'type' => 'PHONE',
            'identifier' => '5512345678',
            'is_primary' => true,
        ]);
    }

    public function test_can_verify_otp_for_existing_user(): void
    {
        // Create existing account
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
            'identifier' => '5512345678',
            'is_primary' => true,
        ]);

        // Create a valid OTP
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->withCode('123456')->valid()->create();

        $response = $this->postJson('/api/v2/applicant/auth/otp/verify', [
            'type' => 'phone',
            'identifier' => '5512345678',
            'code' => '123456',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'is_new_user' => false,
            ]);
    }

    public function test_verify_otp_fails_with_invalid_code(): void
    {
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->withCode('123456')->valid()->create();

        $response = $this->postJson('/api/v2/applicant/auth/otp/verify', [
            'type' => 'phone',
            'identifier' => '5512345678',
            'code' => '654321',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'INVALID_CODE',
            ]);
    }

    public function test_verify_otp_fails_with_expired_code(): void
    {
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->withCode('123456')->expired()->create();

        $response = $this->postJson('/api/v2/applicant/auth/otp/verify', [
            'type' => 'phone',
            'identifier' => '5512345678',
            'code' => '123456',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'OTP_NOT_FOUND',
            ]);
    }

    public function test_verify_otp_fails_with_too_many_attempts(): void
    {
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->withCode('123456')->tooManyAttempts()->create();

        $response = $this->postJson('/api/v2/applicant/auth/otp/verify', [
            'type' => 'phone',
            'identifier' => '5512345678',
            'code' => '123456',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'error' => 'TOO_MANY_ATTEMPTS',
            ]);
    }

    // =====================================================
    // PIN Login Tests
    // =====================================================

    public function test_can_login_with_pin(): void
    {
        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
            'identifier' => '5512345678',
            'is_primary' => true,
        ]);

        $response = $this->postJson('/api/v2/applicant/auth/pin/login', [
            'type' => 'phone',
            'identifier' => '5512345678',
            'pin' => '123456',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'token',
                'user',
            ]);
    }

    public function test_pin_login_fails_with_invalid_pin(): void
    {
        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
            'identifier' => '5512345678',
        ]);

        $response = $this->postJson('/api/v2/applicant/auth/pin/login', [
            'type' => 'phone',
            'identifier' => '5512345678',
            'pin' => '654321',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'INVALID_PIN',
            ]);
    }

    public function test_pin_login_fails_when_account_not_found(): void
    {
        $response = $this->postJson('/api/v2/applicant/auth/pin/login', [
            'type' => 'phone',
            'identifier' => '9999999999',
            'pin' => '123456',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'ACCOUNT_NOT_FOUND',
            ]);
    }

    public function test_pin_login_fails_when_no_pin_set(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
            'identifier' => '5512345678',
        ]);

        $response = $this->postJson('/api/v2/applicant/auth/pin/login', [
            'type' => 'phone',
            'identifier' => '5512345678',
            'pin' => '123456',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'PIN_NOT_SET',
            ]);
    }

    public function test_pin_login_fails_when_account_locked(): void
    {
        $account = ApplicantAccount::factory()
            
            ->pinLocked()
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
            'identifier' => '5512345678',
        ]);

        $response = $this->postJson('/api/v2/applicant/auth/pin/login', [
            'type' => 'phone',
            'identifier' => '5512345678',
            'pin' => '123456',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertStatus(423)
            ->assertJson([
                'error' => 'ACCOUNT_LOCKED',
            ]);
    }

    public function test_pin_login_fails_when_account_disabled(): void
    {
        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->inactive()
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
            'identifier' => '5512345678',
        ]);

        $response = $this->postJson('/api/v2/applicant/auth/pin/login', [
            'type' => 'phone',
            'identifier' => '5512345678',
            'pin' => '123456',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'ACCOUNT_DISABLED',
            ]);
    }

    // =====================================================
    // Check User Tests
    // =====================================================

    public function test_check_user_returns_exists_false_for_new_user(): void
    {
        $response = $this->postJson('/api/v2/applicant/auth/check-user', [
            'type' => 'phone',
            'identifier' => '9999999999',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertOk()
            ->assertJson([
                'exists' => false,
                'has_pin' => false,
                'is_locked' => false,
            ]);
    }

    public function test_check_user_returns_exists_true_for_existing_user(): void
    {
        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
            'identifier' => '5512345678',
        ]);

        $response = $this->postJson('/api/v2/applicant/auth/check-user', [
            'type' => 'phone',
            'identifier' => '5512345678',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertOk()
            ->assertJson([
                'exists' => true,
                'has_pin' => true,
                'is_locked' => false,
            ]);
    }

    // =====================================================
    // PIN Setup Tests
    // =====================================================

    public function test_can_setup_pin(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
        ]);

        $token = $account->createToken('test-token', ['applicant'])->plainTextToken;

        $response = $this->postJson('/api/v2/applicant/auth/pin/setup', [
            'pin' => '135790',
            'pin_confirmation' => '135790',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue($account->fresh()->hasPin());
    }

    public function test_setup_pin_fails_when_pin_already_set(): void
    {
        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->create(['tenant_id' => $this->tenant->id]);

        $token = $account->createToken('test-token', ['applicant'])->plainTextToken;

        $response = $this->postJson('/api/v2/applicant/auth/pin/setup', [
            'pin' => '654321',
            'pin_confirmation' => '654321',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'PIN_ALREADY_SET',
            ]);
    }

    public function test_setup_pin_fails_with_simple_pin(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        $token = $account->createToken('test-token', ['applicant'])->plainTextToken;

        $response = $this->postJson('/api/v2/applicant/auth/pin/setup', [
            'pin' => '111111',
            'pin_confirmation' => '111111',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'PIN_TOO_SIMPLE',
            ]);
    }

    public function test_setup_pin_requires_confirmation(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        $token = $account->createToken('test-token', ['applicant'])->plainTextToken;

        $response = $this->postJson('/api/v2/applicant/auth/pin/setup', [
            'pin' => '123456',
            'pin_confirmation' => '654321',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pin_confirmation']);
    }

    // =====================================================
    // PIN Change Tests
    // =====================================================

    public function test_can_change_pin(): void
    {
        $account = ApplicantAccount::factory()

            ->withPin('135790')
            ->create(['tenant_id' => $this->tenant->id]);

        $token = $account->createToken('test-token', ['applicant'])->plainTextToken;

        $response = $this->postJson('/api/v2/applicant/auth/pin/change', [
            'current_pin' => '135790',
            'new_pin' => '246813',
            'new_pin_confirmation' => '246813',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue($account->fresh()->verifyPin('246813'));
    }

    public function test_change_pin_fails_with_incorrect_current_pin(): void
    {
        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->create(['tenant_id' => $this->tenant->id]);

        $token = $account->createToken('test-token', ['applicant'])->plainTextToken;

        $response = $this->postJson('/api/v2/applicant/auth/pin/change', [
            'current_pin' => 'wrong1',
            'new_pin' => '654321',
            'new_pin_confirmation' => '654321',
        ], [
            'X-Tenant-ID' => $this->tenant->slug,
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'INVALID_CURRENT_PIN',
            ]);
    }

    // =====================================================
    // Me Endpoint Tests
    // =====================================================

    public function test_can_get_current_user(): void
    {
        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->onboardingCompleted()
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
            'identifier' => '5512345678',
            'is_primary' => true,
        ]);

        $token = $account->createToken('test-token', ['applicant'])->plainTextToken;

        $response = $this->getJson('/api/v2/applicant/auth/me', [
            'X-Tenant-ID' => $this->tenant->slug,
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'phone',
                    'email',
                    'has_pin',
                    'is_active',
                    'onboarding_step',
                    'onboarding_completed',
                ],
            ])
            ->assertJson([
                'user' => [
                    'id' => $account->id,
                    'phone' => '5512345678',
                    'has_pin' => true,
                    'onboarding_completed' => true,
                ],
            ]);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/v2/applicant/auth/me', [
            'X-Tenant-ID' => $this->tenant->slug,
        ]);

        $response->assertStatus(401);
    }

    // =====================================================
    // Logout Tests
    // =====================================================

    public function test_can_logout(): void
    {
        $account = ApplicantAccount::factory()

            ->create(['tenant_id' => $this->tenant->id]);

        $tokenResult = $account->createToken('test-token', ['applicant']);
        $token = $tokenResult->plainTextToken;

        // Verify token exists before logout
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $tokenResult->accessToken->id,
        ]);

        $response = $this->postJson('/api/v2/applicant/auth/logout', [], [
            'X-Tenant-ID' => $this->tenant->slug,
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        // Verify token is deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenResult->accessToken->id,
        ]);
    }

    // =====================================================
    // Refresh Token Tests
    // =====================================================

    public function test_can_refresh_token(): void
    {
        $account = ApplicantAccount::factory()

            ->create(['tenant_id' => $this->tenant->id]);

        $originalTokenResult = $account->createToken('test-token', ['applicant']);
        $originalToken = $originalTokenResult->plainTextToken;
        $originalTokenId = $originalTokenResult->accessToken->id;

        $response = $this->postJson('/api/v2/applicant/auth/refresh', [], [
            'X-Tenant-ID' => $this->tenant->slug,
            'Authorization' => "Bearer $originalToken",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'token',
            ]);

        $newToken = $response->json('token');
        $this->assertNotEquals($originalToken, $newToken);

        // Verify old token is deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $originalTokenId,
        ]);

        // Verify new token exists in database
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $account->id,
            'tokenable_type' => ApplicantAccount::class,
        ]);
    }

    // =====================================================
    // Tenant Isolation Tests
    // =====================================================

    public function test_cannot_login_to_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();

        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->create(['tenant_id' => $otherTenant->id]);

        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
            'identifier' => '5512345678',
        ]);

        // Try to login from different tenant
        $response = $this->postJson('/api/v2/applicant/auth/pin/login', [
            'type' => 'phone',
            'identifier' => '5512345678',
            'pin' => '123456',
        ], [
            'X-Tenant-ID' => $this->tenant->slug, // Different tenant
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'ACCOUNT_NOT_FOUND',
            ]);
    }
}
