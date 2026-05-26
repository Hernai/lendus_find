<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanExtension extends Model
{
    use HasUuid, HasTenant, HasAuditFields, SoftDeletes;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'tenant_id', 'loan_id', 'days_added', 'fee_amount',
        'previous_due_date', 'new_due_date', 'status',
        'requested_at', 'approved_at', 'approved_by', 'rejection_reason',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'days_added' => 'integer',
        'fee_amount' => 'decimal:2',
        'previous_due_date' => 'date',
        'new_due_date' => 'date',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
