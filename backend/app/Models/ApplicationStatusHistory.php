<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Application status history model.
 *
 * Tracks all status changes for Application records.
 */
class ApplicationStatusHistory extends Model
{
    use HasUuids;

    protected $table = 'application_status_history';

    public $timestamps = false;

    protected $fillable = [
        'application_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_by_type',
        'notes',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the application this history belongs to.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    /**
     * Get the user who made this change (polymorphic).
     */
    public function changedBy(): BelongsTo
    {
        if ($this->changed_by_type === StaffAccount::class || $this->changed_by_type === 'staff_accounts') {
            return $this->belongsTo(StaffAccount::class, 'changed_by');
        }

        if ($this->changed_by_type === ApplicantAccount::class || $this->changed_by_type === 'applicant_accounts') {
            return $this->belongsTo(ApplicantAccount::class, 'changed_by');
        }

        // Fallback to staff
        return $this->belongsTo(StaffAccount::class, 'changed_by');
    }

    /**
     * Get the name of who made the change.
     */
    public function getChangedByNameAttribute(): ?string
    {
        if (!$this->changed_by) {
            return 'Sistema';
        }

        $user = $this->changedBy;
        if ($user instanceof StaffAccount) {
            return $user->profile?->full_name ?? $user->email;
        }
        if ($user instanceof ApplicantAccount) {
            return $user->person?->full_name ?? $user->email;
        }

        return 'Usuario';
    }

    /**
     * Get status label for from_status.
     */
    public function getFromStatusLabelAttribute(): ?string
    {
        if (!$this->from_status) {
            return null;
        }
        return Application::statuses()[$this->from_status] ?? $this->from_status;
    }

    /**
     * Get status label for to_status.
     */
    public function getToStatusLabelAttribute(): string
    {
        return Application::statuses()[$this->to_status] ?? $this->to_status;
    }

    /**
     * Create a history entry for a status change.
     */
    public static function record(
        Application $application,
        ?string $fromStatus,
        string $toStatus,
        ?string $changedById = null,
        ?string $changedByType = null,
        ?string $notes = null,
        ?array $metadata = null
    ): self {
        return static::create([
            'application_id' => $application->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $changedById,
            'changed_by_type' => $changedByType,
            'notes' => $notes,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
