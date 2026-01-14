<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory, HasUuid, HasTenant, SoftDeletes, HasAuditFields;

    protected $fillable = [
        'tenant_id',
        'application_id',
        'applicant_id',
        'type',
        'name',
        'original_name',
        'description',
        'file_path',
        'storage_path',
        'file_name',
        'mime_type',
        'file_size',
        'size',
        'storage_disk',
        'status',
        'rejection_reason',
        'rejection_comment',
        'reviewed_by',
        'reviewed_at',
        'metadata',
        'checksum',
        'is_sensitive',
    ];

    protected $casts = [
        'type' => DocumentType::class,
        'status' => DocumentStatus::class,
        'metadata' => 'array',
        'is_sensitive' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the application.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the applicant.
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * Get the reviewer.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the signed URL for the document.
     */
    public function getSignedUrl(int $expiration = 60): string
    {
        return Storage::disk($this->storage_disk)->temporaryUrl(
            $this->file_path,
            now()->addMinutes($expiration)
        );
    }

    /**
     * Approve the document.
     */
    public function approve(int $userId): void
    {
        $this->update([
            'status' => DocumentStatus::APPROVED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    /**
     * Reject the document.
     */
    public function reject(int $userId, string $reason): void
    {
        $this->update([
            'status' => DocumentStatus::REJECTED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Check if document is pending.
     */
    public function isPending(): bool
    {
        return $this->status === DocumentStatus::PENDING;
    }

    /**
     * Check if document is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === DocumentStatus::APPROVED;
    }

    /**
     * Check if document is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === DocumentStatus::REJECTED;
    }

    /**
     * Scope by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
