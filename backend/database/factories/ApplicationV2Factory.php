<?php

namespace Database\Factories;

use App\Models\ApplicantAccount;
use App\Models\ApplicationV2;
use App\Models\Company;
use App\Models\Person;
use App\Models\Product;
use App\Models\StaffAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApplicationV2>
 */
class ApplicationV2Factory extends Factory
{
    protected $model = ApplicationV2::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10000, 500000);
        $termMonths = fake()->randomElement([6, 12, 18, 24, 36, 48]);
        $interestRate = fake()->randomFloat(4, 0.15, 0.45);
        $monthlyPayment = $amount * ($interestRate / 12) / (1 - pow(1 + $interestRate / 12, -$termMonths));
        $totalInterest = ($monthlyPayment * $termMonths) - $amount;

        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'applicant_type' => ApplicationV2::TYPE_INDIVIDUAL,
            'person_id' => Person::factory(),
            'company_id' => null,
            'requested_amount' => $amount,
            'requested_term_months' => $termMonths,
            'purpose' => fake()->randomElement(['PERSONAL', 'BUSINESS', 'VEHICLE', 'HOME_IMPROVEMENT', 'DEBT_CONSOLIDATION']),
            'interest_rate' => $interestRate,
            'monthly_payment' => round($monthlyPayment, 2),
            'total_interest' => round($totalInterest, 2),
            'total_amount' => round($amount + $totalInterest, 2),
            'cat' => fake()->randomFloat(4, 0.20, 0.60),
            'status' => ApplicationV2::STATUS_DRAFT,
        ];
    }

    /**
     * For individual applicant.
     */
    public function individual(Person $person = null): static
    {
        return $this->state(fn(array $attributes) => [
            'applicant_type' => ApplicationV2::TYPE_INDIVIDUAL,
            'person_id' => $person?->id ?? Person::factory(),
            'company_id' => null,
        ]);
    }

    /**
     * For company applicant.
     */
    public function forCompany(Company $company = null): static
    {
        return $this->state(fn(array $attributes) => [
            'applicant_type' => ApplicationV2::TYPE_COMPANY,
            'person_id' => null,
            'company_id' => $company?->id ?? Company::factory(),
        ]);
    }

    /**
     * Draft status.
     */
    public function draft(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ApplicationV2::STATUS_DRAFT,
            'submitted_at' => null,
        ]);
    }

    /**
     * Submitted status.
     */
    public function submitted(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ApplicationV2::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submission_ip' => fake()->ipv4(),
        ]);
    }

    /**
     * In review status.
     */
    public function inReview(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ApplicationV2::STATUS_IN_REVIEW,
            'submitted_at' => now()->subDays(2),
            'status_changed_at' => now(),
        ]);
    }

    /**
     * Pending documents status.
     */
    public function docsPending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ApplicationV2::STATUS_DOCS_PENDING,
            'submitted_at' => now()->subDays(3),
            'status_changed_at' => now(),
        ]);
    }

    /**
     * Approved status.
     */
    public function approved(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => ApplicationV2::STATUS_APPROVED,
                'submitted_at' => now()->subDays(5),
                'decision' => ApplicationV2::DECISION_APPROVED,
                'decision_at' => now(),
                'approved_amount' => $attributes['requested_amount'],
                'approved_term_months' => $attributes['requested_term_months'],
                'approved_interest_rate' => $attributes['interest_rate'],
                'approved_monthly_payment' => $attributes['monthly_payment'],
            ];
        });
    }

    /**
     * Rejected status.
     */
    public function rejected(string $reason = null): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ApplicationV2::STATUS_REJECTED,
            'submitted_at' => now()->subDays(5),
            'decision' => ApplicationV2::DECISION_REJECTED,
            'decision_at' => now(),
            'rejection_reason' => $reason ?? 'No cumple con requisitos de crédito',
        ]);
    }

    /**
     * Cancelled status.
     */
    public function cancelled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ApplicationV2::STATUS_CANCELLED,
            'status_changed_at' => now(),
        ]);
    }

    /**
     * Synced to external system.
     */
    public function synced(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ApplicationV2::STATUS_SYNCED,
            'synced_at' => now(),
            'external_id' => fake()->uuid(),
            'external_system' => fake()->randomElement(['SAP', 'LENDUS', 'CORE_BANKING']),
        ]);
    }

    /**
     * Assigned to staff.
     */
    public function assigned(StaffAccount $staff = null, StaffAccount $assignedBy = null): static
    {
        return $this->state(fn(array $attributes) => [
            'assigned_to' => $staff?->id ?? StaffAccount::factory(),
            'assigned_at' => now(),
            'assigned_by' => $assignedBy?->id,
        ]);
    }

    /**
     * With counter offer.
     */
    public function withCounterOffer(array $offer = null): static
    {
        return $this->state(function (array $attributes) use ($offer) {
            return [
                'decision' => ApplicationV2::DECISION_COUNTER_OFFER,
                'decision_at' => now(),
                'counter_offer' => $offer ?? [
                    'amount' => $attributes['requested_amount'] * 0.8,
                    'term_months' => $attributes['requested_term_months'],
                    'interest_rate' => ($attributes['interest_rate'] ?? 0.30) + 0.05,
                    'monthly_payment' => ($attributes['monthly_payment'] ?? 5000) * 0.85,
                    'reason' => 'Oferta ajustada por capacidad de pago',
                    'expires_at' => now()->addDays(7)->toIso8601String(),
                ],
            ];
        });
    }

    /**
     * With verification checklist.
     */
    public function withVerification(array $checks = null): static
    {
        return $this->state(fn(array $attributes) => [
            'verification_checklist' => $checks ?? [
                'identity_verified' => true,
                'address_verified' => true,
                'employment_verified' => true,
                'references_verified' => 2,
                'bank_verified' => true,
                'documents_approved' => 5,
                'documents_pending' => 0,
            ],
        ]);
    }

    /**
     * With risk assessment.
     */
    public function withRisk(string $level = null): static
    {
        return $this->state(fn(array $attributes) => [
            'risk_level' => $level ?? fake()->randomElement([
                ApplicationV2::RISK_LOW,
                ApplicationV2::RISK_MEDIUM,
                ApplicationV2::RISK_HIGH,
            ]),
            'risk_data' => [
                'score' => fake()->numberBetween(300, 850),
                'bureau_checked' => true,
                'pld_checked' => true,
            ],
        ]);
    }

    /**
     * Low risk.
     */
    public function lowRisk(): static
    {
        return $this->withRisk(ApplicationV2::RISK_LOW);
    }

    /**
     * High risk.
     */
    public function highRisk(): static
    {
        return $this->withRisk(ApplicationV2::RISK_HIGH);
    }

    /**
     * Expiring soon.
     */
    public function expiringSoon(int $days = 3): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ApplicationV2::STATUS_DRAFT,
            'expires_at' => now()->addDays($days),
            'expiration_notified' => false,
        ]);
    }

    /**
     * With snapshot data.
     */
    public function withSnapshot(array $data = null): static
    {
        return $this->state(fn(array $attributes) => [
            'snapshot_data' => $data ?? [
                'personal' => [
                    'full_name' => fake()->name(),
                    'curp' => fake()->regexify('[A-Z]{4}[0-9]{6}[A-Z]{6}[0-9]{2}'),
                    'rfc' => fake()->regexify('[A-Z]{4}[0-9]{6}[A-Z0-9]{3}'),
                ],
                'address' => [
                    'street' => fake()->streetAddress(),
                    'city' => fake()->city(),
                    'state' => fake()->state(),
                    'postal_code' => fake()->postcode(),
                ],
                'employment' => [
                    'company_name' => fake()->company(),
                    'monthly_income' => fake()->randomFloat(2, 10000, 100000),
                    'years_employed' => fake()->numberBetween(1, 20),
                ],
            ],
        ]);
    }
}
