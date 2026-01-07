<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApplicationNote extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'application_id',
        'user_id',
        'content',
        'is_internal',
        'type',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    /**
     * Note types.
     */
    public const TYPE_NOTE = 'NOTE';
    public const TYPE_STATUS_CHANGE = 'STATUS_CHANGE';
    public const TYPE_CALL = 'CALL';
    public const TYPE_EMAIL = 'EMAIL';
    public const TYPE_SYSTEM = 'SYSTEM';

    /**
     * Get the application.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the user who created the note.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to internal notes only.
     */
    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    /**
     * Scope to public notes (visible to applicant).
     */
    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }
}
