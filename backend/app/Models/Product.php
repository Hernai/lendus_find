<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUuid, HasTenant, SoftDeletes;

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
        'is_active',
        'display_order',
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
        'is_active' => 'boolean',
    ];

    /**
     * Get applications for this product.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
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
