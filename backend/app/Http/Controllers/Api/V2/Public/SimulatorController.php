<?php

namespace App\Http\Controllers\Api\V2\Public;

use App\Enums\PaymentFrequency;
use App\Enums\ProductType;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\LeaseCalculationService;
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
        protected LoanCalculationService $loanCalculator,
        protected LeaseCalculationService $leaseCalculator,
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
            // El mínimo real lo valida isAmountValid() contra las reglas del
            // producto (hay productos desde $300, ej. "Sin Buró").
            'amount' => 'required|numeric|min:1',
            'term_months' => 'required|integer|min:1',
            // Plazo en días para productos de pago único (SINGLE / BULLET).
            'term_days' => 'nullable|integer|min:1',
            'payment_frequency' => ['required', Rule::in(PaymentFrequency::values())],
            // Anticipo/enganche (%) para arrendamiento; se ignora en crédito.
            'down_payment_pct' => 'nullable|numeric|min:0|max:100',
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

        // Pago único (SINGLE / BULLET): el plazo real va en días. Lo tomamos del
        // request y lo acotamos al rango del producto (rules.min/max_term_days).
        $isSingle = PaymentFrequency::normalize($request->payment_frequency)?->value === 'SINGLE';
        $termDays = null;
        if ($isSingle) {
            $rules = $product->rules ?? [];
            $minDays = (int) ($rules['min_term_days'] ?? 1);
            $maxDays = (int) ($rules['max_term_days'] ?? 30);
            $requested = (int) ($request->term_days ?? ($rules['default_term_days'] ?? $minDays));
            $termDays = max($minDays, min($maxDays, $requested));
        }

        if ($product->type === ProductType::ARRENDAMIENTO) {
            // Arrendamiento: `amount` es el VALOR DEL BIEN; se cobra renta, no interés.
            // El anticipo (%) se acota al rango del producto (rules.lease.anticipo_pct_*).
            $lease = $product->rules['lease'] ?? [];
            $minPct = (float) ($lease['anticipo_pct_min'] ?? 0);
            $maxPct = (float) ($lease['anticipo_pct_max'] ?? 30);
            $defPct = (float) ($lease['anticipo_pct_default'] ?? 20);
            $downPct = $request->filled('down_payment_pct') ? (float) $request->down_payment_pct : $defPct;
            $downPct = max($minPct, min($maxPct, $downPct));

            $calculation = $this->leaseCalculator->calculateLeaseSimulation(
                (float) $request->amount,
                $downPct,
                (int) $request->term_months,
                $request->payment_frequency,
                (float) $product->annual_rate,
                $lease,
            );
        } else {
            $calculation = $this->loanCalculator->calculateSimulation(
                $request->amount,
                $request->term_months,
                $request->payment_frequency,
                $product->annual_rate,
                $product->opening_commission_rate,
                $termDays
            );
        }

        return $this->success([
            'simulation' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                ...$calculation,
            ],
        ]);
    }

}
