<?php

namespace App\Models;

use App\Enums\VerifiableField;
use App\Enums\VerificationMethod;
use App\Enums\VerificationStatus;
use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataVerification extends Model
{
    use HasFactory, HasUuid, HasTenant, HasAuditFields;

    protected $fillable = [
        'tenant_id',
        'applicant_id',
        'entity_type',
        'entity_id',
        'field_name',
        'field_value',
        'method',
        'is_verified',
        'is_locked',
        'notes',
        'rejection_reason',
        'status',
        'rejected_at',
        'corrected_at',
        'correction_history',
        'metadata',
        'verified_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => VerificationStatus::class,
        'method' => VerificationMethod::class,
        'is_verified' => 'boolean',
        'is_locked' => 'boolean',
        'metadata' => 'array',
        'correction_history' => 'array',
        'rejected_at' => 'datetime',
        'corrected_at' => 'datetime',
    ];

    /**
     * Get the person (for person-related verifications).
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'applicant_id');
    }

    /**
     * Get the entity (Person or Company) - polymorphic relationship.
     */
    public function entity(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the staff who verified.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'verified_by');
    }

    /**
     * Scope to filter by entity (Person or Company).
     */
    public function scopeForEntity($query, $entity)
    {
        return $query->where('entity_type', get_class($entity))
            ->where('entity_id', $entity->id);
    }

    /**
     * Get field label in Spanish.
     */
    public static function getFieldLabel(string $field): string
    {
        $enum = VerifiableField::tryFrom($field);
        return $enum?->label() ?? $field;
    }

    /**
     * Get method label in Spanish.
     */
    public static function getMethodLabel(string $method): string
    {
        $enum = VerificationMethod::tryFrom($method);
        return $enum?->label() ?? $method;
    }

    /**
     * Get status label in Spanish.
     */
    public static function getStatusLabel(string $status): string
    {
        $enum = VerificationStatus::tryFrom($status);
        return $enum?->label() ?? $status;
    }

    /**
     * Check if this field is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === VerificationStatus::REJECTED;
    }

    /**
     * Check if this field needs correction.
     */
    public function needsCorrection(): bool
    {
        return $this->status === VerificationStatus::REJECTED;
    }

    /**
     * Check if this field is locked (cannot be modified).
     */
    public function isLocked(): bool
    {
        return $this->is_locked === true;
    }

    /**
     * Check if this field can be modified.
     */
    public function canBeModified(): bool
    {
        return !$this->isLocked();
    }

    /**
     * Reject this field with a reason.
     */
    public function reject(string $reason, ?string $userId = null): void
    {
        $this->status = VerificationStatus::REJECTED;
        $this->rejection_reason = $reason;
        $this->rejected_at = now();
        $this->verified_by = $userId;
        $this->save();
    }

    /**
     * Mark as corrected (user submitted new value) with history.
     */
    public function markCorrected($oldValue = null, $newValue = null, ?array $correctedBy = null): void
    {
        // Append to correction history
        $history = $this->correction_history ?? [];
        $history[] = [
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'rejection_reason' => $this->rejection_reason,
            'corrected_by' => $correctedBy,
            'corrected_at' => now()->toIso8601String(),
        ];

        $this->status = VerificationStatus::CORRECTED;
        $this->corrected_at = now();
        $this->correction_history = $history;
        $this->save();
    }

    /**
     * Get the correction history count.
     */
    public function getCorrectionCountAttribute(): int
    {
        return count($this->correction_history ?? []);
    }

    /**
     * Verify this field.
     */
    public function verify(?string $method = null, ?string $userId = null, ?string $notes = null): void
    {
        // Check if locked before allowing modification
        if ($this->exists && $this->isLocked()) {
            throw new \Exception("Cannot modify locked field: {$this->field_name}. This field was verified by KYC.");
        }

        $this->status = VerificationStatus::VERIFIED;
        $this->is_verified = true;
        $this->method = $method ?? VerificationMethod::MANUAL->value;
        $this->verified_by = $userId;
        $this->notes = $notes;
        $this->rejection_reason = null; // Clear any previous rejection
        $this->save();
    }

    /**
     * Scope to get rejected fields.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', VerificationStatus::REJECTED);
    }

    /**
     * Scope to get pending fields.
     */
    public function scopePending($query)
    {
        return $query->where('status', VerificationStatus::PENDING);
    }

    /**
     * Scope to get verified fields.
     */
    public function scopeVerified($query)
    {
        return $query->where('status', VerificationStatus::VERIFIED);
    }

    /**
     * Create or update a verification record for a field.
     *
     * @param string $applicantId
     * @param VerifiableField|string $field
     * @param mixed $value
     * @param VerificationMethod|string $method
     * @param bool $isVerified
     * @param array|null $metadata Additional data about the verification
     * @param string|null $notes
     * @return static
     */
    public static function recordVerification(
        string $applicantId,
        VerifiableField|string $field,
        mixed $value,
        VerificationMethod|string $method,
        bool $isVerified = true,
        ?array $metadata = null,
        ?string $notes = null
    ): static {
        $fieldName = $field instanceof VerifiableField ? $field->value : $field;
        $methodValue = $method instanceof VerificationMethod ? $method->value : $method;

        // Check if field is already locked
        $existing = static::where('applicant_id', $applicantId)
            ->where('field_name', $fieldName)
            ->first();

        // Determine the method enum for comparison
        $methodEnum = $method instanceof VerificationMethod ? $method : VerificationMethod::tryFrom($methodValue);

        // Allow RENAPO/SAT to override OCR-locked fields (RENAPO is official government source)
        // RENAPO data should always take precedence over OCR which can have errors
        $isOfficialSource = in_array($methodEnum, [
            VerificationMethod::RENAPO,
            VerificationMethod::KYC_CURP_RENAPO,
            VerificationMethod::SAT,
            VerificationMethod::KYC_RFC_SAT,
        ]);

        if ($existing && $existing->is_locked && !$isOfficialSource) {
            // Field is locked and new method is not an official source - return existing record
            return $existing;
        }

        // Determine if this method should lock the field
        // Lock if method is automated KYC
        $shouldLock = $methodEnum && $methodEnum->isAutomated() && $isVerified;

        return static::updateOrCreate(
            [
                'applicant_id' => $applicantId,
                'field_name' => $fieldName,
            ],
            [
                'field_value' => is_array($value) ? json_encode($value) : (string) $value,
                'method' => $methodValue,
                'is_verified' => $isVerified,
                'is_locked' => $shouldLock,
                'status' => $isVerified ? VerificationStatus::VERIFIED : VerificationStatus::PENDING,
                'metadata' => $metadata,
                'notes' => $notes,
            ]
        );
    }

    /**
     * Batch record multiple verifications.
     *
     * @param string $applicantId
     * @param array $verifications Array of [field => [value, method, verified, metadata, notes]]
     * @return array<string, static>
     */
    public static function recordBatchVerifications(
        string $applicantId,
        array $verifications
    ): array {
        $results = [];

        foreach ($verifications as $field => $data) {
            $value = $data['value'] ?? $data[0] ?? null;
            $method = $data['method'] ?? $data[1] ?? VerificationMethod::API;
            $verified = $data['verified'] ?? $data[2] ?? true;
            $metadata = $data['metadata'] ?? $data[3] ?? null;
            $notes = $data['notes'] ?? $data[4] ?? null;

            $results[$field] = static::recordVerification(
                $applicantId,
                $field,
                $value,
                $method,
                $verified,
                $metadata,
                $notes
            );
        }

        return $results;
    }

    /**
     * Get all verified fields for an applicant as a simple array.
     *
     * @param string $applicantId
     * @return array<string, array{value: string, method: string, verified_at: string, metadata: array|null, is_locked: bool}>
     */
    public static function getVerifiedFieldsForApplicant(string $applicantId): array
    {
        $verifications = static::where('applicant_id', $applicantId)
            ->where('is_verified', true)
            ->get();

        $result = [];
        foreach ($verifications as $v) {
            $result[$v->field_name] = [
                'value' => $v->field_value,
                'method' => $v->method?->value ?? $v->method,
                'method_label' => $v->method?->label() ?? self::getMethodLabel($v->method),
                'verified_at' => $v->updated_at->toIso8601String(),
                'metadata' => $v->metadata,
                'is_locked' => $v->is_locked ?? false,
            ];
        }

        return $result;
    }

    /**
     * Check if a specific field is verified for an applicant.
     */
    public static function isFieldVerified(string $applicantId, VerifiableField|string $field): bool
    {
        $fieldName = $field instanceof VerifiableField ? $field->value : $field;

        return static::where('applicant_id', $applicantId)
            ->where('field_name', $fieldName)
            ->where('is_verified', true)
            ->exists();
    }
}
