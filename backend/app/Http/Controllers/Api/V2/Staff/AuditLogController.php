<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicantAccount;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints para que el staff consulte el log de auditoría (acciones,
 * cambios de entidades y peticiones HTTP) de un applicant o aplicación.
 *
 * - GET /v2/staff/applicants/{id}/audit-logs
 * - GET /v2/staff/applications/{id}/audit-logs
 */
class AuditLogController extends Controller
{
    use ApiResponses;

    public function listByApplicant(Request $request, string $applicantId): JsonResponse
    {
        $applicant = ApplicantAccount::find($applicantId);
        if (! $applicant) {
            return $this->error('NOT_FOUND', 'Applicant no encontrado', 404);
        }

        $query = AuditLog::query()
            ->where('applicant_id', $applicant->id)
            ->orderByDesc('created_at');

        $this->applyFilters($query, $request);

        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min($perPage, 100));
        $logs = $query->paginate($perPage);

        return $this->paginated($logs);
    }

    public function listByApplication(Request $request, string $applicationId): JsonResponse
    {
        $application = Application::find($applicationId);
        if (! $application) {
            return $this->error('NOT_FOUND', 'Aplicación no encontrada', 404);
        }

        // Incluir tanto los logs de la application específica como los del
        // applicant_account dueño (logins, OTPs, profile updates, etc. no
        // tienen application_id pero son relevantes para el expediente).
        $applicantIds = ApplicantAccount::query()
            ->where('person_id', $application->person_id)
            ->pluck('id');

        $query = AuditLog::query()
            ->where(function ($q) use ($application, $applicantIds) {
                $q->where('application_id', $application->id);
                if ($applicantIds->isNotEmpty()) {
                    $q->orWhereIn('applicant_id', $applicantIds);
                }
            })
            ->orderByDesc('created_at');

        $this->applyFilters($query, $request);

        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(1, min($perPage, 100));
        $logs = $query->paginate($perPage);

        return $this->paginated($logs);
    }

    /**
     * Filtros opcionales: action, entity_type, from, to, exclude_http.
     */
    private function applyFilters($query, Request $request): void
    {
        if ($action = $request->input('action')) {
            $actions = is_array($action) ? $action : explode(',', (string) $action);
            $query->whereIn('action', $actions);
        }
        if ($entityType = $request->input('entity_type')) {
            $query->where('entity_type', $entityType);
        }
        if ($from = $request->input('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->where('created_at', '<=', $to);
        }
        // Por defecto se incluyen HTTP_REQUEST, pero si exclude_http=1 los omitimos
        // para ver solo acciones de negocio (logins, cambios, etc.).
        if ($request->boolean('exclude_http')) {
            $query->where('action', '!=', \App\Enums\AuditAction::HTTP_REQUEST->value);
        }
    }

    private function paginated(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator): JsonResponse
    {
        return $this->success([
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
