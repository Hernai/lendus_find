<?php

namespace Database\Factories;

use App\Enums\ReferenceType;
use App\Enums\Relationship;
use App\Models\Person;
use App\Models\PersonReference;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for PersonReference model.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PersonReference>
 */
class PersonReferenceFactory extends Factory
{
    protected $model = PersonReference::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'person_id' => Person::factory(),
            'application_id' => null,
            'type' => ReferenceType::PERSONAL->value,
            'first_name' => fake('es_MX')->firstName(),
            'last_name_1' => fake('es_MX')->lastName(),
            'last_name_2' => fake('es_MX')->lastName(),
            'phone' => fake()->numerify('55########'),
            'email' => fake()->optional(0.5)->safeEmail(),
            'relationship' => fake()->randomElement(Relationship::values()),
            'years_known' => fake()->numberBetween(1, 20),
            'status' => PersonReference::STATUS_PENDING,
            'verified_at' => null,
            'verified_by' => null,
            'verification_notes' => null,
            'contact_attempts' => null,
            'notes' => null,
            'metadata' => null,
        ];
    }

    /**
     * Personal reference.
     */
    public function personal(): static
    {
        return $this->state(fn() => [
            'type' => ReferenceType::PERSONAL->value,
            'relationship' => fake()->randomElement([
                Relationship::FRIEND->value,
                Relationship::NEIGHBOR->value,
                Relationship::ACQUAINTANCE->value,
            ]),
        ]);
    }

    /**
     * Work reference.
     */
    public function work(): static
    {
        return $this->state(fn() => [
            'type' => ReferenceType::WORK->value,
            'relationship' => fake()->randomElement([
                Relationship::COWORKER->value,
                Relationship::BOSS->value,
            ]),
        ]);
    }

    /**
     * Family reference.
     */
    public function family(): static
    {
        return $this->state(fn() => [
            'type' => ReferenceType::PERSONAL->value,
            'relationship' => fake()->randomElement([
                Relationship::PARENT->value,
                Relationship::SIBLING->value,
                Relationship::SPOUSE->value,
                Relationship::UNCLE_AUNT->value,
                Relationship::COUSIN->value,
            ]),
        ]);
    }

    /**
     * Verified reference.
     */
    public function verified(): static
    {
        return $this->state(fn() => [
            'status' => PersonReference::STATUS_VERIFIED,
            'verified_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'verification_notes' => 'Reference confirmed applicant information',
            'contact_attempts' => [
                [
                    'date' => now()->subDays(2)->toDateString(),
                    'time' => '10:30',
                    'result' => 'no_answer',
                    'notes' => null,
                    'by' => null,
                ],
                [
                    'date' => now()->subDays(1)->toDateString(),
                    'time' => '14:00',
                    'result' => 'verified',
                    'notes' => 'Confirmed relationship and employment',
                    'by' => null,
                ],
            ],
        ]);
    }

    /**
     * Pending reference.
     */
    public function pending(): static
    {
        return $this->state(fn() => [
            'status' => PersonReference::STATUS_PENDING,
            'verified_at' => null,
            'verification_notes' => null,
            'contact_attempts' => null,
        ]);
    }

    /**
     * Unreachable reference.
     */
    public function unreachable(): static
    {
        return $this->state(fn() => [
            'status' => PersonReference::STATUS_UNREACHABLE,
            'verification_notes' => 'Multiple contact attempts failed',
            'contact_attempts' => [
                [
                    'date' => now()->subDays(3)->toDateString(),
                    'time' => '10:00',
                    'result' => 'no_answer',
                    'notes' => null,
                    'by' => null,
                ],
                [
                    'date' => now()->subDays(2)->toDateString(),
                    'time' => '15:00',
                    'result' => 'no_answer',
                    'notes' => null,
                    'by' => null,
                ],
                [
                    'date' => now()->subDays(1)->toDateString(),
                    'time' => '11:00',
                    'result' => 'no_answer',
                    'notes' => 'Marked as unreachable after 3 attempts',
                    'by' => null,
                ],
            ],
        ]);
    }

    /**
     * Rejected reference.
     */
    public function rejected(): static
    {
        return $this->state(fn() => [
            'status' => PersonReference::STATUS_REJECTED,
            'verification_notes' => 'Reference could not confirm applicant information',
        ]);
    }

    /**
     * No answer status.
     */
    public function noAnswer(): static
    {
        return $this->state(fn() => [
            'status' => PersonReference::STATUS_NO_ANSWER,
            'contact_attempts' => [
                [
                    'date' => now()->subDays(1)->toDateString(),
                    'time' => '10:30',
                    'result' => 'no_answer',
                    'notes' => null,
                    'by' => null,
                ],
            ],
        ]);
    }

    /**
     * With contact attempts.
     */
    public function withContactAttempts(int $count = 2): static
    {
        $attempts = [];
        for ($i = $count; $i >= 1; $i--) {
            $attempts[] = [
                'date' => now()->subDays($i)->toDateString(),
                'time' => fake()->time('H:i'),
                'result' => $i === 1 ? 'answered' : 'no_answer',
                'notes' => $i === 1 ? 'Spoke with reference' : null,
                'by' => null,
            ];
        }

        return $this->state(fn() => [
            'contact_attempts' => $attempts,
        ]);
    }

    /**
     * Long-known reference (10+ years).
     */
    public function longKnown(): static
    {
        return $this->state(fn() => [
            'years_known' => fake()->numberBetween(10, 30),
        ]);
    }

    /**
     * Recently known reference (1-2 years).
     */
    public function recentlyKnown(): static
    {
        return $this->state(fn() => [
            'years_known' => fake()->numberBetween(1, 2),
        ]);
    }

    /**
     * Friend relationship.
     */
    public function friend(): static
    {
        return $this->state(fn() => [
            'type' => ReferenceType::PERSONAL->value,
            'relationship' => Relationship::FRIEND->value,
        ]);
    }

    /**
     * Coworker relationship.
     */
    public function coworker(): static
    {
        return $this->state(fn() => [
            'type' => ReferenceType::WORK->value,
            'relationship' => Relationship::COWORKER->value,
        ]);
    }

    /**
     * Boss relationship.
     */
    public function boss(): static
    {
        return $this->state(fn() => [
            'type' => ReferenceType::WORK->value,
            'relationship' => Relationship::BOSS->value,
        ]);
    }

    /**
     * Sibling relationship.
     */
    public function sibling(): static
    {
        return $this->state(fn() => [
            'type' => ReferenceType::PERSONAL->value,
            'relationship' => Relationship::SIBLING->value,
        ]);
    }

    /**
     * Parent relationship.
     */
    public function parent(): static
    {
        return $this->state(fn() => [
            'type' => ReferenceType::PERSONAL->value,
            'relationship' => Relationship::PARENT->value,
        ]);
    }

    /**
     * Spouse relationship.
     */
    public function spouse(): static
    {
        return $this->state(fn() => [
            'type' => ReferenceType::PERSONAL->value,
            'relationship' => Relationship::SPOUSE->value,
        ]);
    }
}
