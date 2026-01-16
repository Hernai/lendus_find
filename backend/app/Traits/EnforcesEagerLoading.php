<?php

namespace App\Traits;

use App\Models\Applicant;
use App\Models\Application;

/**
 * Trait for enforcing eager loading in controllers.
 *
 * Provides standardized eager loading methods to prevent N+1 queries.
 */
trait EnforcesEagerLoading
{
    /**
     * Standard relations to load with Applicant.
     */
    protected function applicantRelations(): array
    {
        return [
            'addresses',
            'employmentRecords',
            'bankAccounts',
            'user',
            'documents',
            'references',
        ];
    }

    /**
     * Standard relations to load with Application.
     */
    protected function applicationRelations(): array
    {
        return [
            'applicant',
            'applicant.user',
            'product',
            'documents',
            'references',
            'notes',
            'assignedTo',
        ];
    }

    /**
     * Get an applicant with all standard relations loaded.
     */
    protected function getApplicantWithRelations(string $id): ?Applicant
    {
        return Applicant::with($this->applicantRelations())->find($id);
    }

    /**
     * Get an application with all standard relations loaded.
     */
    protected function getApplicationWithRelations(string $id): ?Application
    {
        return Application::with($this->applicationRelations())->find($id);
    }

    /**
     * Get applicant by user ID with relations.
     */
    protected function getApplicantByUserWithRelations(string $userId): ?Applicant
    {
        return Applicant::with($this->applicantRelations())
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Get applications for an applicant with relations.
     */
    protected function getApplicationsForApplicant(string $applicantId): \Illuminate\Database\Eloquent\Collection
    {
        return Application::with($this->applicationRelations())
            ->where('applicant_id', $applicantId)
            ->orderByDesc('created_at')
            ->get();
    }
}
