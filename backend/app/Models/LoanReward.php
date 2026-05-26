<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanReward extends Model
{
    use HasUuid, HasTenant, HasAuditFields, SoftDeletes;

    public const TYPE_PUNCTUAL_PAYMENT = 'PUNCTUAL_PAYMENT';
    public const TYPE_REFERRAL = 'REFERRAL';
    public const TYPE_MILESTONE = 'MILESTONE';

    protected $fillable = [
        'tenant_id', 'applicant_account_id', 'loan_id', 'type', 'points',
        'description', 'earned_at', 'redeemed_at', 'metadata',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'points' => 'integer',
        'earned_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function applicantAccount(): BelongsTo
    {
        return $this->belongsTo(ApplicantAccount::class);
    }
}
