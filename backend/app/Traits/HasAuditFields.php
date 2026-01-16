<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Trait to automatically populate audit fields (created_by, updated_by, deleted_by)
 * based on the currently authenticated user.
 *
 * Usage: Add this trait to any model that has audit fields.
 *
 * Example:
 * ```php
 * class Application extends Model
 * {
 *     use HasAuditFields;
 * }
 * ```
 */
trait HasAuditFields
{
    /**
     * Boot the trait.
     */
    protected static function bootHasAuditFields(): void
    {
        // Automatically set created_by when creating a new record
        static::creating(function (Model $model): void {
            if (Auth::check() && $model->created_by === null) {
                $model->created_by = Auth::id();
            }
        });

        // Automatically set updated_by when updating a record
        static::updating(function (Model $model): void {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        // Automatically set deleted_by when soft deleting a record
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class))) {
            static::deleting(function (Model $model): void {
                if (Auth::check() && $model->deleted_by === null && !$model->isForceDeleting()) {
                    $model->deleted_by = Auth::id();
                    $model->saveQuietly();
                }
            });
        }
    }

    /**
     * Get the user who created this record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who deleted this record.
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Get the name of the user who created this record.
     */
    public function getCreatedByNameAttribute(): ?string
    {
        return $this->creator?->name ?? 'Sistema';
    }

    /**
     * Get the name of the user who last updated this record.
     */
    public function getUpdatedByNameAttribute(): ?string
    {
        return $this->updater?->name ?? null;
    }

    /**
     * Get the name of the user who deleted this record.
     */
    public function getDeletedByNameAttribute(): ?string
    {
        return $this->deleter?->name ?? null;
    }
}
