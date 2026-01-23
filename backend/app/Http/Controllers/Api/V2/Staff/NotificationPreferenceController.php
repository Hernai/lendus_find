<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationPreferenceController extends Controller
{
    /**
     * Get staff's notification preferences.
     */
    public function show(Request $request): JsonResponse
    {
        $staff = $request->user();

        $preference = NotificationPreference::firstOrCreate(
            [
                'tenant_id' => $request->tenant->id,
                'recipient_id' => $staff->id,
                'recipient_type' => 'STAFF',
            ],
            [
                'sms_enabled' => true,
                'whatsapp_enabled' => true,
                'email_enabled' => true,
                'in_app_enabled' => true,
                'disabled_events' => [],
            ]
        );

        return response()->json([
            'preferences' => [
                'sms_enabled' => $preference->sms_enabled,
                'whatsapp_enabled' => $preference->whatsapp_enabled,
                'email_enabled' => $preference->email_enabled,
                'in_app_enabled' => $preference->in_app_enabled,
                'disabled_events' => $preference->disabled_events ?? [],
            ],
        ]);
    }

    /**
     * Update staff's notification preferences.
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sms_enabled' => 'sometimes|boolean',
            'whatsapp_enabled' => 'sometimes|boolean',
            'email_enabled' => 'sometimes|boolean',
            'in_app_enabled' => 'sometimes|boolean',
            'disabled_events' => 'sometimes|array',
            'disabled_events.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $staff = $request->user();

        $preference = NotificationPreference::updateOrCreate(
            [
                'tenant_id' => $request->tenant->id,
                'recipient_id' => $staff->id,
                'recipient_type' => 'STAFF',
            ],
            $validator->validated()
        );

        return response()->json([
            'message' => 'Preferencias actualizadas',
            'preferences' => [
                'sms_enabled' => $preference->sms_enabled,
                'whatsapp_enabled' => $preference->whatsapp_enabled,
                'email_enabled' => $preference->email_enabled,
                'in_app_enabled' => $preference->in_app_enabled,
                'disabled_events' => $preference->disabled_events ?? [],
            ],
        ]);
    }

    /**
     * Disable a specific event type.
     */
    public function disableEvent(Request $request, string $event): JsonResponse
    {
        $validator = Validator::make(['event' => $event], [
            'event' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Evento inválido',
            ], 422);
        }

        $staff = $request->user();

        $preference = NotificationPreference::firstOrCreate(
            [
                'tenant_id' => $request->tenant->id,
                'recipient_id' => $staff->id,
                'recipient_type' => 'STAFF',
            ],
            [
                'sms_enabled' => true,
                'whatsapp_enabled' => true,
                'email_enabled' => true,
                'in_app_enabled' => true,
                'disabled_events' => [],
            ]
        );

        $preference->disableEvent($event);

        return response()->json([
            'message' => 'Evento deshabilitado',
            'preferences' => [
                'disabled_events' => $preference->disabled_events ?? [],
            ],
        ]);
    }

    /**
     * Enable a specific event type.
     */
    public function enableEvent(Request $request, string $event): JsonResponse
    {
        $validator = Validator::make(['event' => $event], [
            'event' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Evento inválido',
            ], 422);
        }

        $staff = $request->user();

        $preference = NotificationPreference::firstOrCreate(
            [
                'tenant_id' => $request->tenant->id,
                'recipient_id' => $staff->id,
                'recipient_type' => 'STAFF',
            ],
            [
                'sms_enabled' => true,
                'whatsapp_enabled' => true,
                'email_enabled' => true,
                'in_app_enabled' => true,
                'disabled_events' => [],
            ]
        );

        $preference->enableEvent($event);

        return response()->json([
            'message' => 'Evento habilitado',
            'preferences' => [
                'disabled_events' => $preference->disabled_events ?? [],
            ],
        ]);
    }
}
