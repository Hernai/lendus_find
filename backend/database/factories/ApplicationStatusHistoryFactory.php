<?php

namespace Database\Factories;

use App\Models\ApplicationStatusHistory;
use App\Models\ApplicationV2;
use App\Models\StaffAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApplicationStatusHistory>
 */
class ApplicationStatusHistoryFactory extends Factory
{
    protected $model = ApplicationStatusHistory::class;

    public function definition(): array
    {
        return [
            'application_id' => ApplicationV2::factory(),
            'from_status' => ApplicationV2::STATUS_DRAFT,
            'to_status' => ApplicationV2::STATUS_SUBMITTED,
            'changed_by' => null,
            'changed_by_type' => null,
            'notes' => null,
            'metadata' => null,
            'created_at' => now(),
        ];
    }

    /**
     * Changed by staff.
     */
    public function byStaff(): static
    {
        return $this->state(fn(array $attributes) => [
            'changed_by' => StaffAccount::factory(),
            'changed_by_type' => StaffAccount::class,
        ]);
    }

    /**
     * Changed by system.
     */
    public function bySystem(): static
    {
        return $this->state(fn(array $attributes) => [
            'changed_by' => null,
            'changed_by_type' => 'system',
        ]);
    }

    /**
     * With notes.
     */
    public function withNotes(string $notes = null): static
    {
        return $this->state(fn(array $attributes) => [
            'notes' => $notes ?? fake()->sentence(),
        ]);
    }

    /**
     * Submission transition.
     */
    public function submitted(): static
    {
        return $this->state(fn(array $attributes) => [
            'from_status' => ApplicationV2::STATUS_DRAFT,
            'to_status' => ApplicationV2::STATUS_SUBMITTED,
        ]);
    }

    /**
     * Approval transition.
     */
    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'from_status' => ApplicationV2::STATUS_IN_REVIEW,
            'to_status' => ApplicationV2::STATUS_APPROVED,
        ]);
    }

    /**
     * Rejection transition.
     */
    public function rejected(): static
    {
        return $this->state(fn(array $attributes) => [
            'from_status' => ApplicationV2::STATUS_IN_REVIEW,
            'to_status' => ApplicationV2::STATUS_REJECTED,
        ]);
    }
}
