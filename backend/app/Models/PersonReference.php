<?php

namespace App\Models;

use App\Enums\ReferenceType;
use App\Enums\Relationship;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Person reference (personal/work contacts).
 *
 * References are contacts that can vouch for the person.
 * Used for credit verification and collection purposes.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $person_id
 * @property string|null $application_id
 * @property string $type
 * @property string $first_name
 * @property string $last_name_1
 * @property string|null $last_name_2
 * @property string $phone
 * @property string|null $email
 * @property string $relationship
 * @property int|null $years_known
 * @property string $status
 * @property \Carbon\Carbon|null $verified_at
 * @property string|null $verified_by
 * @property string|null $verification_notes
 * @property array|null $contact_attempts
 * @property string|null $notes
 * @property array|null $metadata
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property string|null $created_by
 * @property string|null $updated_by
 *
 * @property-read bool $is_verified
 * @property-read string $full_name
 * @property-read string $type_label
 * @property-read string $relationship_label
 * @property-read int $contact_attempts_count
 *
 * @property-read Tenant $tenant
 * @property-read Person $person
 * @property-read StaffAccount|null $verifier
 */
class PersonReference extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant;

    protected $table = 'person_references';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_UNREACHABLE = 'UNREACHABLE';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_NO_ANSWER = 'NO_ANSWER';

    protected $fillable = [
        'tenant_id',
        'person_id',
        'application_id',
        'type',
        'first_name',
        'last_name_1',
        'last_name_2',
        'phone',
        'email',
        'relationship',
        'years_known',
        'status',
        'verified_at',
        'verified_by',
        'verification_notes',
        'contact_attempts',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'contact_attempts' => 'array',
            'metadata' => 'array',
            'years_known' => 'integer',
        ];
    }

    // =====================================================
    // Relationships
    // =====================================================

    /**
     * Get the person this reference belongs to.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Get the staff who verified this reference.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'verified_by');
    }

    // =====================================================
    // Accessors
    // =====================================================

    /**
     * Check if reference is verified.
     */
    public function getIsVerifiedAttribute(): bool
    {
        return $this->status === self::STATUS_VERIFIED && !is_null($this->verified_at);
    }

    /**
     * Get full name.
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->last_name_1,
            $this->last_name_2,
        ]);

        return implode(' ', $parts);
    }

    /**
     * Get type label.
     */
    public function getTypeLabelAttribute(): string
    {
        $enum = ReferenceType::tryFrom($this->type);
        return $enum?->label() ?? $this->type;
    }

    /**
     * Get relationship label.
     */
    public function getRelationshipLabelAttribute(): string
    {
        $enum = Relationship::tryFrom($this->relationship);
        return $enum?->label() ?? $this->relationship;
    }

    /**
     * Get contact attempts count.
     */
    public function getContactAttemptsCountAttribute(): int
    {
        return count($this->contact_attempts ?? []);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_VERIFIED => 'Verificado',
            self::STATUS_UNREACHABLE => 'No contactable',
            self::STATUS_REJECTED => 'Rechazado',
            self::STATUS_NO_ANSWER => 'Sin respuesta',
            default => $this->status,
        };
    }

    // =====================================================
    // Verification Methods
    // =====================================================

    /**
     * Mark as verified.
     */
    public function markAsVerified(?string $verifiedBy = null, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_VERIFIED,
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
            'verification_notes' => $notes,
        ]);
    }

    /**
     * Mark as unreachable.
     */
    public function markAsUnreachable(?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_UNREACHABLE,
            'verification_notes' => $notes,
        ]);
    }

    /**
     * Mark as rejected.
     */
    public function markAsRejected(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'verification_notes' => $reason,
        ]);
    }

    /**
     * Mark as no answer.
     */
    public function markAsNoAnswer(): void
    {
        $this->update([
            'status' => self::STATUS_NO_ANSWER,
        ]);
    }

    // =====================================================
    // Contact Attempts Methods
    // =====================================================

    /**
     * Log a contact attempt.
     */
    public function logContactAttempt(string $result, ?string $notes = null, ?string $byUserId = null): void
    {
        $attempts = $this->contact_attempts ?? [];

        $attempts[] = [
            'date' => now()->toDateString(),
            'time' => now()->format('H:i'),
            'result' => $result,
            'notes' => $notes,
            'by' => $byUserId,
        ];

        $this->update(['contact_attempts' => $attempts]);
    }

    /**
     * Get the last contact attempt.
     */
    public function getLastContactAttempt(): ?array
    {
        $attempts = $this->contact_attempts ?? [];
        return !empty($attempts) ? end($attempts) : null;
    }

    /**
     * Check if has been contacted recently (within hours).
     */
    public function hasBeenContactedRecently(int $hours = 24): bool
    {
        $lastAttempt = $this->getLastContactAttempt();

        if (!$lastAttempt) {
            return false;
        }

        $lastContactTime = \Carbon\Carbon::parse("{$lastAttempt['date']} {$lastAttempt['time']}");
        return $lastContactTime->diffInHours(now()) < $hours;
    }

    // =====================================================
    // Type Checks
    // =====================================================

    /**
     * Check if this is a personal reference.
     */
    public function isPersonal(): bool
    {
        return $this->type === ReferenceType::PERSONAL->value;
    }

    /**
     * Check if this is a work reference.
     */
    public function isWork(): bool
    {
        return $this->type === ReferenceType::WORK->value;
    }

    /**
     * Check if the relationship is family.
     */
    public function isFamilyRelationship(): bool
    {
        $enum = Relationship::tryFrom($this->relationship);
        return $enum?->isFamily() ?? false;
    }

    // =====================================================
    // Scopes
    // =====================================================

    /**
     * Scope to verified references.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonReference> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonReference>
     */
    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    /**
     * Scope to pending references.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonReference> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonReference>
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to specific type.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonReference> $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder<PersonReference>
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to personal references.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonReference> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonReference>
     */
    public function scopePersonal($query)
    {
        return $query->where('type', ReferenceType::PERSONAL->value);
    }

    /**
     * Scope to work references.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonReference> $query
     * @return \Illuminate\Database\Eloquent\Builder<PersonReference>
     */
    public function scopeWork($query)
    {
        return $query->where('type', ReferenceType::WORK->value);
    }

    /**
     * Scope by phone number.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PersonReference> $query
     * @param string $phone
     * @return \Illuminate\Database\Eloquent\Builder<PersonReference>
     */
    public function scopeByPhone($query, string $phone)
    {
        return $query->where('phone', $phone);
    }

    // =====================================================
    // Static Finders
    // =====================================================

    /**
     * Find references for a person by type.
     */
    public static function findByPersonAndType(string $personId, string $type): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('person_id', $personId)
            ->where('type', $type)
            ->get();
    }

    /**
     * Check if phone exists as reference for person.
     */
    public static function phoneExistsForPerson(string $personId, string $phone): bool
    {
        return self::where('person_id', $personId)
            ->where('phone', $phone)
            ->exists();
    }
}
