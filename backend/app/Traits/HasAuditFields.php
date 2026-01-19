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
 * Note: Audit fields reference the `users` table. When a StaffAccount is authenticated
 * (via sanctum guard), we skip setting these fields since StaffAccount IDs are not
 * in the users table.
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
     * Check if the authenticated user is from the users table.
     * Staff accounts use a different table so we cannot use their IDs.
     */
    protected static function getAuditUserId(): ?string
    {
        if (!Auth::check()) {
            return null;
        }

        $user = Auth::user();

        // Only set audit fields if user is from the User model
        // StaffAccount, ApplicantAccount are different tables
        if ($user instanceof User) {
            return $user->id;
        }

        return null;
    }

    /**
     * Boot the trait.
     */
    protected static function bootHasAuditFields(): void
    {
        // Automatically set created_by when creating a new record
        static::creating(function (Model $model): void {
            $userId = static::getAuditUserId();
            if ($userId !== null && $model->created_by === null) {
                $model->created_by = $userId;
            }
        });

        // Automatically set updated_by when updating a record
        static::updating(function (Model $model): void {
            $userId = static::getAuditUserId();
            if ($userId !== null) {
                $model->updated_by = $userId;
            }
        });

        // Automatically set deleted_by when soft deleting a record
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class))) {
            static::deleting(function (Model $model): void {
                $userId = static::getAuditUserId();
                if ($userId !== null && $model->deleted_by === null && !$model->isForceDeleting()) {
                    $model->deleted_by = $userId;
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
