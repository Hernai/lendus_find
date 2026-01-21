<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Enums\DocumentType;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Staff Product Management Controller (v2).
 *
 * Handles CRUD operations for credit products.
 */
class ProductController extends Controller
{
    use ApiResponses;
    /**
     * List all products with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        // Search by name or code
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('code', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->has('active')) {
            $active = filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $active);
        }

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'display_order');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $products = $query->withCount('applications')->paginate($perPage);

        return $this->success([
            'products' => collect($products->items())->map(fn ($p) => $this->formatProduct($p)),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Create a new product.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/i',
                Rule::unique('products')->where(function ($query) {
                    return $query->where('tenant_id', app('tenant.id'));
                }),
            ],
            'type' => 'required|string|in:PERSONAL,AUTO,HIPOTECARIO,PYME,NOMINA,ARRENDAMIENTO',
            'description' => 'nullable|string|max:1000',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'min_term_months' => 'required|integer|min:1',
            'max_term_months' => 'required|integer|gte:min_term_months',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'opening_commission' => 'nullable|numeric|min:0|max:100',
            'late_fee_rate' => 'nullable|numeric|min:0|max:100',
            'payment_frequencies' => 'required|array|min:1',
            'payment_frequencies.*' => 'string|in:WEEKLY,BIWEEKLY,MONTHLY',
            'term_config' => 'nullable|array',
            'required_documents' => 'nullable|array',
            'required_documents.*' => ['string', Rule::in(DocumentType::values())],
            'eligibility_rules' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError('Error de validación', $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $data['code'] = strtoupper($data['code']);
        $data['tenant_id'] = app('tenant.id');

        // Set default values
        $data['opening_commission'] = $data['opening_commission'] ?? 0;
        $data['late_fee_rate'] = $data['late_fee_rate'] ?? 0;
        $data['required_documents'] = $data['required_documents'] ?? [];
        $data['eligibility_rules'] = $data['eligibility_rules'] ?? [];
        $data['is_active'] = $data['is_active'] ?? true;

        // Store term_config in rules JSONB
        if (isset($data['term_config'])) {
            $data['rules'] = array_merge($data['rules'] ?? [], ['term_config' => $data['term_config']]);
            unset($data['term_config']);
        }

        // Get max display_order and add 1
        $maxOrder = Product::where('tenant_id', app('tenant.id'))->max('display_order') ?? 0;
        $data['display_order'] = $maxOrder + 1;

        $product = Product::create($data);
        $product->loadCount('applications');

        return $this->created([
            'product' => $this->formatProduct($product),
        ]);
    }

    /**
     * Update a product.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->notFound('Producto no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9_-]+$/i',
                Rule::unique('products')->where(function ($query) {
                    return $query->where('tenant_id', app('tenant.id'));
                })->ignore($product->id),
            ],
            'type' => 'sometimes|required|string|in:PERSONAL,AUTO,HIPOTECARIO,PYME,NOMINA,ARRENDAMIENTO',
            'description' => 'nullable|string|max:1000',
            'min_amount' => 'sometimes|required|numeric|min:0',
            'max_amount' => 'sometimes|required|numeric',
            'min_term_months' => 'sometimes|required|integer|min:1',
            'max_term_months' => 'sometimes|required|integer',
            'interest_rate' => 'sometimes|required|numeric|min:0|max:100',
            'opening_commission' => 'nullable|numeric|min:0|max:100',
            'late_fee_rate' => 'nullable|numeric|min:0|max:100',
            'payment_frequencies' => 'sometimes|required|array|min:1',
            'payment_frequencies.*' => 'string|in:WEEKLY,BIWEEKLY,MONTHLY',
            'term_config' => 'nullable|array',
            'required_documents' => 'nullable|array',
            'required_documents.*' => ['string', Rule::in(DocumentType::values())],
            'eligibility_rules' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError('Error de validación', $validator->errors()->toArray());
        }

        $data = $validator->validated();

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        // Store term_config in rules JSONB
        if (isset($data['term_config'])) {
            $rules = $product->rules ?? [];
            $rules['term_config'] = $data['term_config'];
            $data['rules'] = $rules;
            unset($data['term_config']);
        }

        $product->update($data);
        $product->loadCount('applications');

        return $this->success([
            'product' => $this->formatProduct($product),
        ]);
    }

    /**
     * Delete a product.
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::withCount('applications')->find($id);

        if (!$product) {
            return $this->notFound('Producto no encontrado');
        }

        // Check if product has applications
        if ($product->applications_count > 0) {
            return $this->badRequest('HAS_APPLICATIONS', 'No se puede eliminar un producto con solicitudes asociadas');
        }

        $product->delete();

        return $this->success(null, 'Producto eliminado correctamente');
    }

    /**
     * Format product for response.
     */
    private function formatProduct(Product $product): array
    {
        $rules = $product->rules ?? [];

        return [
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'type' => $product->type?->value ?? $product->type,
            'description' => $product->description,
            'min_amount' => (float) $product->min_amount,
            'max_amount' => (float) $product->max_amount,
            'min_term_months' => (int) $product->min_term_months,
            'max_term_months' => (int) $product->max_term_months,
            'interest_rate' => (float) $product->interest_rate,
            'opening_commission' => (float) ($product->opening_commission ?? 0),
            'late_fee_rate' => (float) ($product->late_fee_rate ?? 0),
            'payment_frequencies' => $product->payment_frequencies ?? [],
            'term_config' => $rules['term_config'] ?? null,
            'required_documents' => $product->required_documents ?? [],
            'eligibility_rules' => $product->eligibility_rules ?? [],
            'is_active' => (bool) $product->is_active,
            'display_order' => $product->display_order,
            'applications_count' => $product->applications_count ?? 0,
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }
}
