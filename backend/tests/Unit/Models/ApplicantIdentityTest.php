<?php

namespace Tests\Unit\Models;

use App\Models\ApplicantAccount;
use App\Models\ApplicantIdentity;
use App\Models\OtpRequest;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantIdentityTest extends TestCase
{
    private ApplicantAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);
    }

    // =====================================================
    // Basic Model Tests
    // =====================================================

    public function test_can_create_phone_identity(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $this->account->id,
            'identifier' => '5512345678',
        ]);

        $this->assertDatabaseHas('applicant_identities', [
            'id' => $identity->id,
            'account_id' => $this->account->id,
            'type' => 'PHONE',
            'identifier' => '5512345678',
        ]);
    }

    public function test_can_create_email_identity(): void
    {
        $identity = ApplicantIdentity::factory()->email()->verified()->create([
            'account_id' => $this->account->id,
            'identifier' => 'test@example.com',
        ]);

        $this->assertDatabaseHas('applicant_identities', [
            'id' => $identity->id,
            'type' => 'EMAIL',
            'identifier' => 'test@example.com',
        ]);
    }

    public function test_can_create_whatsapp_identity(): void
    {
        $identity = ApplicantIdentity::factory()->whatsapp()->verified()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertEquals('WHATSAPP', $identity->type);
    }

    public function test_identity_belongs_to_account(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertEquals($this->account->id, $identity->account->id);
    }

    // =====================================================
    // Type Check Tests
    // =====================================================

    public function test_is_phone_returns_true_for_phone_type(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertTrue($identity->isPhone());
        $this->assertFalse($identity->isEmail());
        $this->assertFalse($identity->isWhatsApp());
    }

    public function test_is_email_returns_true_for_email_type(): void
    {
        $identity = ApplicantIdentity::factory()->email()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertFalse($identity->isPhone());
        $this->assertTrue($identity->isEmail());
        $this->assertFalse($identity->isWhatsApp());
    }

    public function test_is_whatsapp_returns_true_for_whatsapp_type(): void
    {
        $identity = ApplicantIdentity::factory()->whatsapp()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertFalse($identity->isPhone());
        $this->assertFalse($identity->isEmail());
        $this->assertTrue($identity->isWhatsApp());
    }

    // =====================================================
    // Verification Tests
    // =====================================================

    public function test_is_verified_returns_true_when_verified(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertTrue($identity->isVerified());
    }

    public function test_is_verified_returns_false_when_unverified(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->unverified()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertFalse($identity->isVerified());
    }

    public function test_generate_otp_creates_6_digit_code(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->create([
            'account_id' => $this->account->id,
        ]);

        $code = $identity->generateOtp();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertEquals($code, $identity->fresh()->verification_code);
        $this->assertNotNull($identity->fresh()->verification_code_expires_at);
    }

    public function test_verify_otp_returns_true_for_correct_code(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->withVerificationCode('123456')->create([
            'account_id' => $this->account->id,
        ]);

        $result = $identity->verifyOtp('123456');

        $this->assertTrue($result);
        $this->assertTrue($identity->fresh()->isVerified());
    }

    public function test_verify_otp_returns_false_for_incorrect_code(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->withVerificationCode('123456')->create([
            'account_id' => $this->account->id,
        ]);

        $result = $identity->verifyOtp('654321');

        $this->assertFalse($result);
        $this->assertFalse($identity->fresh()->isVerified());
    }

    public function test_verify_otp_increments_attempts_on_failure(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->withVerificationCode('123456')->create([
            'account_id' => $this->account->id,
        ]);

        $identity->verifyOtp('wrong');

        $this->assertEquals(1, $identity->fresh()->verification_attempts);
    }

    public function test_verify_otp_returns_false_for_expired_code(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->expiredCode()->create([
            'account_id' => $this->account->id,
        ]);

        // Get the code before trying to verify
        $code = $identity->verification_code;
        $result = $identity->verifyOtp($code);

        $this->assertFalse($result);
    }

    public function test_verify_otp_clears_code_after_success(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->withVerificationCode('123456')->create([
            'account_id' => $this->account->id,
        ]);

        $identity->verifyOtp('123456');

        $this->assertNull($identity->fresh()->verification_code);
        $this->assertNull($identity->fresh()->verification_code_expires_at);
    }

    // =====================================================
    // Rate Limiting Tests
    // =====================================================

    public function test_can_request_otp_returns_true_when_under_limit(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertTrue($identity->canRequestOtp());
    }

    public function test_can_request_otp_returns_false_when_at_limit(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $this->account->id,
        ]);

        // Create 3 OTP requests in the last hour
        OtpRequest::factory()->count(3)->recent()->create([
            'identity_id' => $identity->id,
            'target_type' => 'PHONE',
            'target_value' => $identity->identifier,
        ]);

        $this->assertFalse($identity->canRequestOtp());
    }

    public function test_remaining_otp_requests_calculation(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $this->account->id,
        ]);

        OtpRequest::factory()->count(2)->recent()->create([
            'identity_id' => $identity->id,
            'target_type' => 'PHONE',
            'target_value' => $identity->identifier,
        ]);

        $this->assertEquals(1, $identity->remaining_otp_requests);
    }

    public function test_has_too_many_verification_attempts(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->tooManyAttempts()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertTrue($identity->hasTooManyVerificationAttempts());
    }

    // =====================================================
    // Display Helper Tests
    // =====================================================

    public function test_masked_identifier_for_phone(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->create([
            'account_id' => $this->account->id,
            'identifier' => '5512345678',
        ]);

        $this->assertEquals('5512****78', $identity->masked_identifier);
    }

    public function test_masked_identifier_for_email(): void
    {
        $identity = ApplicantIdentity::factory()->email()->create([
            'account_id' => $this->account->id,
            'identifier' => 'john@example.com',
        ]);

        $this->assertEquals('jo***@example.com', $identity->masked_identifier);
    }

    public function test_type_label_for_phone(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertEquals('Teléfono', $identity->type_label);
    }

    public function test_type_label_for_email(): void
    {
        $identity = ApplicantIdentity::factory()->email()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertEquals('Correo electrónico', $identity->type_label);
    }

    public function test_type_label_for_whatsapp(): void
    {
        $identity = ApplicantIdentity::factory()->whatsapp()->create([
            'account_id' => $this->account->id,
        ]);

        $this->assertEquals('WhatsApp', $identity->type_label);
    }

    // =====================================================
    // Scopes Tests
    // =====================================================

    public function test_verified_scope(): void
    {
        // Create first verified identity (phone)
        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $this->account->id,
        ]);

        // Create second verified identity (email) for same account
        ApplicantIdentity::factory()->email()->verified()->create([
            'account_id' => $this->account->id,
        ]);

        // Create unverified identity for different account
        $account2 = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        ApplicantIdentity::factory()->phone()->unverified()->create([
            'account_id' => $account2->id,
        ]);

        $verifiedCount = ApplicantIdentity::verified()->count();

        $this->assertEquals(2, $verifiedCount);
    }

    public function test_primary_scope(): void
    {
        ApplicantIdentity::factory()->phone()->primary()->create([
            'account_id' => $this->account->id,
        ]);

        $account2 = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        ApplicantIdentity::factory()->phone()->create([
            'account_id' => $account2->id,
            'is_primary' => false,
        ]);

        $primaryCount = ApplicantIdentity::primary()->count();

        $this->assertEquals(1, $primaryCount);
    }

    public function test_of_type_scope(): void
    {
        ApplicantIdentity::factory()->phone()->create([
            'account_id' => $this->account->id,
        ]);

        $account2 = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        ApplicantIdentity::factory()->email()->create([
            'account_id' => $account2->id,
        ]);

        $phoneCount = ApplicantIdentity::ofType('PHONE')->count();

        $this->assertEquals(1, $phoneCount);
    }

    // =====================================================
    // Static Finder Tests
    // =====================================================

    public function test_find_by_identifier(): void
    {
        $identity = ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $this->account->id,
            'identifier' => '5512345678',
        ]);

        $found = ApplicantIdentity::findByIdentifier('PHONE', '5512345678', $this->tenant->id);

        $this->assertNotNull($found);
        $this->assertEquals($identity->id, $found->id);
    }

    public function test_find_by_identifier_returns_null_for_different_tenant(): void
    {
        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $this->account->id,
            'identifier' => '5512345678',
        ]);

        $otherTenant = Tenant::factory()->create();
        $found = ApplicantIdentity::findByIdentifier('PHONE', '5512345678', $otherTenant->id);

        $this->assertNull($found);
    }

    public function test_find_by_identifier_returns_null_for_non_existent(): void
    {
        $found = ApplicantIdentity::findByIdentifier('PHONE', '9999999999', $this->tenant->id);

        $this->assertNull($found);
    }
}
