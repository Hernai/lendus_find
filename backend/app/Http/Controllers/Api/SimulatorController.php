<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentFrequency;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\LoanCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SimulatorController extends Controller
{
    public function __construct(
        protected LoanCalculationService $loanCalculator
    ) {}

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
            'payment_frequency' => ['required', Rule::in(PaymentFrequency::values())],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $product = Product::find($request->product_id);

        // Verify product exists and belongs to current tenant
        if (!$product) {
            return response()->json([
                'error' => 'Product not found',
                'message' => 'El producto seleccionado no está disponible para este tenant.',
            ], 404);
        }

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

        $calculation = $this->loanCalculator->calculateSimulation(
            $request->amount,
            $request->term_months,
            $request->payment_frequency,
            $product->annual_rate,
            $product->opening_commission_rate
        );

        return response()->json([
            'simulation' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
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
            'payment_frequency' => ['required', Rule::in(PaymentFrequency::values())],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $table = $this->loanCalculator->generateAmortizationTable(
            $request->amount,
            $request->annual_rate,
            $request->term_months,
            $request->payment_frequency
        );

        return response()->json(['amortization' => $table]);
    }
}
