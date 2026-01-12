<?php

namespace App\Models;

use App\Enums\VerifiableField;
use App\Enums\VerificationMethod;
use App\Enums\VerificationStatus;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataVerification extends Model
{
    use HasFactory, HasUuid, HasTenant;

    protected $fillable = [
        'tenant_id',
        'applicant_id',
        'field_name',
        'field_value',
        'method',
        'is_verified',
        'notes',
        'rejection_reason',
        'status',
        'rejected_at',
        'corrected_at',
        'correction_history',
        'metadata',
        'verified_by',
    ];

    protected $casts = [
        'status' => VerificationStatus::class,
        'method' => VerificationMethod::class,
        'is_verified' => 'boolean',
        'metadata' => 'array',
        'correction_history' => 'array',
        'rejected_at' => 'datetime',
        'corrected_at' => 'datetime',
    ];

    /**
     * Get the applicant.
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * Get the user who verified.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
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

        return static::updateOrCreate(
            [
                'applicant_id' => $applicantId,
                'field_name' => $fieldName,
            ],
            [
                'field_value' => is_array($value) ? json_encode($value) : (string) $value,
                'method' => $methodValue,
                'is_verified' => $isVerified,
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
     * @return array<string, array{value: string, method: string, verified_at: string, metadata: array|null}>
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
