<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Préstamo desembolsado. Vida posterior a Application APPROVED.
 *
 * Solo se crea para tenants con `features.loan_portfolio=true`. La
 * relación es 1:1 con Application — cuando el applicant acepta la oferta
 * preaprobada y se transiciona Application a DISBURSED, el ApplicationService
 * dispara LoanService::createFromApplication() que crea este registro.
 */
class Loan extends Model
{
    use HasUuid, HasTenant, HasAuditFields, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'application_id',
        'applicant_account_id',
        'person_id',
        'bank_account_id',
        'principal_amount',
        'interest_rate',
        'term_days',
        'opening_commission_amount',
        'total_to_pay',
        'status',
        'disbursed_at',
        'due_date',
        'completed_at',
        'outstanding_balance',
        'paid_amount',
        'late_fee_accrued',
        'disbursement_provider',
        'disbursement_reference',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'term_days' => 'integer',
        'opening_commission_amount' => 'decimal:2',
        'total_to_pay' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'late_fee_accrued' => 'decimal:2',
        'status' => LoanStatus::class,
        'disbursed_at' => 'datetime',
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function application(): BelongsTo { return $this->belongsTo(Application::class); }
    public function applicantAccount(): BelongsTo { return $this->belongsTo(ApplicantAccount::class); }
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function bankAccount(): BelongsTo { return $this->belongsTo(BankAccount::class); }
    public function payments(): HasMany { return $this->hasMany(LoanPayment::class); }
    public function extensions(): HasMany { return $this->hasMany(LoanExtension::class); }
    public function rewards(): HasMany { return $this->hasMany(LoanReward::class); }

    public function daysUntilDue(): int
    {
        return (int) Carbon::today()->diffInDays(Carbon::parse($this->due_date), false);
    }

    public function isOverdue(): bool
    {
        if (in_array($this->status, [LoanStatus::COMPLETED, LoanStatus::DEFAULT], true)) {
            return $this->status === LoanStatus::DEFAULT;
        }
        return $this->daysUntilDue() < 0;
    }

    public function recalculateBalance(): void
    {
        $totalPaid = $this->payments()->where('status', 'COMPLETED')->sum('amount');
        $this->paid_amount = $totalPaid;
        $this->outstanding_balance = max(0, (float) $this->total_to_pay - (float) $totalPaid);
        if ($this->outstanding_balance <= 0 && $this->status !== LoanStatus::COMPLETED) {
            $this->status = LoanStatus::COMPLETED;
            $this->completed_at = now();
        }
        $this->save();
    }
}
