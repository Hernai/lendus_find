<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing application status transitions.
 *
 * Encapsulates business logic for:
 * - Status transition validation
 * - Permission checking
 * - Status change execution with audit logging
 *
 * @deprecated Use ApplicationV2Service instead for new functionality.
 *             This service remains for legacy Application model.
 *             ApplicationV2Service works with the new ApplicationV2 model.
 * @see \App\Services\ApplicationV2Service
 */
class ApplicationStatusService
{
    /**
     * Status transition rules matrix.
     * Key: current status, Value: array of allowed next statuses.
     */
    private const TRANSITIONS = [
        'DRAFT' => ['SUBMITTED', 'CANCELLED'],
        'SUBMITTED' => ['IN_REVIEW', 'DOCS_PENDING', 'CANCELLED'],
        'IN_REVIEW' => ['DOCS_PENDING', 'CORRECTIONS_PENDING', 'COUNTER_OFFERED', 'APPROVED', 'REJECTED', 'CANCELLED'],
        'DOCS_PENDING' => ['IN_REVIEW', 'CORRECTIONS_PENDING', 'CANCELLED'],
        'CORRECTIONS_PENDING' => ['IN_REVIEW', 'CANCELLED'],
        'COUNTER_OFFERED' => ['IN_REVIEW', 'APPROVED', 'REJECTED', 'CANCELLED'],
        'APPROVED' => ['DISBURSED', 'CANCELLED'],
        'REJECTED' => [], // Terminal state
        'CANCELLED' => [], // Terminal state
        'DISBURSED' => ['ACTIVE'],
        'ACTIVE' => ['COMPLETED', 'DEFAULT'],
        'COMPLETED' => [], // Terminal state
        'DEFAULT' => ['ACTIVE'], // Can return to active if resolved
        'SYNCED' => [], // Terminal state (legacy)
    ];

    /**
     * Statuses that require approval permission.
     */
    private const RESTRICTED_STATUSES = [
        'APPROVED',
        'REJECTED',
        'CANCELLED',
        'DISBURSED',
        'ACTIVE',
        'COMPLETED',
        'DEFAULT',
    ];

    /**
     * Check if a status transition is allowed.
     */
    public function canTransition(Application $application, string $newStatus): bool
    {
        $currentStatus = $application->status->value;
        $allowedTransitions = self::TRANSITIONS[$currentStatus] ?? [];

        return in_array($newStatus, $allowedTransitions, true);
    }

    /**
     * Check if user has permission to change to the given status.
     */
    public function userCanChangeToStatus(User $user, string $newStatus): bool
    {
        // All staff can change to non-restricted statuses
        if (!in_array($newStatus, self::RESTRICTED_STATUSES, true)) {
            return $user->canChangeApplicationStatus();
        }

        // Restricted statuses require approval permission
        return $user->canApproveRejectApplications();
    }

    /**
     * Get validation error for status transition, or null if valid.
     */
    public function validateTransition(Application $application, string $newStatus): ?string
    {
        $currentStatus = $application->status->value;

        // Check basic transition rules
        if (!$this->canTransition($application, $newStatus)) {
            return "No se puede cambiar el estado de '{$currentStatus}' a '{$newStatus}'.";
        }

        // Additional business rules
        if ($newStatus === 'DISBURSED' && $currentStatus !== 'APPROVED') {
            return 'Solo las solicitudes aprobadas pueden ser desembolsadas.';
        }

        if ($newStatus === 'ACTIVE' && $currentStatus !== 'DISBURSED') {
            return 'Solo las solicitudes desembolsadas pueden marcarse como activas.';
        }

        if (in_array($newStatus, ['COMPLETED', 'DEFAULT']) && $currentStatus !== 'ACTIVE') {
            return 'Solo las solicitudes activas pueden marcarse como completadas o en mora.';
        }

        return null;
    }

    /**
     * Change application status with full validation and logging.
     *
     * @throws \InvalidArgumentException if transition is not allowed
     */
    public function changeStatus(
        Application $application,
        string $newStatus,
        User $user,
        ?string $reason = null,
        array $metadata = []
    ): Application {
        // Validate permission
        if (!$this->userCanChangeToStatus($user, $newStatus)) {
            throw new \InvalidArgumentException('No tienes permiso para cambiar a este estado.');
        }

        // Validate transition
        $error = $this->validateTransition($application, $newStatus);
        if ($error) {
            throw new \InvalidArgumentException($error);
        }

        return DB::transaction(function () use ($application, $newStatus, $user, $reason, $metadata) {
            $oldStatus = $application->status->value;

            // Execute the status change
            $application->changeStatus($newStatus, $reason, $user->id);

            // Handle status-specific side effects
            $this->handleStatusSideEffects($application, $newStatus);

            // Log the change
            $this->logStatusChange($application, $oldStatus, $newStatus, $user, $reason, $metadata);

            return $application->fresh();
        });
    }

    /**
     * Handle side effects for specific status changes.
     */
    private function handleStatusSideEffects(Application $application, string $newStatus): void
    {
        switch ($newStatus) {
            case 'APPROVED':
                $application->approved_at = now();
                $application->approved_amount = $application->approved_amount ?? $application->requested_amount;
                $application->save();
                break;

            case 'REJECTED':
                $application->rejected_at = now();
                $application->save();
                break;

            case 'DISBURSED':
                $application->disbursed_at = now();
                $application->save();
                break;
        }
    }

    /**
     * Log status change to audit log.
     */
    private function logStatusChange(
        Application $application,
        string $oldStatus,
        string $newStatus,
        User $user,
        ?string $reason,
        array $metadata
    ): void {
        AuditLog::log(
            AuditAction::APPLICATION_UPDATED->value,
            null,
            array_merge($metadata, [
                'user_id' => $user->id,
                'applicant_id' => $application->applicant_id,
                'application_id' => $application->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ])
        );
    }

    /**
     * Get allowed next statuses for an application given user permissions.
     */
    public function getAllowedNextStatuses(Application $application, User $user): array
    {
        $currentStatus = $application->status->value;
        $allowedTransitions = self::TRANSITIONS[$currentStatus] ?? [];

        // Filter by user permissions
        return array_values(array_filter($allowedTransitions, function ($status) use ($user) {
            return $this->userCanChangeToStatus($user, $status);
        }));
    }

    /**
     * Get all statuses with labels that user can change to (for dropdown).
     */
    public function getStatusOptionsForUser(User $user): array
    {
        $statuses = [];

        foreach (ApplicationStatus::cases() as $status) {
            // Skip initial states that are not directly selectable
            if (in_array($status->value, ['DRAFT', 'SUBMITTED', 'SYNCED'])) {
                continue;
            }

            if ($this->userCanChangeToStatus($user, $status->value)) {
                $statuses[] = [
                    'value' => $status->value,
                    'label' => $status->label(),
                ];
            }
        }

        return $statuses;
    }

    /**
     * Check if application is in a terminal state.
     */
    public function isTerminalState(Application $application): bool
    {
        $terminalStates = ['REJECTED', 'CANCELLED', 'COMPLETED', 'SYNCED'];
        return in_array($application->status->value, $terminalStates, true);
    }

    /**
     * Check if application is in an active processing state.
     */
    public function isActiveState(Application $application): bool
    {
        $activeStates = ['SUBMITTED', 'IN_REVIEW', 'DOCS_PENDING', 'CORRECTIONS_PENDING', 'COUNTER_OFFERED'];
        return in_array($application->status->value, $activeStates, true);
    }

    /**
     * Check if application requires action (stale).
     */
    public function isStale(Application $application, int $hoursThreshold = 8): bool
    {
        if (!$this->isActiveState($application)) {
            return false;
        }

        return $application->updated_at->lt(now()->subHours($hoursThreshold));
    }
}
