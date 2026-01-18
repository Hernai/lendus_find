<?php

namespace Database\Factories;

use App\Enums\HousingType;
use App\Models\Person;
use App\Models\PersonAddress;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for PersonAddress model.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PersonAddress>
 */
class PersonAddressFactory extends Factory
{
    protected $model = PersonAddress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $yearsAtAddress = fake()->numberBetween(0, 20);
        $monthsAtAddress = fake()->numberBetween(0, 11);

        return [
            'tenant_id' => Tenant::factory(),
            'person_id' => Person::factory(),
            'type' => PersonAddress::TYPE_HOME,
            'street' => fake('es_MX')->streetName(),
            'exterior_number' => (string) fake()->numberBetween(1, 999),
            'interior_number' => fake()->optional(0.3)->numberBetween(1, 20),
            'neighborhood' => fake('es_MX')->city() . ' ' . fake()->randomElement(['Centro', 'Norte', 'Sur', 'Poniente', 'Oriente']),
            'municipality' => fake('es_MX')->city(),
            'city' => fake()->optional(0.5)->city(),
            'state' => fake()->randomElement(['CDMX', 'JAL', 'NL', 'MEX', 'GTO', 'PUE', 'VER', 'YUC', 'QRO', 'AGS']),
            'postal_code' => fake()->numerify('#####'),
            'country' => 'MX',
            'between_streets' => fake()->optional(0.5)->streetName() . ' y ' . fake()->streetName(),
            'references' => fake()->optional(0.3)->sentence(),
            'latitude' => fake()->optional(0.2)->latitude(14.5, 32.7),
            'longitude' => fake()->optional(0.2)->longitude(-118.4, -86.7),
            'geocode_accuracy' => null,
            'valid_from' => fake()->dateTimeBetween('-10 years', '-1 year'),
            'valid_until' => null,
            'is_current' => true,
            'years_at_address' => $yearsAtAddress,
            'months_at_address' => $monthsAtAddress,
            'housing_type' => fake()->randomElement(HousingType::values()),
            'monthly_rent' => null,
            'status' => PersonAddress::STATUS_PENDING,
            'verified_at' => null,
            'verified_by' => null,
            'verification_method' => null,
            'verification_data' => null,
            'previous_version_id' => null,
            'replaced_at' => null,
            'replacement_reason' => null,
            'notes' => null,
            'metadata' => null,
        ];
    }

    /**
     * Home address.
     */
    public function home(): static
    {
        return $this->state(fn() => [
            'type' => PersonAddress::TYPE_HOME,
        ]);
    }

    /**
     * Work address.
     */
    public function work(): static
    {
        return $this->state(fn() => [
            'type' => PersonAddress::TYPE_WORK,
            'housing_type' => null,
            'monthly_rent' => null,
        ]);
    }

    /**
     * Fiscal address.
     */
    public function fiscal(): static
    {
        return $this->state(fn() => [
            'type' => PersonAddress::TYPE_FISCAL,
        ]);
    }

    /**
     * Verified address.
     */
    public function verified(): static
    {
        return $this->state(fn() => [
            'status' => PersonAddress::STATUS_VERIFIED,
            'verified_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'verification_method' => fake()->randomElement(['DOCUMENT', 'GEOLOCATION', 'VISIT', 'INE_MATCH']),
        ]);
    }

    /**
     * Pending address.
     */
    public function pending(): static
    {
        return $this->state(fn() => [
            'status' => PersonAddress::STATUS_PENDING,
            'verified_at' => null,
            'verification_method' => null,
        ]);
    }

    /**
     * Rejected address.
     */
    public function rejected(): static
    {
        return $this->state(fn() => [
            'status' => PersonAddress::STATUS_REJECTED,
            'notes' => 'Address verification failed',
        ]);
    }

    /**
     * Current address.
     */
    public function current(): static
    {
        return $this->state(fn() => [
            'is_current' => true,
            'valid_until' => null,
        ]);
    }

    /**
     * Historical (not current) address.
     */
    public function historical(): static
    {
        return $this->state(fn() => [
            'is_current' => false,
            'valid_until' => fake()->dateTimeBetween('-2 years', '-1 month'),
            'replaced_at' => fake()->dateTimeBetween('-2 years', '-1 month'),
            'replacement_reason' => 'MOVED',
        ]);
    }

    /**
     * Owned housing.
     */
    public function owned(): static
    {
        return $this->state(fn() => [
            'housing_type' => HousingType::OWNED_PAID->value,
            'monthly_rent' => null,
        ]);
    }

    /**
     * Rented housing.
     */
    public function rented(float $rent = null): static
    {
        return $this->state(fn() => [
            'housing_type' => HousingType::RENTED->value,
            'monthly_rent' => $rent ?? fake()->randomFloat(2, 3000, 25000),
        ]);
    }

    /**
     * Family housing.
     */
    public function familyHousing(): static
    {
        return $this->state(fn() => [
            'housing_type' => HousingType::FAMILY->value,
            'monthly_rent' => null,
        ]);
    }

    /**
     * Mortgaged housing.
     */
    public function mortgaged(): static
    {
        return $this->state(fn() => [
            'housing_type' => HousingType::OWNED_MORTGAGE->value,
            'monthly_rent' => null,
        ]);
    }

    /**
     * With geolocation.
     */
    public function withGeolocation(): static
    {
        return $this->state(fn() => [
            'latitude' => fake()->latitude(14.5, 32.7),
            'longitude' => fake()->longitude(-118.4, -86.7),
            'geocode_accuracy' => fake()->randomElement(['ROOFTOP', 'RANGE_INTERPOLATED', 'GEOMETRIC_CENTER', 'APPROXIMATE']),
        ]);
    }

    /**
     * Long residence (5+ years).
     */
    public function longResidence(): static
    {
        $years = fake()->numberBetween(5, 20);
        return $this->state(fn() => [
            'years_at_address' => $years,
            'months_at_address' => fake()->numberBetween(0, 11),
            'valid_from' => now()->subYears($years)->subMonths(fake()->numberBetween(0, 11)),
        ]);
    }

    /**
     * Short residence (less than 1 year).
     */
    public function shortResidence(): static
    {
        $months = fake()->numberBetween(1, 11);
        return $this->state(fn() => [
            'years_at_address' => 0,
            'months_at_address' => $months,
            'valid_from' => now()->subMonths($months),
        ]);
    }

    /**
     * Mexico City address.
     */
    public function cdmx(): static
    {
        $colonias = ['Polanco', 'Condesa', 'Roma Norte', 'Del Valle', 'Coyoacán', 'Santa Fe', 'Nápoles', 'Insurgentes'];
        $delegaciones = ['Miguel Hidalgo', 'Cuauhtémoc', 'Benito Juárez', 'Coyoacán', 'Álvaro Obregón', 'Tlalpan'];

        return $this->state(fn() => [
            'state' => 'CDMX',
            'neighborhood' => fake()->randomElement($colonias),
            'municipality' => fake()->randomElement($delegaciones),
            'city' => 'Ciudad de México',
            'postal_code' => '0' . fake()->numerify('####'),
        ]);
    }
}
