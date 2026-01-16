<?php

namespace App\Models;

use App\Enums\ReferenceType;
use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reference extends Model
{
    use HasFactory, HasUuid, HasTenant, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'tenant_id',
        'applicant_id',
        'application_id',
        'first_name',
        'last_name_1',
        'last_name_2',
        'full_name',
        'phone',
        'email',
        'relationship',
        'type',
        'is_verified',
        'verified_at',
        'verified_by',
        'verification_notes',
        'contact_attempts',
    ];

    protected $casts = [
        'type' => ReferenceType::class,
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'contact_attempts' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate full name
        static::saving(function ($reference) {
            $reference->full_name = trim(implode(' ', array_filter([
                $reference->first_name,
                $reference->last_name_1,
                $reference->last_name_2,
            ])));
        });
    }

    /**
     * Get the applicant.
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * Get the application.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the verifier user.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Mark as verified.
     */
    public function verify(int $userId, ?string $notes = null): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $userId,
            'verification_notes' => $notes,
        ]);
    }

    /**
     * Record a contact attempt.
     */
    public function recordContactAttempt(string $method, string $result, ?string $notes = null): void
    {
        $attempts = $this->contact_attempts ?? [];

        $attempts[] = [
            'method' => $method,
            'result' => $result,
            'notes' => $notes,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->update(['contact_attempts' => $attempts]);
    }

    /**
     * Scope by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to unverified references.
     */
    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false);
    }
}
