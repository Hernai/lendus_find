<?php

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'phone',
        'type',
        'role',
        'first_name',
        'last_name',
        'avatar_url',
        'is_active',
        'phone_verified_at',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'pin_hash',
    ];

    /**
     * Maximum PIN attempts before lockout.
     */
    public const MAX_PIN_ATTEMPTS = 5;

    /**
     * PIN lockout duration in minutes.
     */
    public const PIN_LOCKOUT_MINUTES = 30;

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'pin_set_at' => 'datetime',
            'pin_locked_until' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the applicant profile.
     */
    public function applicant(): HasOne
    {
        return $this->hasOne(Applicant::class);
    }

    /**
     * Get applications assigned to this user.
     */
    public function assignedApplications(): HasMany
    {
        return $this->hasMany(Application::class, 'assigned_to');
    }

    /**
     * Get the full name.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name || $this->last_name) {
            return trim($this->first_name . ' ' . $this->last_name);
        }

        return $this->name ?? '';
    }

    /**
     * Check if user is an admin (can approve/reject, manage).
     */
    public function isAdmin(): bool
    {
        return in_array($this->type, [UserType::ADMIN, UserType::SUPER_ADMIN]);
    }

    /**
     * Check if user is an analyst (can review docs, verify refs).
     */
    public function isAnalyst(): bool
    {
        return $this->type === UserType::ANALYST;
    }

    /**
     * Check if user is an agent (promotor).
     */
    public function isAgent(): bool
    {
        return $this->type === UserType::AGENT;
    }

    /**
     * Check if user is an applicant.
     */
    public function isApplicant(): bool
    {
        return $this->type === UserType::APPLICANT;
    }

    /**
     * Check if user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->type === UserType::SUPER_ADMIN;
    }

    /**
     * Check if user is staff (can access admin panel).
     */
    public function isStaff(): bool
    {
        return $this->type?->isStaff() ?? false;
    }

    /**
     * Check if user has at least analyst level (analyst, admin, super_admin).
     */
    public function isAtLeastAnalyst(): bool
    {
        return in_array($this->type, [
            UserType::ANALYST,
            UserType::ADMIN,
            UserType::SUPER_ADMIN,
        ]);
    }

    // ==========================================
    // PERMISSION METHODS
    // ==========================================

    /**
     * Can view all applications (not just assigned).
     */
    public function canViewAllApplications(): bool
    {
        return $this->isAtLeastAnalyst();
    }

    /**
     * Can review documents (approve/reject).
     * Agents can review docs for their assigned applications.
     */
    public function canReviewDocuments(): bool
    {
        return $this->isStaff(); // All staff including agents
    }

    /**
     * Can verify references.
     * Agents are typically the ones calling references.
     */
    public function canVerifyReferences(): bool
    {
        return $this->isStaff(); // All staff including agents
    }

    /**
     * Can change application status (in_review, docs_pending).
     */
    public function canChangeApplicationStatus(): bool
    {
        return $this->isAtLeastAnalyst();
    }

    /**
     * Can approve or reject applications (final decision).
     */
    public function canApproveRejectApplications(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Can assign applications to agents.
     */
    public function canAssignApplications(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Can manage products (CRUD).
     */
    public function canManageProducts(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Can manage users (CRUD).
     */
    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Can view reports.
     */
    public function canViewReports(): bool
    {
        return $this->isAtLeastAnalyst();
    }

    /**
     * Can configure tenant settings.
     */
    public function canConfigureTenant(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Check if phone is verified.
     */
    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /**
     * Mark phone as verified.
     */
    public function markPhoneAsVerified(): bool
    {
        return $this->forceFill([
            'phone_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Record login.
     */
    public function recordLogin(): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);
    }

    /**
     * Find user by phone.
     */
    public static function findByPhone(string $phone): ?self
    {
        return static::where('phone', $phone)->first();
    }

    /**
     * Scope to active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to users of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Check if user has a PIN set.
     */
    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }

    /**
     * Set the user's PIN.
     */
    public function setPin(string $pin): bool
    {
        return $this->forceFill([
            'pin_hash' => bcrypt($pin),
            'pin_set_at' => $this->freshTimestamp(),
            'pin_attempts' => 0,
            'pin_locked_until' => null,
        ])->save();
    }

    /**
     * Verify the user's PIN.
     */
    public function verifyPin(string $pin): bool
    {
        if (!$this->hasPin()) {
            return false;
        }

        return \Illuminate\Support\Facades\Hash::check($pin, $this->pin_hash);
    }

    /**
     * Check if PIN is locked.
     */
    public function isPinLocked(): bool
    {
        if ($this->pin_locked_until === null) {
            return false;
        }

        return $this->pin_locked_until->isFuture();
    }

    /**
     * Get remaining lockout time in minutes.
     */
    public function getPinLockoutMinutes(): int
    {
        if (!$this->isPinLocked()) {
            return 0;
        }

        return (int) now()->diffInMinutes($this->pin_locked_until, false);
    }

    /**
     * Increment PIN attempts and lock if necessary.
     */
    public function incrementPinAttempts(): void
    {
        $attempts = $this->pin_attempts + 1;

        $data = ['pin_attempts' => $attempts];

        if ($attempts >= self::MAX_PIN_ATTEMPTS) {
            $data['pin_locked_until'] = now()->addMinutes(self::PIN_LOCKOUT_MINUTES);
        }

        $this->update($data);
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
     * Change PIN (requires current PIN verification).
     */
    public function changePin(string $currentPin, string $newPin): bool
    {
        if (!$this->verifyPin($currentPin)) {
            $this->incrementPinAttempts();
            return false;
        }

        $this->resetPinAttempts();
        return $this->setPin($newPin);
    }
}
