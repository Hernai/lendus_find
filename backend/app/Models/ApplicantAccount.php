<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

/**
 * Applicant account for authentication.
 *
 * Separated from applicant data to follow single responsibility principle.
 * Supports multi-identity authentication (phone, email, WhatsApp) via ApplicantIdentity.
 * PIN authentication for quick login after initial OTP verification.
 */
class ApplicantAccount extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, SoftDeletes, HasTenant, HasAuditFields;

    protected $fillable = [
        'tenant_id',
        'person_id',
        'pin_hash',
        'pin_set_at',
        'pin_attempts',
        'pin_locked_until',
        'is_active',
        'onboarding_step',
        'onboarding_completed',
        'onboarding_completed_at',
        'last_login_at',
        'last_login_ip',
        'last_login_method',
        'known_devices',
        'preferences',
    ];

    protected $hidden = [
        'pin_hash',
    ];

    protected function casts(): array
    {
        return [
            'pin_set_at' => 'datetime',
            'pin_locked_until' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'onboarding_completed' => 'boolean',
            'known_devices' => 'array',
            'preferences' => 'array',
        ];
    }

    // =====================================================
    // Relationships
    // =====================================================

    /**
     * Get the tenant this account belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the person linked to this account.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Get person with fallback to account_id lookup.
     *
     * Use this when you need to reliably get the person, even if
     * person_id wasn't properly synced back to the account.
     */
    public function getPersonOrFind(): ?Person
    {
        // First try the direct relationship
        if ($this->person) {
            return $this->person;
        }

        // Fallback: check if there's a Person with this account_id
        $person = Person::where('account_id', $this->id)->first();

        // Sync the person_id back if found
        if ($person && !$this->person_id) {
            $this->update(['person_id' => $person->id]);
        }

        return $person;
    }

    /**
     * Get all identities for this account.
     */
    public function identities(): HasMany
    {
        return $this->hasMany(ApplicantIdentity::class, 'account_id');
    }

    /**
     * Get the primary identity.
     */
    public function primaryIdentity(): HasOne
    {
        return $this->hasOne(ApplicantIdentity::class, 'account_id')
            ->where('is_primary', true);
    }

    /**
     * Get the phone identity.
     */
    public function phoneIdentity(): HasOne
    {
        return $this->hasOne(ApplicantIdentity::class, 'account_id')
            ->where('type', 'PHONE');
    }

    /**
     * Get the email identity.
     */
    public function emailIdentity(): HasOne
    {
        return $this->hasOne(ApplicantIdentity::class, 'account_id')
            ->where('type', 'EMAIL');
    }

    // =====================================================
    // PIN Authentication
    // =====================================================

    /**
     * Check if account has PIN set.
     */
    public function hasPin(): bool
    {
        return !is_null($this->pin_hash);
    }

    /**
     * Check if account is PIN locked.
     */
    public function isPinLocked(): bool
    {
        return $this->pin_locked_until && $this->pin_locked_until->isFuture();
    }

    /**
     * Get remaining lockout minutes.
     */
    public function getLockoutMinutesAttribute(): int
    {
        if (!$this->isPinLocked()) {
            return 0;
        }
        return (int) now()->diffInMinutes($this->pin_locked_until, false);
    }

    /**
     * Verify PIN.
     */
    public function verifyPin(string $pin): bool
    {
        if (!$this->hasPin()) {
            return false;
        }

        if ($this->isPinLocked()) {
            return false;
        }

        return Hash::check($pin, $this->pin_hash);
    }

    /**
     * Set PIN.
     */
    public function setPin(string $pin): void
    {
        $this->update([
            'pin_hash' => Hash::make($pin),
            'pin_set_at' => now(),
            'pin_attempts' => 0,
            'pin_locked_until' => null,
        ]);
    }

    /**
     * Increment PIN attempts and lock if necessary.
     */
    public function incrementPinAttempts(): void
    {
        $this->increment('pin_attempts');

        if ($this->pin_attempts >= 5) {
            $this->update([
                'pin_locked_until' => now()->addMinutes(30),
            ]);
        }
    }

    /**
     * Reset PIN attempts.
     */
    public function resetPinAttempts(): void
    {
        $this->update([
            'pin_attempts' => 0,
            'pin_locked_until' => null,
        ]);
    }

    /**
     * Get remaining PIN attempts.
     */
    public function getRemainingPinAttemptsAttribute(): int
    {
        return max(0, 5 - $this->pin_attempts);
    }

    // =====================================================
    // Identity Helpers
    // =====================================================

    /**
     * Get identity by type.
     */
    public function getIdentityByType(string $type): ?ApplicantIdentity
    {
        return $this->identities()->where('type', $type)->first();
    }

    /**
     * Check if has verified identity.
     */
    public function hasVerifiedIdentity(): bool
    {
        return $this->identities()->whereNotNull('verified_at')->exists();
    }

    /**
     * Get the primary phone number.
     */
    public function getPrimaryPhoneAttribute(): ?string
    {
        $identity = $this->phoneIdentity;
        return $identity?->identifier;
    }

    /**
     * Get the primary email.
     */
    public function getPrimaryEmailAttribute(): ?string
    {
        $identity = $this->emailIdentity;
        return $identity?->identifier;
    }

    // =====================================================
    // Login Tracking
    // =====================================================

    /**
     * Record a login event.
     */
    public function recordLogin(string $method = 'PHONE_OTP'): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
            'last_login_method' => $method,
        ]);
    }

    /**
     * Add device to known devices.
     */
    public function addKnownDevice(string $deviceId, string $userAgent): void
    {
        $devices = $this->known_devices ?? [];

        // Check if device already exists
        $existingIndex = null;
        foreach ($devices as $index => $device) {
            if ($device['device_id'] === $deviceId) {
                $existingIndex = $index;
                break;
            }
        }

        $deviceData = [
            'device_id' => $deviceId,
            'last_seen' => now()->toIso8601String(),
            'user_agent' => $userAgent,
        ];

        if ($existingIndex !== null) {
            $devices[$existingIndex] = $deviceData;
        } else {
            $devices[] = $deviceData;
            // Keep only last 5 devices
            if (count($devices) > 5) {
                array_shift($devices);
            }
        }

        $this->update(['known_devices' => $devices]);
    }

    // =====================================================
    // Onboarding
    // =====================================================

    /**
     * Update onboarding step.
     */
    public function updateOnboardingStep(int $step): void
    {
        $this->update(['onboarding_step' => $step]);
    }

    /**
     * Mark onboarding as completed.
     */
    public function completeOnboarding(): void
    {
        $this->update([
            'onboarding_completed' => true,
            'onboarding_completed_at' => now(),
        ]);
    }

    // =====================================================
    // Preferences
    // =====================================================

    /**
     * Get a preference value.
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences, $key, $default);
    }

    /**
     * Set a preference value.
     */
    public function setPreference(string $key, mixed $value): void
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        $this->update(['preferences' => $preferences]);
    }

    // =====================================================
    // Scopes
    // =====================================================

    /**
     * Scope to active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to accounts with completed onboarding.
     */
    public function scopeOnboardingCompleted($query)
    {
        return $query->where('onboarding_completed', true);
    }

    /**
     * Scope to accounts for a tenant.
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
