<?php

namespace Tests\Unit\Models;

use App\Models\ApplicantAccount;
use App\Models\ApplicantIdentity;
use App\Models\OtpRequest;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    // =====================================================
    // Basic Model Tests
    // =====================================================

    public function test_can_create_otp_request(): void
    {
        $otpRequest = OtpRequest::factory()->phoneSms()->create();

        $this->assertDatabaseHas('otp_requests', [
            'id' => $otpRequest->id,
            'channel' => 'SMS',
        ]);
    }

    public function test_can_create_otp_request_for_email(): void
    {
        $otpRequest = OtpRequest::factory()->email()->create();

        $this->assertEquals('EMAIL', $otpRequest->channel);
        $this->assertEquals('EMAIL', $otpRequest->target_type);
    }

    public function test_can_create_otp_request_for_whatsapp(): void
    {
        $otpRequest = OtpRequest::factory()->phoneWhatsapp()->create();

        $this->assertEquals('WHATSAPP', $otpRequest->channel);
    }

    public function test_otp_request_can_be_linked_to_identity(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $identity = ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
        ]);

        $otpRequest = OtpRequest::factory()->forIdentity($identity)->create();

        $this->assertEquals($identity->id, $otpRequest->identity_id);
        $this->assertEquals($identity->id, $otpRequest->identity->id);
    }

    // =====================================================
    // Status Check Tests
    // =====================================================

    public function test_is_expired_returns_false_for_valid_otp(): void
    {
        $otpRequest = OtpRequest::factory()->valid()->create();

        $this->assertFalse($otpRequest->isExpired());
    }

    public function test_is_expired_returns_true_for_expired_otp(): void
    {
        $otpRequest = OtpRequest::factory()->expired()->create();

        $this->assertTrue($otpRequest->isExpired());
    }

    public function test_is_verified_returns_false_for_unverified_otp(): void
    {
        $otpRequest = OtpRequest::factory()->valid()->create();

        $this->assertFalse($otpRequest->isVerified());
    }

    public function test_is_verified_returns_true_for_verified_otp(): void
    {
        $otpRequest = OtpRequest::factory()->verified()->create();

        $this->assertTrue($otpRequest->isVerified());
    }

    public function test_is_valid_returns_true_for_valid_otp(): void
    {
        $otpRequest = OtpRequest::factory()->valid()->create();

        $this->assertTrue($otpRequest->isValid());
    }

    public function test_is_valid_returns_false_for_expired_otp(): void
    {
        $otpRequest = OtpRequest::factory()->expired()->create();

        $this->assertFalse($otpRequest->isValid());
    }

    public function test_is_valid_returns_false_for_verified_otp(): void
    {
        $otpRequest = OtpRequest::factory()->verified()->create();

        $this->assertFalse($otpRequest->isValid());
    }

    public function test_has_too_many_attempts(): void
    {
        $otpRequest = OtpRequest::factory()->tooManyAttempts()->create();

        $this->assertTrue($otpRequest->hasTooManyAttempts());
    }

    // =====================================================
    // Verification Tests
    // =====================================================

    public function test_verify_returns_true_for_correct_code(): void
    {
        $otpRequest = OtpRequest::factory()->withCode('123456')->valid()->create();

        $result = $otpRequest->verify('123456');

        $this->assertTrue($result);
        $this->assertTrue($otpRequest->fresh()->isVerified());
    }

    public function test_verify_returns_false_for_incorrect_code(): void
    {
        $otpRequest = OtpRequest::factory()->withCode('123456')->valid()->create();

        $result = $otpRequest->verify('654321');

        $this->assertFalse($result);
        $this->assertFalse($otpRequest->fresh()->isVerified());
    }

    public function test_verify_increments_attempts_on_failure(): void
    {
        $otpRequest = OtpRequest::factory()->withCode('123456')->valid()->create();

        $otpRequest->verify('wrong');

        $this->assertEquals(1, $otpRequest->fresh()->attempts);
    }

    public function test_verify_returns_false_for_expired_otp(): void
    {
        $otpRequest = OtpRequest::factory()->withCode('123456')->expired()->create();

        $result = $otpRequest->verify('123456');

        $this->assertFalse($result);
    }

    public function test_verify_returns_false_for_already_verified_otp(): void
    {
        $otpRequest = OtpRequest::factory()->withCode('123456')->verified()->create();

        $result = $otpRequest->verify('123456');

        $this->assertFalse($result);
    }

    public function test_verify_returns_false_when_too_many_attempts(): void
    {
        $otpRequest = OtpRequest::factory()->withCode('123456')->tooManyAttempts()->create();

        $result = $otpRequest->verify('123456');

        $this->assertFalse($result);
    }

    // =====================================================
    // Accessor Tests
    // =====================================================

    public function test_remaining_attempts_calculation(): void
    {
        $otpRequest = OtpRequest::factory()->create(['attempts' => 3]);

        $this->assertEquals(2, $otpRequest->remaining_attempts);
    }

    public function test_remaining_attempts_is_zero_when_exceeded(): void
    {
        $otpRequest = OtpRequest::factory()->create(['attempts' => 7]);

        $this->assertEquals(0, $otpRequest->remaining_attempts);
    }

    public function test_seconds_until_expiry_for_valid_otp(): void
    {
        $otpRequest = OtpRequest::factory()->create([
            'expires_at' => now()->addMinutes(5),
        ]);

        $seconds = $otpRequest->seconds_until_expiry;

        $this->assertGreaterThan(200, $seconds);
        $this->assertLessThanOrEqual(300, $seconds);
    }

    public function test_seconds_until_expiry_is_zero_for_expired_otp(): void
    {
        $otpRequest = OtpRequest::factory()->expired()->create();

        $this->assertEquals(0, $otpRequest->seconds_until_expiry);
    }

    public function test_channel_label_for_sms(): void
    {
        $otpRequest = OtpRequest::factory()->phoneSms()->create();

        $this->assertEquals('SMS', $otpRequest->channel_label);
    }

    public function test_channel_label_for_email(): void
    {
        $otpRequest = OtpRequest::factory()->email()->create();

        $this->assertEquals('Correo electrónico', $otpRequest->channel_label);
    }

    public function test_channel_label_for_whatsapp(): void
    {
        $otpRequest = OtpRequest::factory()->phoneWhatsapp()->create();

        $this->assertEquals('WhatsApp', $otpRequest->channel_label);
    }

    // =====================================================
    // Scopes Tests
    // =====================================================

    public function test_valid_scope(): void
    {
        OtpRequest::factory()->valid()->count(3)->create();
        OtpRequest::factory()->expired()->count(2)->create();
        OtpRequest::factory()->verified()->count(1)->create();

        $validCount = OtpRequest::valid()->count();

        $this->assertEquals(3, $validCount);
    }

    public function test_recent_scope(): void
    {
        OtpRequest::factory()->recent()->count(2)->create();
        OtpRequest::factory()->old()->count(3)->create();

        $recentCount = OtpRequest::recent()->count();

        $this->assertEquals(2, $recentCount);
    }

    public function test_for_target_scope(): void
    {
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->count(2)->create();
        OtpRequest::factory()->forTarget('PHONE', '5587654321')->count(1)->create();
        OtpRequest::factory()->forTarget('EMAIL', 'test@example.com')->count(1)->create();

        $count = OtpRequest::forTarget('PHONE', '5512345678')->count();

        $this->assertEquals(2, $count);
    }

    // =====================================================
    // Static Helper Tests
    // =====================================================

    public function test_count_recent_requests(): void
    {
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->recent()->count(3)->create();
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->old()->count(2)->create();

        $count = OtpRequest::countRecentRequests('PHONE', '5512345678');

        $this->assertEquals(3, $count);
    }

    public function test_can_send_otp_returns_true_under_limit(): void
    {
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->recent()->count(2)->create();

        $this->assertTrue(OtpRequest::canSendOtp('PHONE', '5512345678'));
    }

    public function test_can_send_otp_returns_false_at_limit(): void
    {
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->recent()->count(3)->create();

        $this->assertFalse(OtpRequest::canSendOtp('PHONE', '5512345678'));
    }

    public function test_get_latest_valid_otp(): void
    {
        // Create older valid OTP
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->valid()->create([
            'created_at' => now()->subMinutes(5),
        ]);

        // Create newer valid OTP
        $newerOtp = OtpRequest::factory()->forTarget('PHONE', '5512345678')->valid()->create([
            'created_at' => now(),
        ]);

        // Create expired OTP
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->expired()->create();

        $latestValid = OtpRequest::getLatestValidOtp('PHONE', '5512345678');

        $this->assertNotNull($latestValid);
        $this->assertEquals($newerOtp->id, $latestValid->id);
    }

    public function test_get_latest_valid_otp_returns_null_when_none_valid(): void
    {
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->expired()->count(2)->create();
        OtpRequest::factory()->forTarget('PHONE', '5512345678')->verified()->count(1)->create();

        $latestValid = OtpRequest::getLatestValidOtp('PHONE', '5512345678');

        $this->assertNull($latestValid);
    }

    public function test_create_for_target(): void
    {
        $otpRequest = OtpRequest::createForTarget('PHONE', '5512345678', 'SMS');

        $this->assertEquals('PHONE', $otpRequest->target_type);
        $this->assertEquals('5512345678', $otpRequest->target_value);
        $this->assertEquals('SMS', $otpRequest->channel);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otpRequest->code);
        $this->assertNotNull($otpRequest->expires_at);
        $this->assertTrue($otpRequest->expires_at->isFuture());
    }

    public function test_create_for_target_with_identity_id(): void
    {
        $account = ApplicantAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        $identity = ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
        ]);

        $otpRequest = OtpRequest::createForTarget('PHONE', $identity->identifier, 'SMS', $identity->id);

        $this->assertEquals($identity->id, $otpRequest->identity_id);
    }
}
