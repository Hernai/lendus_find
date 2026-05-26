<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanPayment extends Model
{
    use HasUuid, HasTenant, HasAuditFields, SoftDeletes;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_REFUNDED = 'REFUNDED';

    public const CHANNEL_CONEKTA = 'CONEKTA';
    public const CHANNEL_OPENPAY = 'OPENPAY';
    public const CHANNEL_STP = 'STP';
    public const CHANNEL_MANUAL = 'MANUAL';

    protected $fillable = [
        'tenant_id', 'loan_id', 'amount', 'paid_at', 'status', 'channel',
        'provider', 'provider_reference', 'metadata',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
