<?php

use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Canal de aplicación específica (staff viendo la aplicación)
Broadcast::channel('tenant.{tenantId}.application.{applicationId}', function (User $user, string $tenantId, string $applicationId) {
    \Log::info('Channel auth: application', [
        'user_id' => $user->id,
        'user_tenant_id' => (string) $user->tenant_id,
        'channel_tenant_id' => $tenantId,
        'application_id' => $applicationId,
        'is_staff' => $user->isStaff(),
    ]);

    // Usuario debe pertenecer al tenant (cast to string for UUID comparison)
    if ((string) $user->tenant_id !== $tenantId) {
        \Log::info('Channel auth failed: tenant mismatch');
        return false;
    }

    // Staff puede ver cualquier aplicación en su tenant
    if ($user->isStaff()) {
        // Supervisores solo ven aplicaciones asignadas (a menos que tengan permiso)
        if ($user->isAgent() && !$user->canViewAllApplications()) {
            $application = Application::find($applicationId);
            return $application && (string) $application->assigned_to === (string) $user->id;
        }
        \Log::info('Channel auth success: staff');
        return true;
    }

    // Aplicantes solo ven sus propias aplicaciones
    if ($user->isApplicant() && $user->applicant) {
        $application = Application::find($applicationId);
        return $application && (string) $application->applicant_id === (string) $user->applicant->id;
    }

    return false;
});

// Canal personal del aplicante (para su dashboard)
Broadcast::channel('tenant.{tenantId}.applicant.{applicantId}', function (User $user, string $tenantId, string $applicantId) {
    if ((string) $user->tenant_id !== $tenantId) {
        return false;
    }

    // Solo el aplicante puede suscribirse a su propio canal
    return $user->isApplicant() && $user->applicant && (string) $user->applicant->id === $applicantId;
});

// Canal admin del tenant (para dashboard admin)
Broadcast::channel('tenant.{tenantId}.admin', function (User $user, string $tenantId) {
    \Log::info('Channel auth: admin', [
        'user_id' => $user->id,
        'user_tenant_id' => (string) $user->tenant_id,
        'channel_tenant_id' => $tenantId,
        'is_staff' => $user->isStaff(),
        'can_view_all' => $user->canViewAllApplications(),
        'user_type' => $user->type?->value ?? 'null',
    ]);

    if ((string) $user->tenant_id !== $tenantId) {
        \Log::info('Channel auth: admin - tenant mismatch');
        return false;
    }

    // Solo staff con permisos puede suscribirse
    $result = $user->isStaff() && $user->canViewAllApplications();
    \Log::info('Channel auth: admin - result', ['result' => $result]);
    return $result;
});

// Canal de usuario específico (notificaciones personales como asignaciones)
Broadcast::channel('tenant.{tenantId}.user.{userId}', function (User $user, string $tenantId, string $userId) {
    return (string) $user->tenant_id === $tenantId && (string) $user->id === $userId;
});
