<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonIdentification;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for PersonIdentification model.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PersonIdentification>
 */
class PersonIdentificationFactory extends Factory
{
    protected $model = PersonIdentification::class;

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
            'type' => PersonIdentification::TYPE_CURP,
            'identifier_value' => $this->generateCurp(),
            'document_data' => null,
            'issued_at' => null,
            'expires_at' => null,
            'is_current' => true,
            'status' => PersonIdentification::STATUS_PENDING,
            'verified_at' => null,
            'verified_by' => null,
            'verification_method' => null,
            'verification_data' => null,
            'verification_confidence' => null,
            'previous_version_id' => null,
            'replaced_at' => null,
            'replacement_reason' => null,
            'notes' => null,
            'metadata' => null,
        ];
    }

    /**
     * CURP identification.
     */
    public function curp(): static
    {
        return $this->state(fn() => [
            'type' => PersonIdentification::TYPE_CURP,
            'identifier_value' => $this->generateCurp(),
            'issued_at' => null,
            'expires_at' => null,
        ]);
    }

    /**
     * RFC identification.
     */
    public function rfc(): static
    {
        return $this->state(fn() => [
            'type' => PersonIdentification::TYPE_RFC,
            'identifier_value' => $this->generateRfc(),
            'document_data' => [
                'homoclave' => strtoupper(fake()->lexify('???')),
                'regimen' => 'PERSONA_FISICA',
            ],
        ]);
    }

    /**
     * INE identification.
     */
    public function ine(): static
    {
        $year = fake()->numberBetween(2018, 2023);
        return $this->state(fn() => [
            'type' => PersonIdentification::TYPE_INE,
            'identifier_value' => fake()->numerify('##################'),
            'document_data' => [
                'cic' => fake()->numerify('############'),
                'clave_elector' => strtoupper(fake()->lexify('??????')) . fake()->numerify('############'),
                'ocr' => fake()->numerify('#############'),
                'folio' => fake()->numerify('##########'),
                'seccion' => fake()->numerify('####'),
                'emision' => $year,
                'vigencia' => $year + 10,
            ],
            'issued_at' => fake()->dateTimeBetween("-5 years", "now"),
            'expires_at' => fake()->dateTimeBetween("+1 year", "+10 years"),
        ]);
    }

    /**
     * Passport identification.
     */
    public function passport(): static
    {
        return $this->state(fn() => [
            'type' => PersonIdentification::TYPE_PASSPORT,
            'identifier_value' => strtoupper(fake()->lexify('?')) . fake()->numerify('########'),
            'document_data' => [
                'passport_number' => strtoupper(fake()->lexify('?')) . fake()->numerify('########'),
                'issuing_country' => 'MX',
                'issuing_authority' => 'SRE',
            ],
            'issued_at' => fake()->dateTimeBetween("-5 years", "now"),
            'expires_at' => fake()->dateTimeBetween("+1 year", "+10 years"),
        ]);
    }

    /**
     * Driver license identification.
     */
    public function driverLicense(): static
    {
        return $this->state(fn() => [
            'type' => PersonIdentification::TYPE_DRIVER_LICENSE,
            'identifier_value' => strtoupper(fake()->lexify('???')) . fake()->numerify('######'),
            'issued_at' => fake()->dateTimeBetween("-3 years", "now"),
            'expires_at' => fake()->dateTimeBetween("+1 year", "+5 years"),
        ]);
    }

    /**
     * Professional ID (Cédula Profesional).
     */
    public function professionalId(): static
    {
        return $this->state(fn() => [
            'type' => PersonIdentification::TYPE_PROFESSIONAL_ID,
            'identifier_value' => fake()->numerify('########'),
            'document_data' => [
                'career' => fake()->randomElement(['Medicina', 'Derecho', 'Ingeniería', 'Contaduría']),
                'institution' => fake()->company(),
            ],
        ]);
    }

    /**
     * Verified identification.
     */
    public function verified(): static
    {
        return $this->state(fn() => [
            'status' => PersonIdentification::STATUS_VERIFIED,
            'verified_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'verification_method' => fake()->randomElement(['MANUAL', 'OCR', 'API']),
            'verification_confidence' => fake()->randomFloat(2, 85, 100),
        ]);
    }

    /**
     * Pending identification.
     */
    public function pending(): static
    {
        return $this->state(fn() => [
            'status' => PersonIdentification::STATUS_PENDING,
            'verified_at' => null,
            'verification_method' => null,
        ]);
    }

    /**
     * Expired identification.
     */
    public function expired(): static
    {
        return $this->state(fn() => [
            'status' => PersonIdentification::STATUS_EXPIRED,
            'expires_at' => fake()->dateTimeBetween('-2 years', '-1 day'),
            'is_current' => false,
        ]);
    }

    /**
     * Superseded identification (replaced by newer version).
     */
    public function superseded(): static
    {
        return $this->state(fn() => [
            'status' => PersonIdentification::STATUS_SUPERSEDED,
            'is_current' => false,
            'replaced_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'replacement_reason' => fake()->randomElement(['RENEWED', 'CORRECTED', 'EXPIRED']),
        ]);
    }

    /**
     * Current identification.
     */
    public function current(): static
    {
        return $this->state(fn() => [
            'is_current' => true,
        ]);
    }

    /**
     * Not current (historical).
     */
    public function historical(): static
    {
        return $this->state(fn() => [
            'is_current' => false,
        ]);
    }

    /**
     * Generate a valid-format CURP.
     */
    private function generateCurp(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $vowels = 'AEIOU';
        $consonants = 'BCDFGHJKLMNPQRSTVWXYZ';
        $digits = '0123456789';
        $states = ['AS', 'BC', 'BS', 'CC', 'CL', 'CM', 'CS', 'CH', 'DF', 'DG', 'GT', 'GR', 'HG', 'JC', 'MC', 'MN', 'MS', 'NT', 'NL', 'OC', 'PL', 'QT', 'QR', 'SP', 'SL', 'SR', 'TC', 'TS', 'TL', 'VZ', 'YN', 'ZS', 'NE'];

        // Format: AAAA######AAAAAA##
        $curp = '';
        // First 4 letters (from name)
        $curp .= $letters[rand(0, 25)];
        $curp .= $vowels[rand(0, 4)];
        $curp .= $letters[rand(0, 25)];
        $curp .= $letters[rand(0, 25)];
        // Birth date (YYMMDD)
        $curp .= str_pad(rand(50, 99), 2, '0', STR_PAD_LEFT);
        $curp .= str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
        $curp .= str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
        // Gender
        $curp .= fake()->randomElement(['H', 'M']);
        // State
        $curp .= $states[array_rand($states)];
        // Internal consonants
        $curp .= $consonants[rand(0, 20)];
        $curp .= $consonants[rand(0, 20)];
        $curp .= $consonants[rand(0, 20)];
        // Homoclave
        $curp .= $digits[rand(0, 9)];
        $curp .= $digits[rand(0, 9)];

        return $curp;
    }

    /**
     * Generate a valid-format RFC (13 chars for persona física).
     */
    private function generateRfc(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';
        $alphanumeric = $letters . $digits;

        // Format: AAAA######AAA
        $rfc = '';
        // First 4 letters (from name)
        for ($i = 0; $i < 4; $i++) {
            $rfc .= $letters[rand(0, 25)];
        }
        // Birth date (YYMMDD)
        $rfc .= str_pad(rand(50, 99), 2, '0', STR_PAD_LEFT);
        $rfc .= str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
        $rfc .= str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
        // Homoclave (3 alphanumeric)
        for ($i = 0; $i < 3; $i++) {
            $rfc .= $alphanumeric[rand(0, strlen($alphanumeric) - 1)];
        }

        return $rfc;
    }
}
