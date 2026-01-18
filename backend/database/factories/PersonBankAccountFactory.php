<?php

namespace Database\Factories;

use App\Enums\BankAccountType;
use App\Models\Person;
use App\Models\PersonBankAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for PersonBankAccount model.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PersonBankAccount>
 */
class PersonBankAccountFactory extends Factory
{
    protected $model = PersonBankAccount::class;

    /**
     * Mexican banks with their SPEI codes.
     */
    private const BANKS = [
        '002' => 'Banamex',
        '012' => 'BBVA México',
        '014' => 'Santander',
        '021' => 'HSBC',
        '036' => 'Inbursa',
        '044' => 'Scotiabank',
        '072' => 'Banorte',
        '058' => 'Banregio',
        '137' => 'Bancoppel',
        '638' => 'Nu México',
        '646' => 'STP',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bankCode = array_rand(self::BANKS);
        $bankName = self::BANKS[$bankCode];
        $clabe = $this->generateClabe($bankCode);

        return [
            'tenant_id' => Tenant::factory(),
            'owner_type' => 'persons',
            'owner_id' => Person::factory(),
            'bank_name' => $bankName,
            'bank_code' => $bankCode,
            'clabe' => $clabe,
            'account_number_last4' => substr($clabe, -4),
            'card_number_last4' => fake()->optional(0.5)->numerify('####'),
            'account_type' => fake()->randomElement(BankAccountType::values()),
            'currency' => 'MXN',
            'holder_name' => fake('es_MX')->name(),
            'holder_rfc' => $this->generatePersonRfc(),
            'is_primary' => false,
            'is_for_disbursement' => true,
            'is_for_collection' => false,
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
            'verification_method' => null,
            'verification_data' => null,
            'status' => PersonBankAccount::STATUS_ACTIVE,
            'notes' => null,
            'metadata' => null,
        ];
    }

    /**
     * For a specific person.
     */
    public function forPerson(Person $person): static
    {
        return $this->state(fn() => [
            'tenant_id' => $person->tenant_id,
            'owner_type' => 'persons',
            'owner_id' => $person->id,
            'holder_name' => $person->full_name,
        ]);
    }

    /**
     * Primary account.
     */
    public function primary(): static
    {
        return $this->state(fn() => [
            'is_primary' => true,
        ]);
    }

    /**
     * Not primary.
     */
    public function notPrimary(): static
    {
        return $this->state(fn() => [
            'is_primary' => false,
        ]);
    }

    /**
     * Verified account.
     */
    public function verified(): static
    {
        return $this->state(fn() => [
            'is_verified' => true,
            'verified_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'verification_method' => fake()->randomElement(['MICRO_DEPOSIT', 'BANK_STATEMENT', 'SPEI_API', 'MANUAL']),
            'verification_data' => [
                'verified_by_method' => true,
                'verification_date' => now()->toDateString(),
            ],
        ]);
    }

    /**
     * Unverified account.
     */
    public function unverified(): static
    {
        return $this->state(fn() => [
            'is_verified' => false,
            'verified_at' => null,
            'verification_method' => null,
            'verification_data' => null,
        ]);
    }

    /**
     * For disbursement.
     */
    public function forDisbursement(): static
    {
        return $this->state(fn() => [
            'is_for_disbursement' => true,
        ]);
    }

    /**
     * For collection.
     */
    public function forCollection(): static
    {
        return $this->state(fn() => [
            'is_for_collection' => true,
        ]);
    }

    /**
     * For both disbursement and collection.
     */
    public function forBoth(): static
    {
        return $this->state(fn() => [
            'is_for_disbursement' => true,
            'is_for_collection' => true,
        ]);
    }

    /**
     * Active status.
     */
    public function active(): static
    {
        return $this->state(fn() => [
            'status' => PersonBankAccount::STATUS_ACTIVE,
        ]);
    }

    /**
     * Inactive status.
     */
    public function inactive(): static
    {
        return $this->state(fn() => [
            'status' => PersonBankAccount::STATUS_INACTIVE,
        ]);
    }

    /**
     * Closed status.
     */
    public function closed(): static
    {
        return $this->state(fn() => [
            'status' => PersonBankAccount::STATUS_CLOSED,
            'is_primary' => false,
        ]);
    }

    /**
     * Frozen status.
     */
    public function frozen(): static
    {
        return $this->state(fn() => [
            'status' => PersonBankAccount::STATUS_FROZEN,
        ]);
    }

    /**
     * Debit account type.
     */
    public function debit(): static
    {
        return $this->state(fn() => [
            'account_type' => BankAccountType::DEBIT->value,
        ]);
    }

    /**
     * Payroll account type.
     */
    public function payroll(): static
    {
        return $this->state(fn() => [
            'account_type' => BankAccountType::PAYROLL->value,
        ]);
    }

    /**
     * Savings account type.
     */
    public function savings(): static
    {
        return $this->state(fn() => [
            'account_type' => BankAccountType::SAVINGS->value,
        ]);
    }

    /**
     * Checking account type.
     */
    public function checking(): static
    {
        return $this->state(fn() => [
            'account_type' => BankAccountType::CHECKING->value,
        ]);
    }

    /**
     * BBVA México bank.
     */
    public function bbva(): static
    {
        $clabe = $this->generateClabe('012');
        return $this->state(fn() => [
            'bank_name' => 'BBVA México',
            'bank_code' => '012',
            'clabe' => $clabe,
            'account_number_last4' => substr($clabe, -4),
        ]);
    }

    /**
     * Banorte bank.
     */
    public function banorte(): static
    {
        $clabe = $this->generateClabe('072');
        return $this->state(fn() => [
            'bank_name' => 'Banorte',
            'bank_code' => '072',
            'clabe' => $clabe,
            'account_number_last4' => substr($clabe, -4),
        ]);
    }

    /**
     * Banamex bank.
     */
    public function banamex(): static
    {
        $clabe = $this->generateClabe('002');
        return $this->state(fn() => [
            'bank_name' => 'Banamex',
            'bank_code' => '002',
            'clabe' => $clabe,
            'account_number_last4' => substr($clabe, -4),
        ]);
    }

    /**
     * Santander bank.
     */
    public function santander(): static
    {
        $clabe = $this->generateClabe('014');
        return $this->state(fn() => [
            'bank_name' => 'Santander',
            'bank_code' => '014',
            'clabe' => $clabe,
            'account_number_last4' => substr($clabe, -4),
        ]);
    }

    /**
     * Ready for disbursement (verified, active, primary, for disbursement).
     */
    public function readyForDisbursement(): static
    {
        return $this->verified()->active()->primary()->forDisbursement();
    }

    /**
     * Generate a valid CLABE with correct check digit.
     */
    private function generateClabe(string $bankCode): string
    {
        // Bank code (3 digits) + Plaza code (3 digits) + Account number (11 digits)
        $plaza = str_pad((string) fake()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT);
        $account = fake()->numerify('###########');

        $clabeWithoutCheck = $bankCode . $plaza . $account;

        // Calculate check digit
        $weights = [3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7, 1, 3, 7];
        $sum = 0;

        for ($i = 0; $i < 17; $i++) {
            $sum += ((int) $clabeWithoutCheck[$i] * $weights[$i]) % 10;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $clabeWithoutCheck . $checkDigit;
    }

    /**
     * Generate a valid-format RFC for persona física (13 chars).
     */
    private function generatePersonRfc(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';
        $alphanumeric = $letters . $digits;

        $rfc = '';
        for ($i = 0; $i < 4; $i++) {
            $rfc .= $letters[rand(0, 25)];
        }
        $rfc .= str_pad((string) rand(50, 99), 2, '0', STR_PAD_LEFT);
        $rfc .= str_pad((string) rand(1, 12), 2, '0', STR_PAD_LEFT);
        $rfc .= str_pad((string) rand(1, 28), 2, '0', STR_PAD_LEFT);
        for ($i = 0; $i < 3; $i++) {
            $rfc .= $alphanumeric[rand(0, strlen($alphanumeric) - 1)];
        }

        return $rfc;
    }
}
