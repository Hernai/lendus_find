<?php

namespace App\Enums\Traits;

/**
 * Trait for converting enums to frontend-friendly options.
 *
 * Requires the enum to have a label() method.
 *
 * @method string label()
 */
trait HasOptions
{
    /**
     * Get all enum cases as options array for frontend selects.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function toOptions(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }

    /**
     * Get a map of value => label for all cases.
     *
     * @return array<string, string>
     */
    public static function toLabels(): array
    {
        $labels = [];
        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }
        return $labels;
    }
}
