<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Application extends Model
{
    use HasFactory, HasUuid, HasTenant, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'tenant_id',
        'applicant_id',
        'product_id',
        'folio',
        'requested_amount',
        'approved_amount',
        'term_months',
        'payment_frequency',
        'interest_rate',
        'opening_commission',
        'monthly_payment',
        'total_to_pay',
        'cat',
        'purpose',
        'purpose_description',
        'status',
        'status_history',
        'assigned_to',
        'assigned_at',
        'rejection_reason',
        'internal_notes',
        'scoring_data',
        'risk_score',
        'risk_level',
        'approved_at',
        'disbursed_at',
        'disbursement_reference',
        'extra_data',
    ];

    protected $casts = [
        'status' => ApplicationStatus::class,
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'opening_commission' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'total_to_pay' => 'decimal:2',
        'cat' => 'decimal:2',
        'status_history' => 'array',
        'scoring_data' => 'array',
        'extra_data' => 'array',
        'assigned_at' => 'datetime',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Generate folio on create
        static::creating(function ($application) {
            if (empty($application->folio)) {
                $application->folio = static::generateFolio($application->tenant_id);
            }
        });
    }

    /**
     * Generate a unique folio for the application.
     *
     * Uses database-level locking to prevent race conditions
     * when multiple applications are created simultaneously.
     */
    public static function generateFolio(?string $tenantId): string
    {
        $prefix = 'LEN'; // Can be customized per tenant
        $year = date('Y');

        return DB::transaction(function () use ($prefix, $year, $tenantId) {
            // Use FOR UPDATE lock to prevent race conditions
            $lastFolio = static::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('folio', 'LIKE', "{$prefix}-{$year}-%")
                ->orderByRaw("CAST(SUBSTRING(folio FROM '\\d+$') AS INTEGER) DESC")
                ->lockForUpdate()
                ->value('folio');

            if ($lastFolio) {
                preg_match('/(\d+)$/', $lastFolio, $matches);
                $sequence = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
            } else {
                $sequence = 1;
            }

            // Generate folio with proper sequence
            $folio = sprintf('%s-%s-%05d', $prefix, $year, $sequence);

            // Double-check uniqueness (belt and suspenders)
            $attempts = 0;
            $maxAttempts = 5;

            while (static::withoutGlobalScopes()->where('folio', $folio)->exists() && $attempts < $maxAttempts) {
                $sequence++;
                $folio = sprintf('%s-%s-%05d', $prefix, $year, $sequence);
                $attempts++;
            }

            // Fallback: add timestamp to guarantee uniqueness
            if ($attempts >= $maxAttempts) {
                $folio = sprintf('%s-%s-%05d-%s', $prefix, $year, $sequence, substr(uniqid(), -4));
            }

            return $folio;
        }, 3); // 3 retry attempts on deadlock
    }

    /**
     * Get the applicant.
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the assigned agent.
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the documents for this application.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get the references for this application.
     */
    public function references(): HasMany
    {
        return $this->hasMany(Reference::class);
    }

    /**
     * Get the notes for this application.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ApplicationNote::class)->orderByDesc('created_at');
    }

    /**
     * Change the status and record in history.
     */
    public function changeStatus(string $status, ?string $reason = null, ?string $userId = null): void
    {
        $previousStatus = $this->status->value;

        // Look up user to get name for history
        $changedBy = $userId ? User::find($userId) : null;

        $history = $this->status_history ?? [];

        $history[] = [
            'from' => $previousStatus,
            'to' => $status,
            'reason' => $reason,
            'user_id' => $userId,
            'user_name' => $changedBy?->name,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->status = $status;
        $this->status_history = $history;

        if ($status === ApplicationStatus::APPROVED->value) {
            $this->approved_at = now();
        } elseif ($status === ApplicationStatus::REJECTED->value && $reason) {
            $this->rejection_reason = $reason;
        } elseif ($status === ApplicationStatus::DISBURSED->value) {
            $this->disbursed_at = now();
        }

        $this->save();

        // Broadcast el cambio de status
        event(new \App\Events\ApplicationStatusChanged(
            $this,
            $previousStatus,
            $status,
            $reason,
            $changedBy
        ));
    }

    /**
     * Add an entry to the status_history without changing status.
     * Used for timeline events like data corrections, document uploads, etc.
     */
    public function addTimelineEntry(string $action, array $data = [], ?string $userId = null): void
    {
        $user = $userId ? User::find($userId) : null;

        $history = $this->status_history ?? [];

        $history[] = array_merge([
            'action' => $action,
            'user_id' => $userId,
            'user_name' => $user?->name,
            'timestamp' => now()->toIso8601String(),
        ], $data);

        $this->status_history = $history;
        $this->save();
    }

    /**
     * Check if the application can be edited.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [
            ApplicationStatus::DRAFT,
            ApplicationStatus::DOCS_PENDING,
            ApplicationStatus::CORRECTIONS_PENDING,
        ]);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to pending applications.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [
            ApplicationStatus::SUBMITTED->value,
            ApplicationStatus::IN_REVIEW->value,
            ApplicationStatus::DOCS_PENDING->value,
            ApplicationStatus::CORRECTIONS_PENDING->value,
            ApplicationStatus::COUNTER_OFFERED->value,
        ]);
    }

    /**
     * Create a counter-offer.
     */
    public function createCounterOffer(
        float $amount,
        int $termMonths,
        float $interestRate,
        string $paymentFrequency,
        ?string $reason = null,
        ?string $userId = null
    ): void {
        $this->approved_amount = $amount;
        $this->term_months = $termMonths;
        $this->interest_rate = $interestRate;
        $this->payment_frequency = $paymentFrequency;

        // Recalculate payment
        $periodsPerYear = match ($paymentFrequency) {
            'WEEKLY' => 52,
            'BIWEEKLY', 'QUINCENAL' => 26,
            default => 12,
        };

        $totalPeriods = match ($paymentFrequency) {
            'WEEKLY' => $termMonths * 4.33,
            'BIWEEKLY', 'QUINCENAL' => $termMonths * 2.17,
            default => $termMonths,
        };

        $totalPeriods = (int) round($totalPeriods);
        $periodRate = ($interestRate / 100) / $periodsPerYear;

        if ($periodRate > 0) {
            $payment = $amount * ($periodRate * pow(1 + $periodRate, $totalPeriods)) /
                (pow(1 + $periodRate, $totalPeriods) - 1);
        } else {
            $payment = $amount / $totalPeriods;
        }

        $this->monthly_payment = round($payment, 2);
        $this->total_to_pay = round($payment * $totalPeriods, 2);

        $this->changeStatus(ApplicationStatus::COUNTER_OFFERED->value, $reason, $userId);
    }

    /**
     * Accept counter-offer.
     */
    public function acceptCounterOffer(?string $userId = null): void
    {
        $this->changeStatus(ApplicationStatus::APPROVED->value, 'Contraoferta aceptada', $userId);
    }

    /**
     * Reject counter-offer.
     */
    public function rejectCounterOffer(?string $reason = null, ?string $userId = null): void
    {
        $this->changeStatus(ApplicationStatus::CANCELLED->value, $reason ?? 'Contraoferta rechazada', $userId);
    }
}
