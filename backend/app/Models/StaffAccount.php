<?php

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Staff account for admin/analyst/supervisor users.
 *
 * This model is part of the new normalized architecture that separates
 * staff authentication from applicant authentication.
 *
 * Staff users authenticate with email + password only.
 */
class StaffAccount extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    // =====================================================
    // Role Constants
    // =====================================================

    public const ROLE_ANALYST = 'ANALYST';
    public const ROLE_SUPERVISOR = 'SUPERVISOR';
    public const ROLE_ADMIN = 'ADMIN';
    public const ROLE_SUPER_ADMIN = 'SUPER_ADMIN';

    /**
     * All valid staff roles.
     */
    public const ROLES = [
        self::ROLE_ANALYST,
        self::ROLE_SUPERVISOR,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    /**
     * Roles with supervisor-level access or above.
     */
    public const SUPERVISOR_OR_ABOVE = [
        self::ROLE_SUPERVISOR,
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    /**
     * Roles with admin-level access.
     */
    public const ADMIN_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_SUPER_ADMIN,
    ];

    protected $fillable = [
        'tenant_id',
        'email',
        'password',
        'role',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'remember_token',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // =====================================================
    // Relationships
    // =====================================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(StaffProfile::class, 'account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(self::class, 'updated_by');
    }

    // =====================================================
    // Accessors
    // =====================================================

    /**
     * Get the user's full name from profile.
     */
    public function getNameAttribute(): string
    {
        return $this->profile?->full_name ?? $this->email;
    }

    /**
     * Get the user type as UserType enum for compatibility.
     */
    public function getTypeAttribute(): UserType
    {
        return UserType::tryFrom($this->role) ?? UserType::ANALYST;
    }

    // =====================================================
    // Role Checks
    // =====================================================

    public function isAnalyst(): bool
    {
        return $this->role === self::ROLE_ANALYST;
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, self::ADMIN_ROLES, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isStaff(): bool
    {
        return true; // All staff accounts are staff by definition
    }

    public function isSupervisorOrAbove(): bool
    {
        return in_array($this->role, self::SUPERVISOR_OR_ABOVE, true);
    }

    public function isAtLeastAnalyst(): bool
    {
        return in_array($this->role, self::ROLES, true);
    }

    // =====================================================
    // Permission Methods (compatible with User model)
    // =====================================================

    public function canViewAllApplications(): bool
    {
        return $this->isSupervisorOrAbove();
    }

    public function canReviewDocuments(): bool
    {
        return true; // All staff can review
    }

    public function canVerifyReferences(): bool
    {
        return true; // All staff can verify
    }

    public function canChangeApplicationStatus(): bool
    {
        return $this->isAtLeastAnalyst() || $this->isSupervisor();
    }

    public function canApproveRejectApplications(): bool
    {
        return $this->isSupervisorOrAbove();
    }

    public function canAssignApplications(): bool
    {
        return $this->isSupervisorOrAbove();
    }

    public function canManageProducts(): bool
    {
        return $this->isAdmin();
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    public function canViewReports(): bool
    {
        return $this->isAtLeastAnalyst();
    }

    public function canConfigureTenant(): bool
    {
        return $this->isSuperAdmin();
    }

    // =====================================================
    // Utility Methods
    // =====================================================

    /**
     * Record login timestamp and IP.
     */
    public function recordLogin(): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);
    }

    /**
     * Get permissions array for API response.
     */
    public function getPermissionsArray(): array
    {
        return [
            'canViewAllApplications' => $this->canViewAllApplications(),
            'canReviewDocuments' => $this->canReviewDocuments(),
            'canVerifyReferences' => $this->canVerifyReferences(),
            'canChangeApplicationStatus' => $this->canChangeApplicationStatus(),
            'canApproveRejectApplications' => $this->canApproveRejectApplications(),
            'canAssignApplications' => $this->canAssignApplications(),
            'canManageProducts' => $this->canManageProducts(),
            'canManageUsers' => $this->canManageUsers(),
            'canViewReports' => $this->canViewReports(),
            'canConfigureTenant' => $this->canConfigureTenant(),
        ];
    }

    // =====================================================
    // Scopes
    // =====================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
