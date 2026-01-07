<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SimulatorController extends Controller
{
    /**
     * Get available products for simulation.
     */
    public function products(): JsonResponse
    {
        $products = Product::active()->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'type' => $p->type,
            'description' => $p->description,
            'icon' => $p->icon,
            'min_amount' => $p->min_amount,
            'max_amount' => $p->max_amount,
            'min_term_months' => $p->min_term_months,
            'max_term_months' => $p->max_term_months,
            'annual_rate' => $p->annual_rate,
            'opening_commission' => $p->opening_commission_rate,
            'payment_frequencies' => $p->rules['payment_frequencies'] ?? ['MONTHLY'],
        ]);

        return response()->json(['products' => $products]);
    }

    /**
     * Calculate loan simulation.
     */
    public function calculate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|uuid|exists:products,id',
            'amount' => 'required|numeric|min:1000',
            'term_months' => 'required|integer|min:1',
            'payment_frequency' => 'required|in:WEEKLY,BIWEEKLY,MONTHLY',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $product = Product::find($request->product_id);

        // Validate amount and term against product rules
        if (!$product->isAmountValid($request->amount)) {
            return response()->json([
                'error' => 'Invalid amount',
                'message' => 'El monto debe estar entre ' . $product->min_amount . ' y ' . $product->max_amount,
            ], 422);
        }

        if (!$product->isTermValid($request->term_months)) {
            return response()->json([
                'error' => 'Invalid term',
                'message' => 'El plazo debe estar entre ' . $product->min_term_months . ' y ' . $product->max_term_months . ' meses',
            ], 422);
        }

        $calculation = $this->calculateLoan(
            $request->amount,
            $product->annual_rate,
            $request->term_months,
            $request->payment_frequency,
            $product->opening_commission_rate
        );

        return response()->json([
            'simulation' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'requested_amount' => $request->amount,
                'term_months' => $request->term_months,
                'payment_frequency' => $request->payment_frequency,
                'annual_rate' => $product->annual_rate,
                'opening_commission_rate' => $product->opening_commission_rate,
                ...$calculation,
            ],
        ]);
    }

    /**
     * Get amortization table.
     */
    public function amortization(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1000',
            'annual_rate' => 'required|numeric|min:0|max:100',
            'term_months' => 'required|integer|min:1',
            'payment_frequency' => 'required|in:WEEKLY,BIWEEKLY,MONTHLY',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $table = $this->generateAmortizationTable(
            $request->amount,
            $request->annual_rate,
            $request->term_months,
            $request->payment_frequency
        );

        return response()->json(['amortization' => $table]);
    }

    /**
     * Calculate loan details.
     */
    protected function calculateLoan(
        float $amount,
        float $annualRate,
        int $termMonths,
        string $frequency,
        float $commissionRate
    ): array {
        // Convert annual rate to period rate
        $periodsPerYear = match ($frequency) {
            'WEEKLY' => 52,
            'BIWEEKLY' => 26,
            'MONTHLY' => 12,
        };

        $totalPeriods = match ($frequency) {
            'WEEKLY' => $termMonths * 4.33,
            'BIWEEKLY' => $termMonths * 2.17,
            'MONTHLY' => $termMonths,
        };

        $totalPeriods = (int) round($totalPeriods);
        $periodRate = ($annualRate / 100) / $periodsPerYear;

        // Calculate payment using French amortization (fixed payments)
        if ($periodRate > 0) {
            $payment = $amount * ($periodRate * pow(1 + $periodRate, $totalPeriods)) /
                (pow(1 + $periodRate, $totalPeriods) - 1);
        } else {
            $payment = $amount / $totalPeriods;
        }

        $totalToPay = $payment * $totalPeriods;
        $totalInterest = $totalToPay - $amount;
        $openingCommission = $amount * ($commissionRate / 100);

        // Calculate CAT (Costo Anual Total) - simplified
        $cat = ($totalInterest + $openingCommission) / $amount / ($termMonths / 12) * 100;

        return [
            'payment_amount' => round($payment, 2),
            'total_periods' => $totalPeriods,
            'total_to_pay' => round($totalToPay, 2),
            'total_interest' => round($totalInterest, 2),
            'opening_commission' => round($openingCommission, 2),
            'net_amount' => round($amount - $openingCommission, 2),
            'cat' => round($cat, 2),
        ];
    }

    /**
     * Generate amortization table.
     */
    protected function generateAmortizationTable(
        float $amount,
        float $annualRate,
        int $termMonths,
        string $frequency
    ): array {
        $periodsPerYear = match ($frequency) {
            'WEEKLY' => 52,
            'BIWEEKLY' => 26,
            'MONTHLY' => 12,
        };

        $totalPeriods = match ($frequency) {
            'WEEKLY' => (int) round($termMonths * 4.33),
            'BIWEEKLY' => (int) round($termMonths * 2.17),
            'MONTHLY' => $termMonths,
        };

        $periodRate = ($annualRate / 100) / $periodsPerYear;

        // Calculate fixed payment
        if ($periodRate > 0) {
            $payment = $amount * ($periodRate * pow(1 + $periodRate, $totalPeriods)) /
                (pow(1 + $periodRate, $totalPeriods) - 1);
        } else {
            $payment = $amount / $totalPeriods;
        }

        $balance = $amount;
        $table = [];

        for ($i = 1; $i <= $totalPeriods; $i++) {
            $interest = $balance * $periodRate;
            $principal = $payment - $interest;

            if ($i === $totalPeriods) {
                // Last payment adjustment
                $principal = $balance;
                $payment = $principal + $interest;
            }

            $balance -= $principal;

            $table[] = [
                'period' => $i,
                'payment' => round($payment, 2),
                'principal' => round($principal, 2),
                'interest' => round($interest, 2),
                'balance' => round(max(0, $balance), 2),
            ];
        }

        return $table;
    }
}
