<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\StaffAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Registra/elimina tokens de dispositivo para push notifications de staff.
 *
 * - POST /api/v2/staff/devices               — upsert
 * - DELETE /api/v2/staff/devices/{token}     — revoca
 */
class DeviceController extends Controller
{
    use ApiResponses;

    public function register(Request $request): JsonResponse
    {
        /** @var StaffAccount $user */
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
                'owner_type' => StaffAccount::class,
                'owner_id' => $user->id,
                'provider' => $validated['provider'],
                'platform' => $validated['platform'],
                'app_version' => $validated['app_version'] ?? null,
                'device_id' => $validated['device_id'] ?? null,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        if (! empty($validated['device_id'])) {
            DeviceToken::query()
                ->where('owner_type', StaffAccount::class)
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
        /** @var StaffAccount $user */
        $user = $request->user();

        DeviceToken::query()
            ->where('token', $token)
            ->where('owner_type', StaffAccount::class)
            ->where('owner_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return $this->success(null, 'Dispositivo dado de baja');
    }
}
