<?php

use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Canal de aplicación específica (staff viendo la aplicación)
Broadcast::channel('tenant.{tenantId}.application.{applicationId}', function (User $user, string $tenantId, string $applicationId) {
    // Usuario debe pertenecer al tenant
    if ($user->tenant_id !== $tenantId) {
        return false;
    }

    // Staff puede ver cualquier aplicación en su tenant
    if ($user->isStaff()) {
        // Agentes solo ven aplicaciones asignadas
        if ($user->isAgent() && !$user->canViewAllApplications()) {
            $application = Application::find($applicationId);
            return $application && $application->assigned_to === $user->id;
        }
        return true;
    }

    // Aplicantes solo ven sus propias aplicaciones
    if ($user->isApplicant() && $user->applicant) {
        $application = Application::find($applicationId);
        return $application && $application->applicant_id === $user->applicant->id;
    }

    return false;
});

// Canal personal del aplicante (para su dashboard)
Broadcast::channel('tenant.{tenantId}.applicant.{applicantId}', function (User $user, string $tenantId, string $applicantId) {
    if ($user->tenant_id !== $tenantId) {
        return false;
    }

    // Solo el aplicante puede suscribirse a su propio canal
    return $user->isApplicant() && $user->applicant && $user->applicant->id === $applicantId;
});

// Canal admin del tenant (para dashboard admin)
Broadcast::channel('tenant.{tenantId}.admin', function (User $user, string $tenantId) {
    if ($user->tenant_id !== $tenantId) {
        return false;
    }

    // Solo staff con permisos puede suscribirse
    return $user->isStaff() && $user->canViewAllApplications();
});

// Canal de usuario específico (notificaciones personales como asignaciones)
Broadcast::channel('tenant.{tenantId}.user.{userId}', function (User $user, string $tenantId, string $userId) {
    return $user->tenant_id === $tenantId && $user->id === $userId;
});
