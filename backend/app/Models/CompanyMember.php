<?php

namespace App\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CompanyMember model.
 *
 * Represents a person who has a role in a company.
 * Members can be owners, legal representatives, administrators, etc.
 */
class CompanyMember extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'person_id',
        'account_id',
        'role',
        'title',
        'is_legal_representative',
        'power_type',
        'power_granted_date',
        'power_expiry_date',
        'is_shareholder',
        'ownership_percentage',
        'permissions',
        'status',
        'invited_at',
        'invited_by',
        'accepted_at',
        'suspended_at',
        'removed_at',
        'is_verified',
        'verified_at',
        'verified_by',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_legal_representative' => 'boolean',
        'is_shareholder' => 'boolean',
        'is_verified' => 'boolean',
        'ownership_percentage' => 'decimal:2',
        'power_granted_date' => 'date',
        'power_expiry_date' => 'date',
        'permissions' => 'array',
        'metadata' => 'array',
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'suspended_at' => 'datetime',
        'removed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    // =====================================================
    // Roles
    // =====================================================

    public const ROLE_OWNER = 'OWNER';
    public const ROLE_LEGAL_REP = 'LEGAL_REP';
    public const ROLE_ADMIN = 'ADMIN';
    public const ROLE_FINANCE = 'FINANCE';
    public const ROLE_OPERATIONS = 'OPERATIONS';
    public const ROLE_VIEWER = 'VIEWER';

    public static function roles(): array
    {
        return [
            self::ROLE_OWNER => 'Propietario',
            self::ROLE_LEGAL_REP => 'Representante Legal',
            self::ROLE_ADMIN => 'Administrador',
            self::ROLE_FINANCE => 'Finanzas',
            self::ROLE_OPERATIONS => 'Operaciones',
            self::ROLE_VIEWER => 'Visor',
        ];
    }

    // =====================================================
    // Power Types
    // =====================================================

    public const POWER_GENERAL = 'GENERAL';
    public const POWER_LIMITED = 'LIMITED';
    public const POWER_SPECIAL = 'SPECIAL';

    public static function powerTypes(): array
    {
        return [
            self::POWER_GENERAL => 'Poder General',
            self::POWER_LIMITED => 'Poder Limitado',
            self::POWER_SPECIAL => 'Poder Especial',
        ];
    }

    // =====================================================
    // Statuses
    // =====================================================

    public const STATUS_INVITED = 'INVITED';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_REMOVED = 'REMOVED';

    // =====================================================
    // Default Permissions by Role
    // =====================================================

    public static function defaultPermissions(string $role): array
    {
        return match ($role) {
            self::ROLE_OWNER => [
                'can_apply_credit' => true,
                'can_view_applications' => true,
                'can_sign_contracts' => true,
                'can_view_balances' => true,
                'can_view_statements' => true,
                'can_download_documents' => true,
                'can_invite_members' => true,
                'can_manage_members' => true,
                'can_edit_company_info' => true,
            ],
            self::ROLE_LEGAL_REP => [
                'can_apply_credit' => true,
                'can_view_applications' => true,
                'can_sign_contracts' => true,
                'can_view_balances' => true,
                'can_view_statements' => true,
                'can_download_documents' => true,
                'can_invite_members' => false,
                'can_manage_members' => false,
                'can_edit_company_info' => false,
            ],
            self::ROLE_ADMIN => [
                'can_apply_credit' => true,
                'can_view_applications' => true,
                'can_sign_contracts' => false,
                'can_view_balances' => true,
                'can_view_statements' => true,
                'can_download_documents' => true,
                'can_invite_members' => true,
                'can_manage_members' => true,
                'can_edit_company_info' => true,
            ],
            self::ROLE_FINANCE => [
                'can_apply_credit' => false,
                'can_view_applications' => true,
                'can_sign_contracts' => false,
                'can_view_balances' => true,
                'can_view_statements' => true,
                'can_download_documents' => true,
                'can_invite_members' => false,
                'can_manage_members' => false,
                'can_edit_company_info' => false,
            ],
            self::ROLE_OPERATIONS => [
                'can_apply_credit' => true,
                'can_view_applications' => true,
                'can_sign_contracts' => false,
                'can_view_balances' => false,
                'can_view_statements' => false,
                'can_download_documents' => true,
                'can_invite_members' => false,
                'can_manage_members' => false,
                'can_edit_company_info' => false,
            ],
            self::ROLE_VIEWER => [
                'can_apply_credit' => false,
                'can_view_applications' => true,
                'can_sign_contracts' => false,
                'can_view_balances' => false,
                'can_view_statements' => false,
                'can_download_documents' => false,
                'can_invite_members' => false,
                'can_manage_members' => false,
                'can_edit_company_info' => false,
            ],
            default => [],
        };
    }

    // =====================================================
    // Relationships
    // =====================================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ApplicantAccount::class, 'account_id');
    }

    public function invitedByMember(): BelongsTo
    {
        return $this->belongsTo(CompanyMember::class, 'invited_by');
    }

    public function verifiedByStaff(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'verified_by');
    }

    // =====================================================
    // Accessors
    // =====================================================

    public function getFullNameAttribute(): string
    {
        return $this->person?->full_name ?? 'Sin nombre';
    }

    public function getRoleLabelAttribute(): string
    {
        return self::roles()[$this->role] ?? $this->role;
    }

    public function getPowerTypeLabelAttribute(): string
    {
        return self::powerTypes()[$this->power_type] ?? $this->power_type;
    }

    // =====================================================
    // Status Helpers
    // =====================================================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInvited(): bool
    {
        return $this->status === self::STATUS_INVITED;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isRemoved(): bool
    {
        return $this->status === self::STATUS_REMOVED;
    }

    // =====================================================
    // Permission Helpers
    // =====================================================

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? self::defaultPermissions($this->role);
        return $permissions[$permission] ?? false;
    }

    public function canApplyCredit(): bool
    {
        return $this->hasPermission('can_apply_credit');
    }

    public function canSignContracts(): bool
    {
        return $this->hasPermission('can_sign_contracts');
    }

    public function canInviteMembers(): bool
    {
        return $this->hasPermission('can_invite_members');
    }

    public function canManageMembers(): bool
    {
        return $this->hasPermission('can_manage_members');
    }

    public function canEditCompanyInfo(): bool
    {
        return $this->hasPermission('can_edit_company_info');
    }

    // =====================================================
    // Legal Representative Helpers
    // =====================================================

    public function isPowerValid(): bool
    {
        if (!$this->is_legal_representative) {
            return false;
        }

        if (!$this->power_expiry_date) {
            return true; // No expiry means always valid
        }

        return $this->power_expiry_date->isFuture();
    }

    public function canLegallyBind(): bool
    {
        return $this->is_legal_representative
            && $this->isActive()
            && $this->isPowerValid();
    }

    // =====================================================
    // Actions
    // =====================================================

    public function accept(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'accepted_at' => now(),
        ]);
    }

    public function suspend(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'notes' => $reason ? ($this->notes . "\nSuspendido: " . $reason) : $this->notes,
        ]);
    }

    public function remove(): void
    {
        $this->update([
            'status' => self::STATUS_REMOVED,
            'removed_at' => now(),
        ]);
    }

    public function verify(string $staffId): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $staffId,
        ]);
    }

    // =====================================================
    // Scopes
    // =====================================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeLegalRepresentatives($query)
    {
        return $query->where('is_legal_representative', true);
    }

    public function scopeShareholders($query)
    {
        return $query->where('is_shareholder', true);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }
}
