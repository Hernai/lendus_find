<?php

namespace Tests\Unit\Models;

use App\Models\ApplicantAccount;
use App\Models\ApplicantIdentity;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApplicantAccountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    // =====================================================
    // Basic Model Tests
    // =====================================================

    public function test_can_create_account(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        $this->assertDatabaseHas('applicant_accounts', [
            'id' => $account->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_account_has_identities_relationship(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
            'is_primary' => true,
        ]);

        $this->assertCount(1, $account->fresh()->identities);
    }

    public function test_account_has_primary_identity_relationship(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
            'is_primary' => true,
        ]);

        $this->assertNotNull($account->fresh()->primaryIdentity);
    }

    // =====================================================
    // PIN Authentication Tests
    // =====================================================

    public function test_has_pin_returns_false_when_no_pin_set(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        $this->assertFalse($account->hasPin());
    }

    public function test_has_pin_returns_true_when_pin_set(): void
    {
        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->create(['tenant_id' => $this->tenant->id]);

        $this->assertTrue($account->hasPin());
    }

    public function test_verify_pin_returns_true_for_correct_pin(): void
    {
        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->create(['tenant_id' => $this->tenant->id]);

        $this->assertTrue($account->verifyPin('123456'));
    }

    public function test_verify_pin_returns_false_for_incorrect_pin(): void
    {
        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->create(['tenant_id' => $this->tenant->id]);

        $this->assertFalse($account->verifyPin('654321'));
    }

    public function test_set_pin_hashes_pin(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        $account->setPin('123456');

        $this->assertNotEquals('123456', $account->pin_hash);
        $this->assertTrue(Hash::check('123456', $account->pin_hash));
    }

    public function test_set_pin_updates_pin_set_at(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        $this->assertNull($account->pin_set_at);

        $account->setPin('123456');

        $this->assertNotNull($account->fresh()->pin_set_at);
    }

    // =====================================================
    // PIN Lockout Tests
    // =====================================================

    public function test_is_pin_locked_returns_false_when_not_locked(): void
    {
        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->create(['tenant_id' => $this->tenant->id]);

        $this->assertFalse($account->isPinLocked());
    }

    public function test_is_pin_locked_returns_true_when_locked(): void
    {
        $account = ApplicantAccount::factory()
            
            ->pinLocked()
            ->create(['tenant_id' => $this->tenant->id]);

        $this->assertTrue($account->isPinLocked());
    }

    public function test_verify_pin_returns_false_when_locked(): void
    {
        $account = ApplicantAccount::factory()
            
            ->pinLocked()
            ->create(['tenant_id' => $this->tenant->id]);

        // The PIN is '123456' in pinLocked state
        $this->assertFalse($account->verifyPin('123456'));
    }

    public function test_increment_pin_attempts_locks_after_5_attempts(): void
    {
        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->create([
                'tenant_id' => $this->tenant->id,
                'pin_attempts' => 4,
            ]);

        $account->incrementPinAttempts();

        $this->assertTrue($account->isPinLocked());
        $this->assertNotNull($account->pin_locked_until);
    }

    public function test_reset_pin_attempts_clears_lock(): void
    {
        $account = ApplicantAccount::factory()
            
            ->pinLocked()
            ->create(['tenant_id' => $this->tenant->id]);

        $account->resetPinAttempts();

        $this->assertFalse($account->fresh()->isPinLocked());
        $this->assertEquals(0, $account->fresh()->pin_attempts);
    }

    public function test_remaining_pin_attempts_calculation(): void
    {
        $account = ApplicantAccount::factory()
            
            ->withPin('123456')
            ->create([
                'tenant_id' => $this->tenant->id,
                'pin_attempts' => 3,
            ]);

        $this->assertEquals(2, $account->remaining_pin_attempts);
    }

    public function test_lockout_minutes_calculation(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create([
                'tenant_id' => $this->tenant->id,
                'pin_locked_until' => now()->addMinutes(15),
            ]);

        $this->assertGreaterThan(10, $account->lockout_minutes);
        $this->assertLessThanOrEqual(15, $account->lockout_minutes);
    }

    // =====================================================
    // Identity Helper Tests
    // =====================================================

    public function test_get_identity_by_type(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        $phoneIdentity = ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
        ]);

        $identity = $account->getIdentityByType('PHONE');

        $this->assertEquals($phoneIdentity->id, $identity->id);
    }

    public function test_has_verified_identity(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
        ]);

        $this->assertTrue($account->hasVerifiedIdentity());
    }

    public function test_has_verified_identity_returns_false_when_unverified(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->phone()->unverified()->create([
            'account_id' => $account->id,
        ]);

        $this->assertFalse($account->hasVerifiedIdentity());
    }

    public function test_primary_phone_accessor(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->phone()->verified()->create([
            'account_id' => $account->id,
            'identifier' => '5512345678',
        ]);

        $this->assertEquals('5512345678', $account->primary_phone);
    }

    public function test_primary_email_accessor(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantIdentity::factory()->email()->verified()->create([
            'account_id' => $account->id,
            'identifier' => 'test@example.com',
        ]);

        $this->assertEquals('test@example.com', $account->primary_email);
    }

    // =====================================================
    // Login Tracking Tests
    // =====================================================

    public function test_record_login_updates_last_login_at(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        $this->assertNull($account->last_login_at);

        $account->recordLogin('PHONE_OTP');

        $this->assertNotNull($account->fresh()->last_login_at);
        $this->assertEquals('PHONE_OTP', $account->fresh()->last_login_method);
    }

    // =====================================================
    // Onboarding Tests
    // =====================================================

    public function test_update_onboarding_step(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        $account->updateOnboardingStep(3);

        $this->assertEquals(3, $account->fresh()->onboarding_step);
    }

    public function test_complete_onboarding(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        $account->completeOnboarding();

        $this->assertTrue($account->fresh()->onboarding_completed);
        $this->assertNotNull($account->fresh()->onboarding_completed_at);
    }

    // =====================================================
    // Preferences Tests
    // =====================================================

    public function test_get_preference_returns_default_when_not_set(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        $value = $account->getPreference('theme', 'light');

        $this->assertEquals('light', $value);
    }

    public function test_set_preference(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        $account->setPreference('theme', 'dark');

        $this->assertEquals('dark', $account->fresh()->getPreference('theme'));
    }

    // =====================================================
    // Scopes Tests
    // =====================================================

    public function test_active_scope(): void
    {
        ApplicantAccount::factory()
            
            ->count(3)
            ->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);

        ApplicantAccount::factory()
            
            ->count(2)
            ->inactive()
            ->create(['tenant_id' => $this->tenant->id]);

        $activeCount = ApplicantAccount::active()->count();

        $this->assertEquals(3, $activeCount);
    }

    public function test_onboarding_completed_scope(): void
    {
        ApplicantAccount::factory()
            
            ->count(2)
            ->onboardingCompleted()
            ->create(['tenant_id' => $this->tenant->id]);

        ApplicantAccount::factory()
            
            ->count(3)
            ->create(['tenant_id' => $this->tenant->id]);

        $completedCount = ApplicantAccount::onboardingCompleted()->count();

        $this->assertEquals(2, $completedCount);
    }

    // =====================================================
    // Known Devices Tests
    // =====================================================

    public function test_add_known_device(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create(['tenant_id' => $this->tenant->id]);

        $account->addKnownDevice('device-123', 'Mozilla/5.0');

        $devices = $account->fresh()->known_devices;

        $this->assertCount(1, $devices);
        $this->assertEquals('device-123', $devices[0]['device_id']);
    }

    public function test_add_known_device_updates_existing(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create([
                'tenant_id' => $this->tenant->id,
                'known_devices' => [
                    ['device_id' => 'device-123', 'last_seen' => '2026-01-01', 'user_agent' => 'Old UA'],
                ],
            ]);

        $account->addKnownDevice('device-123', 'New UA');

        $devices = $account->fresh()->known_devices;

        $this->assertCount(1, $devices);
        $this->assertEquals('New UA', $devices[0]['user_agent']);
    }

    public function test_add_known_device_keeps_max_5(): void
    {
        $account = ApplicantAccount::factory()
            
            ->create([
                'tenant_id' => $this->tenant->id,
                'known_devices' => array_map(fn ($i) => [
                    'device_id' => "device-$i",
                    'last_seen' => '2026-01-01',
                    'user_agent' => 'UA',
                ], range(1, 5)),
            ]);

        $account->addKnownDevice('device-new', 'New UA');

        $devices = $account->fresh()->known_devices;

        $this->assertCount(5, $devices);
        $this->assertEquals('device-new', $devices[4]['device_id']);
    }
}
