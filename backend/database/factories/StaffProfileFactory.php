<?php

namespace Database\Factories;

use App\Models\StaffAccount;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for StaffProfile model.
 *
 * @extends Factory<StaffProfile>
 */
class StaffProfileFactory extends Factory
{
    protected $model = StaffProfile::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'account_id' => StaffAccount::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'last_name_2' => fake()->optional(0.7)->lastName(),
            'phone' => '+52' . fake()->numerify('55########'),
            'avatar_url' => null,
            'title' => fake()->optional()->jobTitle(),
            'preferences' => [
                'theme' => 'light',
                'notifications' => [
                    'email' => true,
                    'push' => false,
                ],
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Set a specific job title.
     */
    public function withTitle(string $title): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $title,
        ]);
    }

    /**
     * Set an avatar URL.
     */
    public function withAvatar(string $url = null): static
    {
        return $this->state(fn (array $attributes) => [
            'avatar_url' => $url ?? fake()->imageUrl(200, 200, 'people'),
        ]);
    }

    /**
     * Set dark theme preference.
     */
    public function darkTheme(): static
    {
        return $this->state(fn (array $attributes) => [
            'preferences' => array_merge($attributes['preferences'] ?? [], [
                'theme' => 'dark',
            ]),
        ]);
    }
}
