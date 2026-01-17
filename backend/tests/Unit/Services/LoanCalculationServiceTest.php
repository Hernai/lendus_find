<?php

namespace Tests\Unit\Services;

use App\Services\LoanCalculationService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LoanCalculationService.
 *
 * These tests validate the mathematical accuracy of loan calculations
 * which are critical for regulatory compliance (CAT) and business operations.
 */
class LoanCalculationServiceTest extends TestCase
{
    private LoanCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LoanCalculationService();
    }

    /**
     * @test
     * @dataProvider periodsPerYearProvider
     */
    public function it_returns_correct_periods_per_year(string $frequency, int $expected): void
    {
        $result = $this->service->getPeriodsPerYear($frequency);
        $this->assertEquals($expected, $result);
    }

    public static function periodsPerYearProvider(): array
    {
        return [
            'weekly' => ['WEEKLY', 52],
            'biweekly' => ['BIWEEKLY', 24],
            'quincenal' => ['QUINCENAL', 24],
            'monthly' => ['MONTHLY', 12],
            'lowercase weekly' => ['weekly', 52],
            'lowercase monthly' => ['monthly', 12],
            'unknown defaults to monthly' => ['UNKNOWN', 12],
        ];
    }

    /**
     * @test
     * @dataProvider totalPeriodsProvider
     */
    public function it_calculates_total_periods_correctly(
        int $termMonths,
        string $frequency,
        int $expected
    ): void {
        $result = $this->service->calculateTotalPeriods($termMonths, $frequency);
        $this->assertEquals($expected, $result);
    }

    public static function totalPeriodsProvider(): array
    {
        return [
            '12 months weekly' => [12, 'WEEKLY', 52],  // 12 * 4.33 = 51.96 ≈ 52
            '12 months biweekly' => [12, 'BIWEEKLY', 24],  // 12 * 2 = 24
            '12 months monthly' => [12, 'MONTHLY', 12],
            '6 months weekly' => [6, 'WEEKLY', 26],  // 6 * 4.33 = 25.98 ≈ 26
            '6 months biweekly' => [6, 'BIWEEKLY', 12],  // 6 * 2 = 12
            '24 months monthly' => [24, 'MONTHLY', 24],
            '36 months biweekly' => [36, 'BIWEEKLY', 72],  // 36 * 2 = 72
        ];
    }

    /**
     * @test
     */
    public function it_calculates_periodic_rate_correctly(): void
    {
        // 24% annual rate, monthly = 2% per period
        $result = $this->service->calculatePeriodicRate(24.0, 'MONTHLY');
        $this->assertEquals(0.02, $result);

        // 24% annual rate, biweekly = 1% per period
        $result = $this->service->calculatePeriodicRate(24.0, 'BIWEEKLY');
        $this->assertEquals(0.01, $result);

        // 52% annual rate, weekly = 1% per period
        $result = $this->service->calculatePeriodicRate(52.0, 'WEEKLY');
        $this->assertEquals(0.01, $result);
    }

    /**
     * @test
     * @dataProvider paymentCalculationProvider
     */
    public function it_calculates_payment_amount_using_french_amortization(
        float $principal,
        float $annualRate,
        int $termMonths,
        string $frequency,
        float $expectedPayment
    ): void {
        $result = $this->service->calculatePayment($principal, $annualRate, $termMonths, $frequency);

        // Allow 0.01 tolerance for rounding differences
        $this->assertEqualsWithDelta($expectedPayment, $result, 0.01);
    }

    public static function paymentCalculationProvider(): array
    {
        return [
            // $100,000 at 24% for 12 months monthly
            // Formula: 100000 * [0.02(1.02)^12] / [(1.02)^12 - 1] = 9,455.96
            '100k 24% 12mo monthly' => [100000, 24.0, 12, 'MONTHLY', 9455.96],

            // $50,000 at 24% for 6 months biweekly (12 periods)
            // Rate per period = 24/24 = 1% = 0.01
            // Formula: 50000 * [0.01(1.01)^12] / [(1.01)^12 - 1] = 4,442.44
            '50k 24% 6mo biweekly' => [50000, 24.0, 6, 'BIWEEKLY', 4442.44],

            // $10,000 at 0% for 12 months monthly (no interest)
            // Simple division: 10000 / 12 = 833.33
            '10k 0% 12mo monthly' => [10000, 0.0, 12, 'MONTHLY', 833.33],

            // $200,000 at 36% for 24 months monthly
            // Rate = 3% = 0.03
            // Formula: 200000 * [0.03(1.03)^24] / [(1.03)^24 - 1] = 11,809.48
            '200k 36% 24mo monthly' => [200000, 36.0, 24, 'MONTHLY', 11809.48],
        ];
    }

    /**
     * @test
     */
    public function it_calculates_opening_commission_correctly(): void
    {
        // 3% commission on $100,000 = $3,000
        $result = $this->service->calculateOpeningCommission(100000, 3.0);
        $this->assertEquals(3000.00, $result);

        // 5% commission on $50,000 = $2,500
        $result = $this->service->calculateOpeningCommission(50000, 5.0);
        $this->assertEquals(2500.00, $result);

        // 0% commission = $0
        $result = $this->service->calculateOpeningCommission(100000, 0);
        $this->assertEquals(0.00, $result);
    }

    /**
     * @test
     */
    public function it_calculates_net_amount_correctly(): void
    {
        // $100,000 - $3,000 commission = $97,000
        $result = $this->service->calculateNetAmount(100000, 3000);
        $this->assertEquals(97000.00, $result);
    }

    /**
     * @test
     */
    public function it_calculates_total_to_pay_correctly(): void
    {
        // $9,455.96 * 12 payments = $113,471.52
        $result = $this->service->calculateTotalToPay(9455.96, 12);
        $this->assertEquals(113471.52, $result);
    }

    /**
     * @test
     */
    public function it_calculates_total_interest_correctly(): void
    {
        // $113,471.52 total - $100,000 principal = $13,471.52 interest
        $result = $this->service->calculateTotalInterest(113471.52, 100000);
        $this->assertEquals(13471.52, $result);
    }

    /**
     * @test
     */
    public function it_calculates_cat_correctly(): void
    {
        // CAT = ((totalCost / principal) - 1) / years * 100
        // For $100,000 loan, $113,471.52 total to pay, $3,000 commission, 12 months
        // totalCost = 113471.52 + 3000 = 116471.52
        // CAT = ((116471.52 / 100000) - 1) / 1 * 100 = 16.47%
        $result = $this->service->calculateCAT(100000, 113471.52, 3000, 12);
        $this->assertEqualsWithDelta(16.47, $result, 0.01);
    }

    /**
     * @test
     */
    public function it_returns_zero_cat_for_invalid_inputs(): void
    {
        // Zero principal
        $result = $this->service->calculateCAT(0, 1000, 100, 12);
        $this->assertEquals(0, $result);

        // Zero term
        $result = $this->service->calculateCAT(100000, 110000, 1000, 0);
        $this->assertEquals(0, $result);

        // Negative term
        $result = $this->service->calculateCAT(100000, 110000, 1000, -12);
        $this->assertEquals(0, $result);
    }

    /**
     * @test
     */
    public function it_generates_complete_amortization_table(): void
    {
        // Simple case: $12,000 at 24% for 12 months monthly
        $table = $this->service->generateAmortizationTable(12000, 24.0, 12, 'MONTHLY');

        // Should have 12 entries
        $this->assertCount(12, $table);

        // First period should have highest interest, last should have lowest
        $firstInterest = $table[0]['interest'];
        $lastInterest = $table[11]['interest'];
        $this->assertGreaterThan($lastInterest, $firstInterest);

        // Balance should be zero at the end
        $this->assertEquals(0, $table[11]['balance']);

        // Each entry should have required keys
        foreach ($table as $entry) {
            $this->assertArrayHasKey('period', $entry);
            $this->assertArrayHasKey('payment', $entry);
            $this->assertArrayHasKey('principal', $entry);
            $this->assertArrayHasKey('interest', $entry);
            $this->assertArrayHasKey('balance', $entry);
        }

        // Sum of all principal payments should equal original amount
        $totalPrincipal = array_sum(array_column($table, 'principal'));
        $this->assertEqualsWithDelta(12000, $totalPrincipal, 0.02);
    }

    /**
     * @test
     */
    public function it_generates_amortization_table_with_decreasing_balance(): void
    {
        $table = $this->service->generateAmortizationTable(50000, 18.0, 24, 'MONTHLY');

        $previousBalance = 50000;
        foreach ($table as $entry) {
            $this->assertLessThanOrEqual($previousBalance, $entry['balance']);
            $previousBalance = $entry['balance'];
        }
    }

    /**
     * @test
     */
    public function it_calculates_complete_simulation(): void
    {
        $simulation = $this->service->calculateSimulation(
            amount: 100000,
            termMonths: 12,
            frequency: 'MONTHLY',
            annualRate: 24.0,
            commissionRate: 3.0
        );

        // Verify all required keys exist
        $requiredKeys = [
            'requested_amount',
            'term_months',
            'payment_frequency',
            'annual_rate',
            'periodic_rate',
            'total_periods',
            'payment_amount',
            'opening_commission_rate',
            'opening_commission',
            'net_amount',
            'total_to_pay',
            'total_interest',
            'cat',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $simulation);
        }

        // Verify values
        $this->assertEquals(100000, $simulation['requested_amount']);
        $this->assertEquals(12, $simulation['term_months']);
        $this->assertEquals('MONTHLY', $simulation['payment_frequency']);
        $this->assertEquals(24.0, $simulation['annual_rate']);
        $this->assertEquals(12, $simulation['total_periods']);
        $this->assertEquals(3.0, $simulation['opening_commission_rate']);
        $this->assertEquals(3000.00, $simulation['opening_commission']);
        $this->assertEquals(97000.00, $simulation['net_amount']);

        // Total to pay should be greater than principal
        $this->assertGreaterThan(100000, $simulation['total_to_pay']);

        // Total interest should be positive
        $this->assertGreaterThan(0, $simulation['total_interest']);

        // CAT should be reasonable (between 0 and 100%)
        $this->assertGreaterThan(0, $simulation['cat']);
        $this->assertLessThan(100, $simulation['cat']);
    }

    /**
     * @test
     */
    public function it_normalizes_frequency_to_uppercase_in_simulation(): void
    {
        $simulation = $this->service->calculateSimulation(
            amount: 50000,
            termMonths: 6,
            frequency: 'monthly',  // lowercase
            annualRate: 18.0,
            commissionRate: 0
        );

        $this->assertEquals('MONTHLY', $simulation['payment_frequency']);
    }

    /**
     * @test
     * @dataProvider frequencyLabelProvider
     */
    public function it_returns_correct_frequency_labels(string $frequency, string $expected): void
    {
        $result = $this->service->getFrequencyLabel($frequency);
        $this->assertEquals($expected, $result);
    }

    public static function frequencyLabelProvider(): array
    {
        return [
            'weekly' => ['WEEKLY', 'Semanal'],
            'biweekly' => ['BIWEEKLY', 'Quincenal'],
            'quincenal' => ['QUINCENAL', 'Quincenal'],
            'monthly' => ['MONTHLY', 'Mensual'],
            'lowercase' => ['monthly', 'Mensual'],
            'unknown' => ['UNKNOWN', 'UNKNOWN'],
        ];
    }

    /**
     * @test
     */
    public function it_handles_zero_interest_loan_correctly(): void
    {
        $simulation = $this->service->calculateSimulation(
            amount: 10000,
            termMonths: 10,
            frequency: 'MONTHLY',
            annualRate: 0,
            commissionRate: 0
        );

        // Payment should be principal / periods
        $this->assertEquals(1000.00, $simulation['payment_amount']);
        $this->assertEquals(10000.00, $simulation['total_to_pay']);
        $this->assertEquals(0.00, $simulation['total_interest']);
    }

    /**
     * @test
     */
    public function it_handles_high_interest_rates(): void
    {
        // 100% annual rate (extreme case)
        $simulation = $this->service->calculateSimulation(
            amount: 10000,
            termMonths: 12,
            frequency: 'MONTHLY',
            annualRate: 100.0,
            commissionRate: 0
        );

        // Should still calculate without errors
        $this->assertGreaterThan(10000, $simulation['total_to_pay']);
        $this->assertGreaterThan(0, $simulation['payment_amount']);
    }

    /**
     * @test
     */
    public function it_handles_small_loan_amounts(): void
    {
        $simulation = $this->service->calculateSimulation(
            amount: 1000,
            termMonths: 3,
            frequency: 'MONTHLY',
            annualRate: 24.0,
            commissionRate: 0
        );

        // Should calculate correctly for small amounts
        $this->assertEquals(1000, $simulation['requested_amount']);
        $this->assertGreaterThan(0, $simulation['payment_amount']);
        $this->assertEquals(3, $simulation['total_periods']);
    }

    /**
     * @test
     */
    public function it_handles_long_term_loans(): void
    {
        // 60 months (5 years)
        $simulation = $this->service->calculateSimulation(
            amount: 500000,
            termMonths: 60,
            frequency: 'MONTHLY',
            annualRate: 18.0,
            commissionRate: 2.5
        );

        $this->assertEquals(60, $simulation['total_periods']);
        $this->assertEquals(60, $simulation['term_months']);
        $this->assertGreaterThan(0, $simulation['cat']);
    }
}
