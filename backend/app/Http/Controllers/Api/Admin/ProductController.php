<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * List all products.
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $query = Product::where('tenant_id', $tenant->id);

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')->get();

        return response()->json([
            'data' => $products->map(fn($p) => $this->formatProduct($p))
        ]);
    }

    /**
     * Create a new product.
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:products,code',
            'type' => 'required|in:PERSONAL,AUTO,HIPOTECARIO,PYME,NOMINA,ARRENDAMIENTO',
            'description' => 'nullable|string|max:500',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'min_term_months' => 'nullable|integer|min:1',
            'max_term_months' => 'nullable|integer|min:1',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'opening_commission' => 'required|numeric|min:0|max:100',
            'late_fee_rate' => 'nullable|numeric|min:0|max:100',
            'payment_frequencies' => 'required|array|min:1',
            'payment_frequencies.*' => 'in:WEEKLY,BIWEEKLY,QUINCENAL,MONTHLY,MENSUAL',
            'term_config' => 'nullable|array',
            'term_config.*.available_terms' => 'required_with:term_config|array|min:1',
            'term_config.*.available_terms.*' => 'integer|min:1',
            'required_documents' => 'nullable|array',
            'eligibility_rules' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Extract min/max terms from term_config for backwards compatibility
        $termConfig = $request->term_config ?? [];
        $minTermMonths = $request->min_term_months ?? 3;
        $maxTermMonths = $request->max_term_months ?? 48;

        // Get min/max from MONTHLY available_terms if available, otherwise use first config
        if (!empty($termConfig)) {
            if (isset($termConfig['MONTHLY']['available_terms']) && !empty($termConfig['MONTHLY']['available_terms'])) {
                $terms = $termConfig['MONTHLY']['available_terms'];
                $minTermMonths = min($terms);
                $maxTermMonths = max($terms);
            } elseif (!empty($termConfig)) {
                $firstConfig = reset($termConfig);
                if (isset($firstConfig['available_terms']) && !empty($firstConfig['available_terms'])) {
                    $minTermMonths = min($firstConfig['available_terms']);
                    $maxTermMonths = max($firstConfig['available_terms']);
                }
            }
        }

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'type' => $request->type,
            'description' => $request->description,
            'min_amount' => $request->min_amount,
            'max_amount' => $request->max_amount,
            'min_term_months' => $minTermMonths,
            'max_term_months' => $maxTermMonths,
            'interest_rate' => $request->interest_rate,
            'opening_commission' => $request->opening_commission,
            'late_fee_rate' => $request->late_fee_rate ?? 0,
            'payment_frequencies' => $request->payment_frequencies,
            'required_documents' => $request->required_documents ?? [],
            'eligibility_rules' => $request->eligibility_rules ?? [],
            'rules' => ['term_config' => $termConfig],
            'is_active' => $request->input('is_active', true),
        ]);

        return response()->json([
            'message' => 'Product created',
            'data' => $this->formatProduct($product)
        ], 201);
    }

    /**
     * Get a specific product.
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($product->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json([
            'data' => $this->formatProduct($product)
        ]);
    }

    /**
     * Update a product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($product->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'code' => 'sometimes|string|max:20|unique:products,code,' . $product->id,
            'type' => 'sometimes|in:PERSONAL,AUTO,HIPOTECARIO,PYME,NOMINA,ARRENDAMIENTO',
            'description' => 'nullable|string|max:500',
            'min_amount' => 'sometimes|numeric|min:0',
            'max_amount' => 'sometimes|numeric',
            'min_term_months' => 'nullable|integer|min:1',
            'max_term_months' => 'nullable|integer|min:1',
            'interest_rate' => 'sometimes|numeric|min:0|max:100',
            'opening_commission' => 'sometimes|numeric|min:0|max:100',
            'late_fee_rate' => 'nullable|numeric|min:0|max:100',
            'payment_frequencies' => 'sometimes|array|min:1',
            'payment_frequencies.*' => 'in:WEEKLY,BIWEEKLY,QUINCENAL,MONTHLY,MENSUAL',
            'term_config' => 'nullable|array',
            'term_config.*.available_terms' => 'array|min:1',
            'term_config.*.available_terms.*' => 'integer|min:1',
            'required_documents' => 'nullable|array',
            'eligibility_rules' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $product->fill($request->only([
            'name', 'code', 'type', 'description',
            'min_amount', 'max_amount', 'min_term_months', 'max_term_months',
            'interest_rate', 'opening_commission', 'late_fee_rate',
            'payment_frequencies', 'required_documents', 'eligibility_rules',
            'is_active'
        ]));

        if ($request->has('code')) {
            $product->code = strtoupper($request->code);
        }

        // Handle term_config
        if ($request->has('term_config')) {
            $termConfig = $request->term_config;

            // Store in rules column
            $rules = $product->rules ?? [];
            $rules['term_config'] = $termConfig;
            $product->rules = $rules;

            // Update legacy min/max term fields for backwards compatibility
            if (!empty($termConfig)) {
                if (isset($termConfig['MONTHLY']['available_terms']) && !empty($termConfig['MONTHLY']['available_terms'])) {
                    $terms = $termConfig['MONTHLY']['available_terms'];
                    $product->min_term_months = min($terms);
                    $product->max_term_months = max($terms);
                } elseif (!empty($termConfig)) {
                    $firstConfig = reset($termConfig);
                    if (isset($firstConfig['available_terms']) && !empty($firstConfig['available_terms'])) {
                        $product->min_term_months = min($firstConfig['available_terms']);
                        $product->max_term_months = max($firstConfig['available_terms']);
                    }
                }
            }
        }

        $product->save();

        return response()->json([
            'message' => 'Product updated',
            'data' => $this->formatProduct($product->fresh())
        ]);
    }

    /**
     * Delete a product.
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        if ($product->tenant_id !== $tenant->id) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Check if product has applications
        if ($product->applications()->exists()) {
            return response()->json([
                'message' => 'Cannot delete product with existing applications. Deactivate it instead.'
            ], 400);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted'
        ]);
    }

    /**
     * Format product for response.
     */
    private function formatProduct(Product $product): array
    {
        // Extract term_config from rules if available
        $termConfig = $product->rules['term_config'] ?? null;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'type' => $product->type,
            'description' => $product->description,
            'min_amount' => (float) $product->min_amount,
            'max_amount' => (float) $product->max_amount,
            'min_term_months' => $product->min_term_months,
            'max_term_months' => $product->max_term_months,
            'interest_rate' => (float) $product->interest_rate,
            'opening_commission' => (float) $product->opening_commission,
            'late_fee_rate' => (float) ($product->late_fee_rate ?? 0),
            'payment_frequencies' => $product->payment_frequencies,
            'term_config' => $termConfig,
            'required_documents' => $product->required_documents,
            'eligibility_rules' => $product->eligibility_rules,
            'is_active' => $product->is_active,
            'applications_count' => $product->applications_count ?? $product->applications()->count(),
            'created_at' => $product->created_at->toIso8601String(),
            'updated_at' => $product->updated_at->toIso8601String(),
        ];
    }
}
