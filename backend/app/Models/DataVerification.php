<?php

namespace App\Models;

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
        'metadata',
        'verified_by',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'metadata' => 'array',
        'rejected_at' => 'datetime',
        'corrected_at' => 'datetime',
    ];

    /**
     * Verification statuses.
     */
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_CORRECTED = 'CORRECTED';

    /**
     * Verification methods.
     */
    public const METHOD_MANUAL = 'MANUAL';
    public const METHOD_OTP = 'OTP';
    public const METHOD_API = 'API';
    public const METHOD_DOCUMENT = 'DOCUMENT';
    public const METHOD_BUREAU = 'BUREAU';

    /**
     * Verifiable fields.
     */
    public const FIELD_FIRST_NAME = 'first_name';
    public const FIELD_LAST_NAME_1 = 'last_name_1';
    public const FIELD_LAST_NAME_2 = 'last_name_2';
    public const FIELD_CURP = 'curp';
    public const FIELD_RFC = 'rfc';
    public const FIELD_INE = 'ine_clave';
    public const FIELD_BIRTH_DATE = 'birth_date';
    public const FIELD_PHONE = 'phone';
    public const FIELD_EMAIL = 'email';
    public const FIELD_ADDRESS = 'address';
    public const FIELD_EMPLOYMENT = 'employment';

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
        $labels = [
            self::FIELD_FIRST_NAME => 'Nombre',
            self::FIELD_LAST_NAME_1 => 'Apellido Paterno',
            self::FIELD_LAST_NAME_2 => 'Apellido Materno',
            self::FIELD_CURP => 'CURP',
            self::FIELD_RFC => 'RFC',
            self::FIELD_INE => 'Clave INE',
            self::FIELD_BIRTH_DATE => 'Fecha de Nacimiento',
            self::FIELD_PHONE => 'Teléfono',
            self::FIELD_EMAIL => 'Email',
            self::FIELD_ADDRESS => 'Domicilio',
            self::FIELD_EMPLOYMENT => 'Empleo',
        ];

        return $labels[$field] ?? $field;
    }

    /**
     * Get method label in Spanish.
     */
    public static function getMethodLabel(string $method): string
    {
        $labels = [
            self::METHOD_MANUAL => 'Manual',
            self::METHOD_OTP => 'Código OTP',
            self::METHOD_API => 'API Externa',
            self::METHOD_DOCUMENT => 'Documento',
            self::METHOD_BUREAU => 'Buró',
        ];

        return $labels[$method] ?? $method;
    }

    /**
     * Get status label in Spanish.
     */
    public static function getStatusLabel(string $status): string
    {
        $labels = [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_VERIFIED => 'Verificado',
            self::STATUS_REJECTED => 'Rechazado',
            self::STATUS_CORRECTED => 'Corregido',
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Check if this field is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if this field needs correction.
     */
    public function needsCorrection(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Reject this field with a reason.
     */
    public function reject(string $reason, ?int $userId = null): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejection_reason = $reason;
        $this->rejected_at = now();
        $this->verified_by = $userId;
        $this->save();
    }

    /**
     * Mark as corrected (user submitted new value).
     */
    public function markCorrected(): void
    {
        $this->status = self::STATUS_CORRECTED;
        $this->corrected_at = now();
        $this->save();
    }

    /**
     * Verify this field.
     */
    public function verify(?string $method = null, ?int $userId = null, ?string $notes = null): void
    {
        $this->status = self::STATUS_VERIFIED;
        $this->is_verified = true;
        $this->method = $method ?? self::METHOD_MANUAL;
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
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope to get pending fields.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get verified fields.
     */
    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }
}
