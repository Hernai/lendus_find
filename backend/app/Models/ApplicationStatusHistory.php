<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Application status history model.
 *
 * Tracks all status changes for an application with who made the change.
 */
class ApplicationStatusHistory extends Model
{
    use HasFactory, HasUuids;

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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->created_at) {
                $model->created_at = now();
            }
        });
    }

    // =====================================================
    // Relationships
    // =====================================================

    public function application(): BelongsTo
    {
        return $this->belongsTo(ApplicationV2::class, 'application_id');
    }

    // =====================================================
    // Accessors
    // =====================================================

    public function getFromStatusLabelAttribute(): ?string
    {
        return $this->from_status ? (ApplicationV2::statuses()[$this->from_status] ?? $this->from_status) : null;
    }

    public function getToStatusLabelAttribute(): string
    {
        return ApplicationV2::statuses()[$this->to_status] ?? $this->to_status;
    }

    public function getChangedByNameAttribute(): ?string
    {
        if (!$this->changed_by) {
            return 'Sistema';
        }

        if ($this->changed_by_type === StaffAccount::class) {
            $staff = StaffAccount::find($this->changed_by);
            return $staff?->profile?->full_name ?? $staff?->email;
        }

        if ($this->changed_by_type === ApplicantAccount::class) {
            $account = ApplicantAccount::find($this->changed_by);
            return $account?->person?->full_name ?? 'Solicitante';
        }

        return null;
    }
}
