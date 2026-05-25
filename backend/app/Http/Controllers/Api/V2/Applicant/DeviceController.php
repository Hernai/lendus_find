<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\ApplicantAccount;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Registra/elimina tokens de dispositivo para push notifications.
 *
 * - POST /api/v2/applicant/devices    — upsert (idempotente por token)
 * - DELETE /api/v2/applicant/devices/{token} — revoca el token
 */
class DeviceController extends Controller
{
    use ApiResponses;

    public function register(Request $request): JsonResponse
    {
        /** @var ApplicantAccount $user */
        $user = $request->user();

        $validated = Validator::make($request->all(), [
            'token' => 'required|string|max:4096',
            'provider' => 'required|in:fcm,apns,webpush',
            'platform' => 'required|in:ios,android,web',
            'app_version' => 'nullable|string|max:32',
            'device_id' => 'nullable|string|max:64',
        ])->validate();

        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'tenant_id' => $user->tenant_id,
                'owner_type' => ApplicantAccount::class,
                'owner_id' => $user->id,
                'provider' => $validated['provider'],
                'platform' => $validated['platform'],
                'app_version' => $validated['app_version'] ?? null,
                'device_id' => $validated['device_id'] ?? null,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        // Revocar otros tokens del mismo device_id que apunten a otro token actual
        // (renovación de token APNs/FCM).
        if (! empty($validated['device_id'])) {
            DeviceToken::query()
                ->where('owner_type', ApplicantAccount::class)
                ->where('owner_id', $user->id)
                ->where('device_id', $validated['device_id'])
                ->where('id', '!=', $deviceToken->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        return $this->success([
            'id' => $deviceToken->id,
            'provider' => $deviceToken->provider,
            'platform' => $deviceToken->platform,
        ], 'Dispositivo registrado', 201);
    }

    public function unregister(Request $request, string $token): JsonResponse
    {
        /** @var ApplicantAccount $user */
        $user = $request->user();

        DeviceToken::query()
            ->where('token', $token)
            ->where('owner_type', ApplicantAccount::class)
            ->where('owner_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return $this->success(null, 'Dispositivo dado de baja');
    }
}
