<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Application model.
 *
 * Currently only supports individual applicants (person_id).
 * Company support (company_id) is reserved for future implementation.
 * Stores snapshots of data at application time for historical record.
 */
class Application extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasTenant, HasAuditFields;

    protected $table = 'applications';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'applicant_type',
        'person_id',
        'company_id',
        'submitted_by_account_id',
        'submitted_by_member_id',
        'snapshot_references',
        'snapshot_data',
        'requested_amount',
        'requested_term_months',
        'requested_term_days',
        'purpose',
        'purpose_description',
        'interest_rate',
        'monthly_payment',
        'total_interest',
        'total_amount',
        'cat',
        'approved_amount',
        'approved_term_months',
        'approved_term_days',
        'approved_interest_rate',
        'approved_monthly_payment',
        'status',
        'status_changed_at',
        'status_changed_by',
        'status_changed_by_type',
        'submitted_at',
        'submission_ip',
        'submission_device',
        'assigned_to',
        'assigned_at',
        'assigned_by',
        'decision',
        'decision_at',
        'decision_by',
        'decision_notes',
        'rejection_reason',
        'counter_offer',
        'counter_offer_accepted',
        'counter_offer_responded_at',
        'verification_checklist',
        'risk_level',
        'risk_data',
        'synced_at',
        'external_id',
        'external_system',
        'sync_data',
        'expires_at',
        'expiration_notified',
        'notes',
        'metadata',
        'renewal_of_loan_id',
        'cooldown_waived_at',
        'cooldown_waived_by',
        'cooldown_waived_reason',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'snapshot_references' => 'array',
        'snapshot_data' => 'array',
        'requested_amount' => 'decimal:2',
        'requested_term_days' => 'integer',
        'interest_rate' => 'decimal:4',
        'monthly_payment' => 'decimal:2',
        'total_interest' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'cat' => 'decimal:4',
        'approved_amount' => 'decimal:2',
        'approved_term_days' => 'integer',
        'approved_interest_rate' => 'decimal:4',
        'approved_monthly_payment' => 'decimal:2',
        'status_changed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'assigned_at' => 'datetime',
        'decision_at' => 'datetime',
        'counter_offer' => 'array',
        'counter_offer_accepted' => 'boolean',
        'counter_offer_responded_at' => 'datetime',
        'verification_checklist' => 'array',
        'risk_data' => 'array',
        'synced_at' => 'datetime',
        'sync_data' => 'array',
        'expires_at' => 'datetime',
        'expiration_notified' => 'boolean',
        'notes' => 'array',
        'metadata' => 'array',
        'cooldown_waived_at' => 'datetime',
    ];

    // =====================================================
    // Applicant Types
    // =====================================================

    public const TYPE_INDIVIDUAL = 'INDIVIDUAL';
    public const TYPE_COMPANY = 'COMPANY';

    public static function applicantTypes(): array
    {
        return [
            self::TYPE_INDIVIDUAL => 'Persona Física',
            self::TYPE_COMPANY => 'Persona Moral',
        ];
    }

    // =====================================================
    // Statuses
    // =====================================================

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_IN_REVIEW = 'IN_REVIEW';
    public const STATUS_DOCS_PENDING = 'DOCS_PENDING';
    public const STATUS_CORRECTIONS_PENDING = 'CORRECTIONS_PENDING';
    public const STATUS_ANALYST_REVIEW = 'ANALYST_REVIEW';
    public const STATUS_SUPERVISOR_REVIEW = 'SUPERVISOR_REVIEW';
    public const STATUS_COUNTER_OFFERED = 'COUNTER_OFFERED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_SYNCED = 'SYNCED';

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_SUBMITTED => 'Enviada',
            self::STATUS_IN_REVIEW => 'En revisión',
            self::STATUS_DOCS_PENDING => 'Documentos pendientes',
            self::STATUS_CORRECTIONS_PENDING => 'Correcciones pendientes',
            self::STATUS_ANALYST_REVIEW => 'Revisión de analista',
            self::STATUS_SUPERVISOR_REVIEW => 'Revisión de supervisor',
            self::STATUS_COUNTER_OFFERED => 'Contraoferta',
            self::STATUS_APPROVED => 'Aprobada',
            self::STATUS_REJECTED => 'Rechazada',
            self::STATUS_CANCELLED => 'Cancelada',
            self::STATUS_SYNCED => 'Sincronizada',
        ];
    }

    // =====================================================
    // Decisions
    // =====================================================

    public const DECISION_APPROVED = 'APPROVED';
    public const DECISION_REJECTED = 'REJECTED';
    public const DECISION_COUNTER_OFFER = 'COUNTER_OFFER';

    // =====================================================
    // Risk Levels
    // =====================================================

    public const RISK_LOW = 'LOW';
    public const RISK_MEDIUM = 'MEDIUM';
    public const RISK_HIGH = 'HIGH';
    public const RISK_VERY_HIGH = 'VERY_HIGH';

    public static function riskLevels(): array
    {
        return [
            self::RISK_LOW => 'Bajo',
            self::RISK_MEDIUM => 'Medio',
            self::RISK_HIGH => 'Alto',
            self::RISK_VERY_HIGH => 'Muy Alto',
        ];
    }

    // =====================================================
    // Status Transitions (State Machine)
    // =====================================================

    /**
     * Allowed status transitions.
     * Key: current status, Value: array of allowed next statuses.
     */
    private const STATUS_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SUBMITTED, self::STATUS_CANCELLED],
        self::STATUS_SUBMITTED => [self::STATUS_IN_REVIEW, self::STATUS_DOCS_PENDING, self::STATUS_CORRECTIONS_PENDING, self::STATUS_CANCELLED],
        self::STATUS_IN_REVIEW => [self::STATUS_DOCS_PENDING, self::STATUS_CORRECTIONS_PENDING, self::STATUS_ANALYST_REVIEW, self::STATUS_COUNTER_OFFERED, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_DOCS_PENDING => [self::STATUS_IN_REVIEW, self::STATUS_SUBMITTED, self::STATUS_COUNTER_OFFERED, self::STATUS_CANCELLED],
        self::STATUS_CORRECTIONS_PENDING => [self::STATUS_IN_REVIEW, self::STATUS_SUBMITTED, self::STATUS_CANCELLED],
        self::STATUS_ANALYST_REVIEW => [self::STATUS_SUPERVISOR_REVIEW, self::STATUS_DOCS_PENDING, self::STATUS_CORRECTIONS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_SUPERVISOR_REVIEW => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_COUNTER_OFFERED => [self::STATUS_APPROVED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_SYNCED, self::STATUS_CANCELLED],
        self::STATUS_REJECTED => [], // Terminal state
        self::STATUS_CANCELLED => [], // Terminal state
        self::STATUS_SYNCED => [], // Terminal state
    ];

    /**
     * Check if transition to new status is allowed.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $allowedTransitions = self::STATUS_TRANSITIONS[$this->status] ?? [];

        return in_array($newStatus, $allowedTransitions, true);
    }

    /**
     * Get allowed next statuses from current status.
     */
    public function getAllowedTransitions(): array
    {
        return self::STATUS_TRANSITIONS[$this->status] ?? [];
    }

    /**
     * Validate and throw if transition is not allowed.
     *
     * @throws \InvalidArgumentException
     */
    public function validateTransition(string $newStatus): void
    {
        if (!$this->canTransitionTo($newStatus)) {
            $currentLabel = self::statuses()[$this->status] ?? $this->status;
            $newLabel = self::statuses()[$newStatus] ?? $newStatus;
            throw new \InvalidArgumentException(
                "No se puede cambiar el estado de '{$currentLabel}' a '{$newLabel}'."
            );
        }
    }

    // =====================================================
    // Relationships
    // =====================================================

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Company relationship - reserved for future implementation.
     * Returns null as company applications are not yet supported.
     */
    public function company(): BelongsTo
    {
        // Company model not implemented yet - return null relationship
        return $this->belongsTo(Person::class, 'company_id')->whereRaw('1 = 0');
    }

    public function submittedByAccount(): BelongsTo
    {
        return $this->belongsTo(ApplicantAccount::class, 'submitted_by_account_id');
    }

    /**
     * Submitted by member - reserved for future company implementation.
     */
    public function submittedByMember(): BelongsTo
    {
        // CompanyMember model not implemented yet - return null relationship
        return $this->belongsTo(Person::class, 'submitted_by_member_id')->whereRaw('1 = 0');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'assigned_by');
    }

    public function decisionBy(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'decision_by');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class, 'application_id')
            ->orderByDesc('created_at');
    }

    /**
     * Documents attached directly to this application (polymorphic).
     * These are application-specific documents like PROOF_OF_ADDRESS.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Get documents used in this application via documentable_relations.
     *
     * Returns documents that have a USAGE relation with this application.
     * This is a polymorphic many-to-many relationship through documentable_relations.
     */
    public function usedDocuments(): BelongsToMany
    {
        return $this->morphToMany(
            Document::class,
            'relatable',
            'documentable_relations',
            'relatable_id',
            'document_id'
        )
            ->wherePivot('relation_context', 'USAGE')
            ->wherePivotNull('deleted_at')
            ->withPivot([
                'relation_context',
                'notes',
                'created_by',
                'created_by_type',
            ])
            ->withTimestamps();
    }

    public function references(): HasMany
    {
        return $this->hasMany(PersonReference::class, 'application_id');
    }

    // =====================================================
    // Accessors
    // =====================================================

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function getApplicantTypeLabelAttribute(): string
    {
        return self::applicantTypes()[$this->applicant_type] ?? $this->applicant_type;
    }

    public function getRiskLevelLabelAttribute(): ?string
    {
        return $this->risk_level ? (self::riskLevels()[$this->risk_level] ?? $this->risk_level) : null;
    }

    public function getIsIndividualAttribute(): bool
    {
        return $this->applicant_type === self::TYPE_INDIVIDUAL;
    }

    public function getIsCompanyAttribute(): bool
    {
        return $this->applicant_type === self::TYPE_COMPANY;
    }

    public function getApplicantAttribute(): ?Person
    {
        // Currently only individual applications are supported
        return $this->person;
    }

    public function getApplicantNameAttribute(): ?string
    {
        // Currently only individual applications are supported
        return $this->person?->full_name;
    }

    public function getHasCounterOfferAttribute(): bool
    {
        return !empty($this->counter_offer);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // =====================================================
    // Status Helpers
    // =====================================================

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isInReview(): bool
    {
        return in_array($this->status, [
            self::STATUS_IN_REVIEW,
            self::STATUS_ANALYST_REVIEW,
            self::STATUS_SUPERVISOR_REVIEW,
        ]);
    }

    public function isPendingDocs(): bool
    {
        return $this->status === self::STATUS_DOCS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isSynced(): bool
    {
        return $this->status === self::STATUS_SYNCED;
    }

    public function isActive(): bool
    {
        return !in_array($this->status, [
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
            self::STATUS_SYNCED,
        ]);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
            self::STATUS_SYNCED,
        ]);
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function canBeSubmitted(): bool
    {
        return $this->isDraft();
    }

    public function canBeCancelled(): bool
    {
        return $this->isActive() && !$this->isSynced();
    }

    public function canBeApproved(): bool
    {
        return in_array($this->status, [
            self::STATUS_IN_REVIEW,
            self::STATUS_ANALYST_REVIEW,
            self::STATUS_SUPERVISOR_REVIEW,
        ]);
    }

    public function canBeRejected(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_IN_REVIEW,
            self::STATUS_ANALYST_REVIEW,
            self::STATUS_SUPERVISOR_REVIEW,
        ]);
    }

    // =====================================================
    // Actions
    // =====================================================

    /**
     * Submit the application.
     */
    public function submit(string $accountId, ?string $ip = null, ?string $device = null): void
    {
        $this->update([
            'status' => self::STATUS_SUBMITTED,
            'status_changed_at' => now(),
            'status_changed_by' => $accountId,
            'status_changed_by_type' => ApplicantAccount::class,
            'submitted_at' => now(),
            'submission_ip' => $ip,
            'submission_device' => $device,
        ]);

        $this->recordStatusChange(self::STATUS_DRAFT, self::STATUS_SUBMITTED, $accountId, ApplicantAccount::class);
    }

    /**
     * Change status with transition validation.
     *
     * @throws \InvalidArgumentException if transition is not allowed
     */
    public function changeStatus(string $newStatus, ?string $changedById, string $changedByType, ?string $notes = null): void
    {
        // Validate transition is allowed
        $this->validateTransition($newStatus);

        $oldStatus = $this->status;

        $this->update([
            'status' => $newStatus,
            'status_changed_at' => now(),
            'status_changed_by' => $changedById,
            'status_changed_by_type' => $changedByType,
        ]);

        $this->recordStatusChange($oldStatus, $newStatus, $changedById, $changedByType, $notes);
    }

    /**
     * Assign to staff member.
     */
    public function assignTo(string $staffId, string $assignedById): void
    {
        $previousAssignee = $this->assigned_to;

        $this->update([
            'assigned_to' => $staffId,
            'assigned_at' => now(),
            'assigned_by' => $assignedById,
        ]);

        // Get assignee name for history
        $assignee = StaffAccount::find($staffId);
        $assigneeName = $assignee?->name ?? 'Usuario desconocido';

        \App\Services\ActivityRecorder::recordApplicationEvent($this, 'APPLICATION_UPDATED', [
            'from_status' => 'ASSIGNMENT',
            'to_status' => 'ASSIGNMENT',
            'notes' => "Solicitud asignada a {$assigneeName}",
            'old_values' => ['assigned_to' => $previousAssignee],
            'new_values' => ['assigned_to' => $staffId],
            'metadata' => [
                'kind' => 'assigned',
                'assignee_id' => $staffId,
                'assignee_name' => $assigneeName,
            ],
        ]);
    }

    /**
     * Approve the application.
     */
    public function approve(
        string $staffId,
        ?float $amount = null,
        ?int $termMonths = null,
        ?float $interestRate = null,
        ?string $notes = null
    ): void {
        $this->update([
            'decision' => self::DECISION_APPROVED,
            'decision_at' => now(),
            'decision_by' => $staffId,
            'decision_notes' => $notes,
            'approved_amount' => $amount ?? $this->requested_amount,
            'approved_term_months' => $termMonths ?? $this->requested_term_months,
            'approved_interest_rate' => $interestRate ?? $this->interest_rate,
            'status' => self::STATUS_APPROVED,
            'status_changed_at' => now(),
            'status_changed_by' => $staffId,
            'status_changed_by_type' => StaffAccount::class,
        ]);

        $this->recordStatusChange($this->status, self::STATUS_APPROVED, $staffId, StaffAccount::class, $notes);
    }

    /**
     * Reject the application.
     *
     * $staffId null = rechazo automático del motor de decisión (actor 'system').
     */
    public function reject(?string $staffId, string $reason, ?string $notes = null): void
    {
        $oldStatus = $this->status;
        $actorType = $staffId ? StaffAccount::class : 'system';

        $this->update([
            'decision' => self::DECISION_REJECTED,
            'decision_at' => now(),
            'decision_by' => $staffId,
            'decision_notes' => $notes,
            'rejection_reason' => $reason,
            'status' => self::STATUS_REJECTED,
            'status_changed_at' => now(),
            'status_changed_by' => $staffId,
            'status_changed_by_type' => $actorType,
        ]);

        $this->recordStatusChange($oldStatus, self::STATUS_REJECTED, $staffId, $actorType, $reason);
    }

    /**
     * Send counter offer.
     *
     * Transiciona a COUNTER_OFFERED y guarda el snapshot completo de la oferta.
     * Reenviar estando ya en COUNTER_OFFERED sobreescribe el snapshot (y renueva
     * su vigencia) sin registrar una transición duplicada en el historial.
     *
     * $staffId null = oferta generada por el motor de decisión (actor 'system';
     * decision_by queda null por su FK a staff_accounts). El modo rango viaja en
     * el snapshot ($offer con min/max_amount) — el modelo no lo interpreta.
     */
    public function sendCounterOffer(?string $staffId, array $offer, ?string $reason = null): void
    {
        $oldStatus = $this->status;
        $isResend = $oldStatus === self::STATUS_COUNTER_OFFERED;

        if (!$isResend) {
            $this->validateTransition(self::STATUS_COUNTER_OFFERED);
        }

        $actorType = $staffId ? StaffAccount::class : 'system';

        $this->update([
            'decision' => self::DECISION_COUNTER_OFFER,
            'decision_at' => now(),
            'decision_by' => $staffId,
            'counter_offer' => array_merge($offer, [
                'reason' => $reason,
                'offered_by' => $staffId ?? 'system',
                'offered_at' => now()->toIso8601String(),
                'responded_at' => null,
                'accepted' => null,
                'acceptance' => null,
            ]),
            'counter_offer_accepted' => null,
            'counter_offer_responded_at' => null,
            'status' => self::STATUS_COUNTER_OFFERED,
            'status_changed_at' => now(),
            'status_changed_by' => $staffId,
            'status_changed_by_type' => $actorType,
        ]);

        if (!$isResend) {
            $this->recordStatusChange($oldStatus, self::STATUS_COUNTER_OFFERED, $staffId, $actorType, $reason);
        }

        $formattedAmount = number_format($offer['amount'], 2);
        $amountLabel = isset($offer['max_amount'])
            ? '$' . number_format($offer['min_amount'] ?? 0, 2) . " - \${$formattedAmount}"
            : "\${$formattedAmount}";
        $termLabel = isset($offer['term_days'])
            ? "{$offer['term_days']} días"
            : "{$offer['term_months']} meses";
        $prefix = $staffId
            ? ($isResend ? 'Contraoferta reenviada' : 'Contraoferta enviada')
            : 'Oferta generada por el motor de decisión';
        \App\Services\ActivityRecorder::recordApplicationEvent($this, 'APPLICATION_UPDATED', [
            'from_status' => 'COUNTER_OFFER',
            'to_status' => 'COUNTER_OFFER',
            'notes' => "{$prefix}: {$amountLabel} a {$termLabel}" . ($reason ? " - {$reason}" : ''),
            'new_values' => ['counter_offer' => $offer],
            'metadata' => [
                'kind' => 'counter_offer_sent',
                'source' => $offer['source'] ?? ($staffId ? 'STAFF' : 'ENGINE'),
                'amount' => $offer['amount'],
                'min_amount' => $offer['min_amount'] ?? null,
                'max_amount' => $offer['max_amount'] ?? null,
                'term_months' => $offer['term_months'] ?? null,
                'term_days' => $offer['term_days'] ?? null,
                'max_term_days' => $offer['max_term_days'] ?? null,
                'interest_rate' => $offer['interest_rate'] ?? null,
                'monthly_payment' => $offer['monthly_payment'] ?? null,
                'expires_at' => $offer['expires_at'] ?? null,
                'reason' => $reason,
            ],
        ]);
    }

    /**
     * Respond to counter offer.
     *
     * Aceptar transiciona a APPROVED copiando los términos aceptados a los
     * campos approved_*; rechazar transiciona a CANCELLED (decisión del cliente).
     *
     * En ofertas de rango, $acceptance trae el monto/plazo elegidos (ya
     * validados contra el rango por el service) con su pricing recalculado y la
     * evidencia de aceptación (ip, user_agent). En ofertas fijas llega vacío y
     * se usan los términos del snapshot.
     */
    public function respondToCounterOffer(bool $accepted, string $accountId, array $acceptance = []): void
    {
        $oldStatus = $this->status;
        $newStatus = $accepted ? self::STATUS_APPROVED : self::STATUS_CANCELLED;
        $this->validateTransition($newStatus);

        $respondedAt = now();
        $snapshot = array_merge($this->counter_offer ?? [], [
            'responded_at' => $respondedAt->toIso8601String(),
            'accepted' => $accepted,
        ]);

        if ($accepted) {
            $snapshot['acceptance'] = [
                'chosen_amount' => $acceptance['amount'] ?? ($this->counter_offer['amount'] ?? null),
                'chosen_term_days' => $acceptance['term_days'] ?? ($this->counter_offer['term_days'] ?? null),
                'chosen_term_months' => $acceptance['term_months'] ?? ($this->counter_offer['term_months'] ?? null),
                'ip' => $acceptance['ip'] ?? null,
                'user_agent' => $acceptance['user_agent'] ?? null,
                'accepted_at' => $respondedAt->toIso8601String(),
            ];
        }

        $attributes = [
            'counter_offer' => $snapshot,
            'counter_offer_accepted' => $accepted,
            'counter_offer_responded_at' => $respondedAt,
            'status' => $newStatus,
            'status_changed_at' => $respondedAt,
            'status_changed_by' => $accountId,
            'status_changed_by_type' => ApplicantAccount::class,
        ];

        if ($accepted) {
            $attributes += [
                'approved_amount' => $acceptance['amount'] ?? ($this->counter_offer['amount'] ?? $this->requested_amount),
                'approved_term_months' => $acceptance['term_months'] ?? ($this->counter_offer['term_months'] ?? null),
                'approved_term_days' => $acceptance['term_days'] ?? ($this->counter_offer['term_days'] ?? null),
                'approved_interest_rate' => $this->counter_offer['interest_rate'] ?? $this->interest_rate,
                'approved_monthly_payment' => $acceptance['monthly_payment'] ?? ($this->counter_offer['monthly_payment'] ?? null),
            ];
        }

        $this->update($attributes);

        $notes = $accepted
            ? 'El cliente aceptó la contraoferta'
            : 'El cliente rechazó la contraoferta';
        $this->recordStatusChange($oldStatus, $newStatus, $accountId, ApplicantAccount::class, $notes);
    }

    /**
     * Cancel the application.
     */
    public function cancel(?string $cancelledById, string $cancelledByType, ?string $reason = null): void
    {
        $oldStatus = $this->status;

        $this->update([
            'status' => self::STATUS_CANCELLED,
            'status_changed_at' => now(),
            'status_changed_by' => $cancelledById,
            'status_changed_by_type' => $cancelledByType,
        ]);

        $this->recordStatusChange($oldStatus, self::STATUS_CANCELLED, $cancelledById, $cancelledByType, $reason);
    }

    /**
     * Mark as synced to external system.
     */
    public function markSynced(string $externalId, string $system, ?array $syncData = null): void
    {
        $oldStatus = $this->status;

        $this->update([
            'status' => self::STATUS_SYNCED,
            'status_changed_at' => now(),
            'synced_at' => now(),
            'external_id' => $externalId,
            'external_system' => $system,
            'sync_data' => $syncData,
        ]);

        $this->recordStatusChange($oldStatus, self::STATUS_SYNCED, null, 'system');
    }

    /**
     * Record status change. Delega a ActivityRecorder para que el cambio
     * quede registrado en ambas tablas (application_status_history Y
     * audit_logs), con el actor explicito que viene del caller (los metodos
     * de lifecycle como submit/approve/reject reciben el staff_id o el
     * applicant_id como parametro, no del request HTTP).
     *
     * Mapeo from_status/to_status -> AuditAction:
     *   STATUS_SUBMITTED  -> APPLICATION_SUBMITTED
     *   STATUS_APPROVED   -> APPLICATION_APPROVED
     *   STATUS_REJECTED   -> APPLICATION_REJECTED
     *   otros             -> APPLICATION_UPDATED (status change generico)
     */
    protected function recordStatusChange(
        ?string $fromStatus,
        string $toStatus,
        ?string $changedBy,
        ?string $changedByType,
        ?string $notes = null
    ): void {
        $action = match ($toStatus) {
            self::STATUS_SUBMITTED => 'APPLICATION_SUBMITTED',
            self::STATUS_APPROVED  => 'APPLICATION_APPROVED',
            self::STATUS_REJECTED  => 'APPLICATION_REJECTED',
            default                => 'APPLICATION_UPDATED',
        };

        \App\Services\ActivityRecorder::recordApplicationEvent($this, $action, [
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'notes'       => $notes,
            'old_values'  => ['status' => $fromStatus],
            'new_values'  => ['status' => $toStatus],
            'actor_id'    => $changedBy,
            'actor_type'  => $changedByType,
        ]);
    }

    /**
     * Update verification checklist.
     */
    public function updateVerification(array $checks): void
    {
        $current = $this->verification_checklist ?? [];
        $this->update([
            'verification_checklist' => array_merge($current, $checks),
        ]);
    }

    /**
     * Set risk assessment.
     */
    public function setRiskAssessment(string $level, ?array $data = null): void
    {
        $this->update([
            'risk_level' => $level,
            'risk_data' => $data,
        ]);
    }

    // =====================================================
    // Scopes
    // =====================================================

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeInReview($query)
    {
        return $query->whereIn('status', [
            self::STATUS_IN_REVIEW,
            self::STATUS_ANALYST_REVIEW,
            self::STATUS_SUPERVISOR_REVIEW,
        ]);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
            self::STATUS_SYNCED,
        ]);
    }

    public function scopeForPerson($query, string $personId)
    {
        return $query->where('person_id', $personId);
    }

    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeAssignedToStaff($query, string $staffId)
    {
        return $query->where('assigned_to', $staffId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeIndividuals($query)
    {
        return $query->where('applicant_type', self::TYPE_INDIVIDUAL);
    }

    public function scopeCompanies($query)
    {
        return $query->where('applicant_type', self::TYPE_COMPANY);
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('status', self::STATUS_DRAFT)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->where('expiration_notified', false);
    }

    public function scopeRiskLevel($query, string $level)
    {
        return $query->where('risk_level', $level);
    }
}
