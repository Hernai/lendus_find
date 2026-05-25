<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponses;

    /**
     * Listar notificaciones in-app paginadas del aplicante.
     */
    public function index(Request $request): JsonResponse
    {
        $applicant = $request->user();

        $notifications = NotificationLog::where('recipient_id', $applicant->id)
            ->where('recipient_type', 'APPLICANT')
            ->forChannel('IN_APP')
            ->whereIn('status', [
                NotificationLog::STATUS_SENT,
                NotificationLog::STATUS_DELIVERED,
                NotificationLog::STATUS_READ,
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->success([
            'notifications' => $notifications->map(fn (NotificationLog $log) => $this->formatNotification($log)),
            'pagination' => [
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * Contador de notificaciones no leídas.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $applicant = $request->user();

        $count = NotificationLog::where('recipient_id', $applicant->id)
            ->where('recipient_type', 'APPLICANT')
            ->forChannel('IN_APP')
            ->whereIn('status', [
                NotificationLog::STATUS_SENT,
                NotificationLog::STATUS_DELIVERED,
            ])
            ->whereNull('read_at')
            ->count();

        return $this->success(['unread_count' => $count]);
    }

    /**
     * Marcar una notificación como leída.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $applicant = $request->user();

        $log = NotificationLog::where('id', $id)
            ->where('recipient_id', $applicant->id)
            ->where('recipient_type', 'APPLICANT')
            ->forChannel('IN_APP')
            ->first();

        if (!$log) {
            return $this->notFound('Notificación no encontrada');
        }

        if (!$log->read_at) {
            $log->markAsRead();
        }

        return $this->success([
            'notification' => $this->formatNotification($log),
        ]);
    }

    /**
     * Marcar todas las notificaciones como leídas.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $applicant = $request->user();

        $updatedCount = NotificationLog::where('recipient_id', $applicant->id)
            ->where('recipient_type', 'APPLICANT')
            ->forChannel('IN_APP')
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'status' => NotificationLog::STATUS_READ,
            ]);

        return $this->success(['updated_count' => $updatedCount]);
    }

    /**
     * Formato estándar de una notificación para la respuesta.
     */
    private function formatNotification(NotificationLog $log): array
    {
        return [
            'id' => $log->id,
            'event' => $log->event instanceof \BackedEnum ? $log->event->value : $log->event,
            'subject' => $log->subject,
            'body' => $log->body,
            'read_at' => $log->read_at?->toISOString(),
            'is_read' => $log->read_at !== null,
            'created_at' => $log->created_at->toISOString(),
            'metadata' => $log->metadata,
        ];
    }
}
