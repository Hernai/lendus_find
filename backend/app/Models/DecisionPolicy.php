<?php

namespace App\Models;

use App\Enums\DecisionMode;
use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Política versionada del motor de decisión.
 *
 * product_id NULL = política de tenant (filtro telefónico, cooldown);
 * product_id presente = política de producto (scoring, bandas, oferta,
 * graduación). Las versiones son inmutables: editar crea una versión nueva y
 * solo una puede estar activa por alcance (índices únicos parciales).
 */
class DecisionPolicy extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant, HasAuditFields;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'version',
        'mode',
        'is_active',
        'rules',
        'notes',
        'activated_at',
        'activated_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_active' => 'boolean',
        'rules' => 'array',
        'activated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'activated_by');
    }

    public function isTenantPolicy(): bool
    {
        return $this->product_id === null;
    }

    public function modeEnum(): DecisionMode
    {
        return DecisionMode::from($this->mode);
    }

    /** Lee una regla anidada del JSONB con default, ej. rule('offer.validity_hours', 72). */
    public function rule(string $path, mixed $default = null): mixed
    {
        return data_get($this->rules, $path, $default);
    }

    /**
     * Política activa para un alcance (product_id null = política de tenant).
     * El tenant scoping lo aporta el global scope de HasTenant.
     */
    public static function activeFor(?string $productId): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->when(
                $productId === null,
                fn ($q) => $q->whereNull('product_id'),
                fn ($q) => $q->where('product_id', $productId)
            )
            ->first();
    }

    /** Siguiente número de versión para el alcance dentro del tenant actual. */
    public static function nextVersionFor(string $tenantId, ?string $productId): int
    {
        $max = static::withoutGlobalScopes()
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->when(
                $productId === null,
                fn ($q) => $q->whereNull('product_id'),
                fn ($q) => $q->where('product_id', $productId)
            )
            ->max('version');

        return ((int) $max) + 1;
    }

    /** Activa esta versión desactivando la activa anterior del mismo alcance. */
    public function activate(?string $staffId = null): void
    {
        DB::transaction(function () use ($staffId) {
            static::query()
                ->where('is_active', true)
                ->when(
                    $this->product_id === null,
                    fn ($q) => $q->whereNull('product_id'),
                    fn ($q) => $q->where('product_id', $this->product_id)
                )
                ->where('id', '!=', $this->id)
                ->update(['is_active' => false]);

            $this->update([
                'is_active' => true,
                'activated_at' => now(),
                'activated_by' => $staffId,
            ]);
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** @return array<string, mixed> */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'version' => $this->version,
            'mode' => $this->mode,
            'mode_label' => $this->modeEnum()->label(),
            'is_active' => $this->is_active,
            'rules' => $this->rules,
            'notes' => $this->notes,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'activated_by' => $this->activatedBy?->full_name ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
