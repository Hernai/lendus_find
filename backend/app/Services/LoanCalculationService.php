<?php

namespace App\Services;

use App\Enums\PaymentFrequency;

/**
 * Service for loan calculation operations.
 *
 * Single Responsibility: Handle all loan-related calculations including
 * payment amounts, amortization tables, and commission calculations.
 *
 * This centralizes calculation logic that was previously duplicated
 * across multiple controllers.
 */
class LoanCalculationService
{
    /**
     * Payment frequency constants.
     * @deprecated Use PaymentFrequency enum instead
     */
    public const FREQUENCY_WEEKLY = 'WEEKLY';
    public const FREQUENCY_BIWEEKLY = 'BIWEEKLY';
    public const FREQUENCY_MONTHLY = 'MONTHLY';

    /**
     * Periods per year by payment frequency.
     */
    private const PERIODS_PER_YEAR = [
        self::FREQUENCY_WEEKLY => 52,
        self::FREQUENCY_BIWEEKLY => 24,
        self::FREQUENCY_MONTHLY => 12,
    ];

    /**
     * Calculate the number of periods per year for a given frequency.
     */
    public function getPeriodsPerYear(string $frequency): int
    {
        $normalizedFrequency = strtoupper($frequency);

        // Use enum if value exists, otherwise try direct lookup
        $enumFrequency = PaymentFrequency::normalize($normalizedFrequency);
        if ($enumFrequency !== null) {
            return $enumFrequency->periodsPerYear();
        }

        return self::PERIODS_PER_YEAR[$normalizedFrequency] ?? 12;
    }

    /**
     * Calculate total number of payment periods.
     */
    public function calculateTotalPeriods(int $termMonths, string $frequency): int
    {
        $normalizedFrequency = strtoupper($frequency);

        // Normalize legacy Spanish values
        $enumFrequency = PaymentFrequency::normalize($normalizedFrequency);
        if ($enumFrequency !== null) {
            $normalizedFrequency = $enumFrequency->value;
        }

        $totalPeriods = match ($normalizedFrequency) {
            self::FREQUENCY_WEEKLY => $termMonths * 4.33,
            self::FREQUENCY_BIWEEKLY => $termMonths * 2,
            default => $termMonths,
        };

        return (int) round($totalPeriods);
    }

    /**
     * Calculate periodic interest rate from annual rate.
     */
    public function calculatePeriodicRate(float $annualRate, string $frequency): float
    {
        $periodsPerYear = $this->getPeriodsPerYear($frequency);
        return ($annualRate / 100) / $periodsPerYear;
    }

    /**
     * Calculate periodic payment amount using French amortization formula.
     *
     * Formula: P = L * [r(1+r)^n] / [(1+r)^n - 1]
     * Where:
     *   P = periodic payment
     *   L = loan amount (principal)
     *   r = periodic interest rate
     *   n = total number of periods
     */
    public function calculatePayment(
        float $principal,
        float $annualRate,
        int $termMonths,
        string $frequency
    ): float {
        $periodRate = $this->calculatePeriodicRate($annualRate, $frequency);
        $totalPeriods = $this->calculateTotalPeriods($termMonths, $frequency);

        if ($periodRate > 0) {
            $payment = $principal *
                ($periodRate * pow(1 + $periodRate, $totalPeriods)) /
                (pow(1 + $periodRate, $totalPeriods) - 1);
        } else {
            // Zero interest rate - simple division
            $payment = $principal / $totalPeriods;
        }

        return round($payment, 2);
    }

    /**
     * Calculate opening commission amount.
     */
    public function calculateOpeningCommission(float $principal, float $commissionRate): float
    {
        return round($principal * ($commissionRate / 100), 2);
    }

    /**
     * Calculate net amount after opening commission.
     */
    public function calculateNetAmount(float $principal, float $openingCommission): float
    {
        return round($principal - $openingCommission, 2);
    }

    /**
     * Calculate total amount to pay (all payments).
     */
    public function calculateTotalToPay(float $payment, int $totalPeriods): float
    {
        return round($payment * $totalPeriods, 2);
    }

    /**
     * Calculate total interest over the loan term.
     */
    public function calculateTotalInterest(float $totalToPay, float $principal): float
    {
        return round($totalToPay - $principal, 2);
    }

    /**
     * Calculate CAT (Costo Anual Total) - Total Annual Cost.
     *
     * CAT is a Mexican regulatory requirement that represents the total
     * cost of a loan including interest and fees, expressed as an annual percentage.
     *
     * Simplified calculation: ((totalToPay + commission) / principal - 1) / years * 100
     */
    public function calculateCAT(
        float $principal,
        float $totalToPay,
        float $openingCommission,
        int $termMonths
    ): float {
        $years = $termMonths / 12;
        if ($years <= 0 || $principal <= 0) {
            return 0;
        }

        $totalCost = $totalToPay + $openingCommission;
        $cat = (($totalCost / $principal) - 1) / $years * 100;

        return round($cat, 2);
    }

    /**
     * Generate complete amortization table.
     *
     * @return array<int, array{
     *     period: int,
     *     payment: float,
     *     principal: float,
     *     interest: float,
     *     balance: float
     * }>
     */
    public function generateAmortizationTable(
        float $principal,
        float $annualRate,
        int $termMonths,
        string $frequency
    ): array {
        $periodRate = $this->calculatePeriodicRate($annualRate, $frequency);
        $totalPeriods = $this->calculateTotalPeriods($termMonths, $frequency);
        $payment = $this->calculatePayment($principal, $annualRate, $termMonths, $frequency);

        $balance = $principal;
        $table = [];

        for ($period = 1; $period <= $totalPeriods; $period++) {
            $interest = round($balance * $periodRate, 2);
            $principalPayment = round($payment - $interest, 2);

            // Last period adjustment to avoid floating point errors
            if ($period === $totalPeriods) {
                $principalPayment = $balance;
                $payment = $principalPayment + $interest;
            }

            $balance = round($balance - $principalPayment, 2);
            if ($balance < 0) {
                $balance = 0;
            }

            $table[] = [
                'period' => $period,
                'payment' => round($payment, 2),
                'principal' => $principalPayment,
                'interest' => $interest,
                'balance' => $balance,
            ];
        }

        return $table;
    }

    /**
     * Calculate complete loan simulation.
     *
     * @return array{
     *     requested_amount: float,
     *     term_months: int,
     *     payment_frequency: string,
     *     annual_rate: float,
     *     periodic_rate: float,
     *     total_periods: int,
     *     payment_amount: float,
     *     opening_commission_rate: float,
     *     opening_commission: float,
     *     net_amount: float,
     *     total_to_pay: float,
     *     total_interest: float,
     *     cat: float
     * }
     */
    public function calculateSimulation(
        float $amount,
        int $termMonths,
        string $frequency,
        float $annualRate,
        float $commissionRate = 0
    ): array {
        $totalPeriods = $this->calculateTotalPeriods($termMonths, $frequency);
        $periodicRate = $this->calculatePeriodicRate($annualRate, $frequency);
        $payment = $this->calculatePayment($amount, $annualRate, $termMonths, $frequency);
        $openingCommission = $this->calculateOpeningCommission($amount, $commissionRate);
        $netAmount = $this->calculateNetAmount($amount, $openingCommission);
        $totalToPay = $this->calculateTotalToPay($payment, $totalPeriods);
        $totalInterest = $this->calculateTotalInterest($totalToPay, $amount);
        $cat = $this->calculateCAT($amount, $totalToPay, $openingCommission, $termMonths);

        return [
            'requested_amount' => $amount,
            'term_months' => $termMonths,
            'payment_frequency' => strtoupper($frequency),
            'annual_rate' => $annualRate,
            'periodic_rate' => round($periodicRate * 100, 4),
            'total_periods' => $totalPeriods,
            'payment_amount' => $payment,
            'opening_commission_rate' => $commissionRate,
            'opening_commission' => $openingCommission,
            'net_amount' => $netAmount,
            'total_to_pay' => $totalToPay,
            'total_interest' => $totalInterest,
            'cat' => $cat,
        ];
    }

    /**
     * Get frequency label in Spanish.
     */
    public function getFrequencyLabel(string $frequency): string
    {
        $enumFrequency = PaymentFrequency::normalize($frequency);
        if ($enumFrequency !== null) {
            return $enumFrequency->label();
        }

        return match (strtoupper($frequency)) {
            self::FREQUENCY_WEEKLY => 'Semanal',
            self::FREQUENCY_BIWEEKLY => 'Quincenal',
            self::FREQUENCY_MONTHLY => 'Mensual',
            default => $frequency,
        };
    }
}
