<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Staff profile containing personal information.
 *
 * Separated from StaffAccount to follow single responsibility principle.
 * Profile data can change independently of authentication data.
 */
class StaffProfile extends Model
{
    use HasFactory, HasUuids, HasAuditFields;

    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'last_name_2',
        'phone',
        'avatar_url',
        'title',
        'preferences',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
        ];
    }

    // =====================================================
    // Relationships
    // =====================================================

    public function account(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'account_id');
    }

    // =====================================================
    // Accessors
    // =====================================================

    /**
     * Get the full name combining first_name, last_name, and last_name_2.
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->last_name,
            $this->last_name_2,
        ]);

        return implode(' ', $parts);
    }

    /**
     * Get initials for avatar placeholder.
     */
    public function getInitialsAttribute(): string
    {
        $initials = '';

        if ($this->first_name) {
            $initials .= strtoupper(substr($this->first_name, 0, 1));
        }

        if ($this->last_name) {
            $initials .= strtoupper(substr($this->last_name, 0, 1));
        }

        return $initials ?: 'U';
    }

    // =====================================================
    // Preference Helpers
    // =====================================================

    /**
     * Get a specific preference value.
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences, $key, $default);
    }

    /**
     * Set a specific preference value.
     */
    public function setPreference(string $key, mixed $value): void
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        $this->preferences = $preferences;
        $this->save();
    }
}
