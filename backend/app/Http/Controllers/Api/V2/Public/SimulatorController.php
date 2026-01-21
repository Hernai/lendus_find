<?php

namespace App\Http\Controllers\Api\V2\Public;

use App\Enums\PaymentFrequency;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\LoanCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * V2 Simulator Controller.
 *
 * Handles loan simulation calculations.
 * All endpoints are under /api/v2/simulator
 */
class SimulatorController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected LoanCalculationService $loanCalculator
    ) {}

    /**
     * Calculate loan simulation.
     *
     * POST /v2/simulator/calculate
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
            return $this->validationError('Error de validación', $validator->errors()->toArray());
        }

        $product = Product::find($request->product_id);

        if (!$product) {
            return $this->notFound('El producto seleccionado no está disponible.');
        }

        // Validate amount against product rules
        if (!$product->isAmountValid($request->amount)) {
            return $this->error(
                'INVALID_AMOUNT',
                "El monto debe estar entre {$product->min_amount} y {$product->max_amount}",
                422
            );
        }

        // Validate term against product rules
        if (!$product->isTermValid($request->term_months)) {
            return $this->error(
                'INVALID_TERM',
                "El plazo debe estar entre {$product->min_term_months} y {$product->max_term_months} meses",
                422
            );
        }

        $calculation = $this->loanCalculator->calculateSimulation(
            $request->amount,
            $request->term_months,
            $request->payment_frequency,
            $product->annual_rate,
            $product->opening_commission_rate
        );

        return $this->success([
            'simulation' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                ...$calculation,
            ],
        ]);
    }

}
