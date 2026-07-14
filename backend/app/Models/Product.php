<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUuid, HasTenant, SoftDeletes, HasAuditFields;

    protected $attributes = [
        'rules' => '{}',
        'eligibility_rules' => '[]',
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'description',
        'icon',
        'min_amount',
        'max_amount',
        'min_term_months',
        'max_term_months',
        'interest_rate',
        'opening_commission',
        'late_fee_rate',
        'payment_frequencies',
        'required_documents',
        'eligibility_rules',
        'rules',
        'required_docs',
        'extra_fields',
        'onboarding_steps',
        'is_active',
        'is_default',
        'display_order',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'type' => ProductType::class,
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'opening_commission' => 'decimal:2',
        'late_fee_rate' => 'decimal:2',
        'payment_frequencies' => 'array',
        'required_documents' => 'array',
        'eligibility_rules' => 'array',
        'rules' => 'array',
        'required_docs' => 'array',
        'extra_fields' => 'array',
        'onboarding_steps' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get applications for this product.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Devuelve la lista plana de TYPES de documentos requeridos.
     *
     * Acepta los 3 formatos historicos en BD:
     *   - Legacy flat: ['INE_FRONT', 'INE_BACK']
     *   - Segmentado: {nationals: [...], foreigners: [...]} con strings u objetos
     *   - Objetos:    {nationals: [{type, required, description}, ...]}
     *
     * Solo incluye docs marcados required=true (los opcionales no bloquean
     * el submit ni cuentan como "faltantes").
     *
     * Si $isForeigner es null y la estructura esta segmentada, combina ambas
     * categorias. Es el comportamiento seguro para validaciones que no
     * conocen aun la nacionalidad del aplicante.
     */
    public function requiredDocumentTypes(?bool $isForeigner = null): array
    {
        $raw = $this->required_documents ?? $this->required_docs ?? [];
        if (!$raw) {
            return [];
        }

        if (is_array($raw) && (isset($raw['nationals']) || isset($raw['foreigners']))) {
            if ($isForeigner === true) {
                $items = $raw['foreigners'] ?? [];
            } elseif ($isForeigner === false) {
                $items = $raw['nationals'] ?? [];
            } else {
                $items = array_merge($raw['nationals'] ?? [], $raw['foreigners'] ?? []);
            }
        } else {
            $items = $raw;
        }

        $types = array_map(function ($item) {
            if (is_array($item)) {
                $required = $item['required'] ?? true;
                return $required ? ($item['type'] ?? null) : null;
            }
            return $item;
        }, $items);

        return array_values(array_unique(array_filter($types, fn ($t) => is_string($t) && $t !== '')));
    }

    /**
     * Scope to active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('display_order');
    }

    /**
     * Get the minimum amount for this product.
     */
    public function getMinAmountAttribute(): float
    {
        // First check database column, then fall back to rules
        return $this->attributes['min_amount'] ?? $this->rules['min_amount'] ?? 5000;
    }

    /**
     * Get the maximum amount for this product.
     */
    public function getMaxAmountAttribute(): float
    {
        // First check database column, then fall back to rules
        return $this->attributes['max_amount'] ?? $this->rules['max_amount'] ?? 500000;
    }

    /**
     * Get the annual interest rate.
     */
    public function getAnnualRateAttribute(): float
    {
        // First check database column, then fall back to rules
        return $this->attributes['interest_rate']
            ?? $this->rules['annual_rate']
            ?? $this->rules['interest_rate']
            ?? 45.0;
    }

    /**
     * Get minimum term in months.
     */
    public function getMinTermMonthsAttribute(): int
    {
        // First check database column, then fall back to rules
        return $this->attributes['min_term_months']
            ?? $this->rules['min_term_months']
            ?? $this->rules['min_term']
            ?? 3;
    }

    /**
     * Get maximum term in months.
     */
    public function getMaxTermMonthsAttribute(): int
    {
        // First check database column, then fall back to rules
        return $this->attributes['max_term_months']
            ?? $this->rules['max_term_months']
            ?? $this->rules['max_term']
            ?? 48;
    }

    /**
     * Whether the product term is measured in days (BULLET products like MoneyCapital).
     */
    public function getTermInDaysAttribute(): bool
    {
        return (bool) ($this->rules['term_in_days'] ?? false);
    }

    /**
     * Get minimum term in days (only meaningful when term_in_days).
     */
    public function getMinTermDaysAttribute(): int
    {
        return $this->rules['min_term_days'] ?? 1;
    }

    /**
     * Get maximum term in days (only meaningful when term_in_days).
     */
    public function getMaxTermDaysAttribute(): int
    {
        return $this->rules['max_term_days'] ?? 30;
    }

    /**
     * Get opening commission rate.
     */
    public function getOpeningCommissionRateAttribute(): float
    {
        // First check database column, then fall back to rules
        return $this->attributes['opening_commission']
            ?? $this->rules['opening_commission']
            ?? 0;
    }

    /**
     * Check if an amount is valid for this product.
     */
    public function isAmountValid(float $amount): bool
    {
        return $amount >= $this->min_amount && $amount <= $this->max_amount;
    }

    /**
     * Check if a term is valid for this product.
     */
    public function isTermValid(int $months): bool
    {
        return $months >= $this->min_term_months && $months <= $this->max_term_months;
    }
}
