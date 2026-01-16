<?php

namespace App\Traits;

/**
 * Trait for models that have first_name, last_name_1, last_name_2 fields.
 *
 * Automatically generates full_name on create/update and provides
 * a consistent accessor for getting the formatted full name.
 */
trait HasFullName
{
    /**
     * Boot the trait.
     */
    public static function bootHasFullName(): void
    {
        static::creating(function ($model) {
            $model->updateFullName();
        });

        static::updating(function ($model) {
            $model->updateFullName();
        });
    }

    /**
     * Update the full_name field based on name components.
     */
    protected function updateFullName(): void
    {
        if ($this->isDirty(['first_name', 'last_name_1', 'last_name_2']) ||
            empty($this->full_name)) {
            $this->full_name = $this->buildFullName();
        }
    }

    /**
     * Build the full name from components.
     */
    protected function buildFullName(): string
    {
        return implode(' ', array_filter([
            $this->first_name ?? null,
            $this->last_name_1 ?? null,
            $this->last_name_2 ?? null,
        ]));
    }

    /**
     * Get the full name accessor.
     * Returns the stored full_name or builds it on the fly.
     */
    public function getFullNameAttribute(): string
    {
        if (!empty($this->attributes['full_name'])) {
            return $this->attributes['full_name'];
        }

        return $this->buildFullName();
    }

    /**
     * Get the formatted name for display (First LastName).
     */
    public function getDisplayNameAttribute(): string
    {
        return implode(' ', array_filter([
            $this->first_name ?? null,
            $this->last_name_1 ?? null,
        ]));
    }

    /**
     * Get initials (e.g., "JG" for "Juan García").
     */
    public function getInitialsAttribute(): string
    {
        $parts = array_filter([
            $this->first_name ?? null,
            $this->last_name_1 ?? null,
        ]);

        return implode('', array_map(
            fn($part) => mb_strtoupper(mb_substr($part, 0, 1)),
            $parts
        ));
    }
}
