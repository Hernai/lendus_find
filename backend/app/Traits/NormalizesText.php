<?php

namespace App\Traits;

/**
 * Trait for normalizing text fields to uppercase.
 *
 * This trait provides automatic uppercase conversion for specified fields,
 * following the DRY principle to avoid repeating strtoupper() in controllers.
 *
 * Usage:
 * 1. Add the trait to your model: `use NormalizesText;`
 * 2. Define the fields to normalize: `protected array $uppercaseFields = ['name', 'city'];`
 */
trait NormalizesText
{
    /**
     * Boot the trait - registers event listener.
     */
    public static function bootNormalizesText(): void
    {
        static::saving(function ($model) {
            $model->normalizeTextFields();
        });
    }

    /**
     * Normalize text fields to uppercase.
     */
    protected function normalizeTextFields(): void
    {
        $fields = $this->getUppercaseFields();

        foreach ($fields as $field) {
            if (isset($this->attributes[$field]) && is_string($this->attributes[$field])) {
                $this->attributes[$field] = mb_strtoupper($this->attributes[$field], 'UTF-8');
            }
        }
    }

    /**
     * Get the fields that should be converted to uppercase.
     *
     * Override this in your model if you prefer a method over a property.
     *
     * @return array
     */
    protected function getUppercaseFields(): array
    {
        return $this->uppercaseFields ?? [];
    }
}
