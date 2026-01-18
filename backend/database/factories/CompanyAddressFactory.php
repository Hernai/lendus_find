<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CompanyAddress>
 */
class CompanyAddressFactory extends Factory
{
    protected $model = CompanyAddress::class;

    public function definition(): array
    {
        $states = ['AGS', 'BC', 'BCS', 'CAM', 'CHIS', 'CHIH', 'COAH', 'COL', 'CDMX', 'DGO', 'GTO', 'GRO', 'HGO', 'JAL', 'MEX', 'MICH', 'MOR', 'NAY', 'NL', 'OAX', 'PUE', 'QRO', 'QROO', 'SLP', 'SIN', 'SON', 'TAB', 'TAMPS', 'TLAX', 'VER', 'YUC', 'ZAC'];

        return [
            'tenant_id' => Tenant::factory(),
            'company_id' => Company::factory(),
            'type' => CompanyAddress::TYPE_FISCAL,
            'street' => fake()->streetName(),
            'exterior_number' => fake()->buildingNumber(),
            'interior_number' => fake()->optional(0.3)->numerify('##'),
            'neighborhood' => fake()->citySuffix() . ' ' . fake()->lastName(),
            'municipality' => fake()->city(),
            'city' => fake()->city(),
            'state' => fake()->randomElement($states),
            'postal_code' => fake()->numerify('#####'),
            'country' => 'MX',
            'between_streets' => fake()->optional()->streetName() . ' y ' . fake()->streetName(),
            'references' => fake()->optional()->sentence(),
            'latitude' => fake()->latitude(14, 32),
            'longitude' => fake()->longitude(-117, -86),
            'valid_from' => now()->subYear(),
            'is_current' => true,
            'status' => CompanyAddress::STATUS_PENDING,
        ];
    }

    /**
     * Fiscal address.
     */
    public function fiscal(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => CompanyAddress::TYPE_FISCAL,
        ]);
    }

    /**
     * Headquarters address.
     */
    public function headquarters(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => CompanyAddress::TYPE_HEADQUARTERS,
        ]);
    }

    /**
     * Branch address.
     */
    public function branch(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => CompanyAddress::TYPE_BRANCH,
        ]);
    }

    /**
     * Warehouse address.
     */
    public function warehouse(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => CompanyAddress::TYPE_WAREHOUSE,
        ]);
    }

    /**
     * Verified address.
     */
    public function verified(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => CompanyAddress::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
    }

    /**
     * Historical (not current) address.
     */
    public function historical(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_current' => false,
            'valid_until' => now()->subMonth(),
            'replaced_at' => now()->subMonth(),
        ]);
    }

    /**
     * CDMX address.
     */
    public function cdmx(): static
    {
        return $this->state(fn(array $attributes) => [
            'state' => 'CDMX',
            'municipality' => fake()->randomElement([
                'Benito Juárez',
                'Miguel Hidalgo',
                'Cuauhtémoc',
                'Coyoacán',
                'Álvaro Obregón',
            ]),
            'latitude' => fake()->latitude(19.3, 19.5),
            'longitude' => fake()->longitude(-99.2, -99.0),
        ]);
    }
}
