<?php

use App\Models\Application;
use App\Models\ApplicantAccount;
use App\Models\StaffAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/**
 * Helpers para distinguir StaffAccount vs ApplicantAccount.
 *
 * Sanctum entrega el modelo asociado al token activo, así que estos
 * canales se invocan con uno u otro tipo y debemos manejar ambos.
 */
function broadcast_is_staff(Model $user): bool
{
    return $user instanceof StaffAccount;
}

function broadcast_is_applicant(Model $user): bool
{
    return $user instanceof ApplicantAccount;
}

function broadcast_can_view_all_applications(Model $user): bool
{
    return $user instanceof StaffAccount && $user->canViewAllApplications();
}

// Canal de aplicación específica (staff viendo la aplicación)
Broadcast::channel('tenant.{tenantId}.application.{applicationId}', function (Model $user, string $tenantId, string $applicationId) {
    Log::info('Channel auth: application', [
        'user_id' => $user->id,
        'user_tenant_id' => (string) $user->tenant_id,
        'channel_tenant_id' => $tenantId,
        'application_id' => $applicationId,
        'is_staff' => broadcast_is_staff($user),
    ]);

    if ((string) $user->tenant_id !== $tenantId) {
        Log::info('Channel auth failed: tenant mismatch');
        return false;
    }

    if (broadcast_is_staff($user)) {
        /** @var StaffAccount $user */
        if ($user->isSupervisor() && ! $user->canViewAllApplications()) {
            $application = Application::find($applicationId);
            return $application && (string) $application->assigned_to === (string) $user->id;
        }
        return true;
    }

    if (broadcast_is_applicant($user)) {
        /** @var ApplicantAccount $user */
        $application = Application::find($applicationId);
        // El aplicante puede ver la aplicación si su persona coincide con la del expediente.
        return $application && (string) $application->person_id === (string) $user->person_id;
    }

    return false;
});

// Canal personal del aplicante (para su dashboard)
Broadcast::channel('tenant.{tenantId}.applicant.{applicantId}', function (Model $user, string $tenantId, string $applicantId) {
    if ((string) $user->tenant_id !== $tenantId) {
        return false;
    }
    // Solo el aplicante puede suscribirse a su propio canal.
    return broadcast_is_applicant($user) && (string) $user->id === $applicantId;
});

// Canal admin del tenant
Broadcast::channel('tenant.{tenantId}.admin', function (Model $user, string $tenantId) {
    Log::info('Channel auth: admin', [
        'user_id' => $user->id,
        'user_tenant_id' => (string) $user->tenant_id,
        'channel_tenant_id' => $tenantId,
        'is_staff' => broadcast_is_staff($user),
    ]);

    if ((string) $user->tenant_id !== $tenantId) {
        return false;
    }

    return broadcast_can_view_all_applications($user);
});

// Canal de usuario específico (notificaciones personales como asignaciones)
Broadcast::channel('tenant.{tenantId}.user.{userId}', function (Model $user, string $tenantId, string $userId) {
    return (string) $user->tenant_id === $tenantId && (string) $user->id === $userId;
});
