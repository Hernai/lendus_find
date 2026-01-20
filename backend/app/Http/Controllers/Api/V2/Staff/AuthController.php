<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Enums\AuditAction;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\StaffAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Staff Authentication Controller (v2).
 *
 * Uses the new StaffAccount model separated from applicant authentication.
 * Supports email/password login for staff users.
 */
class AuthController extends Controller
{
    use ApiResponses;
    /**
     * Login with email + password.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $account = StaffAccount::where('email', $request->email)
            ->where('tenant_id', app('tenant.id'))
            ->first();

        if (!$account) {
            return $this->error('INVALID_CREDENTIALS', 'Correo o contraseña incorrectos', 401);
        }

        // Check if account is active
        if (!$account->is_active) {
            return $this->forbidden('Tu cuenta ha sido desactivada. Contacta al administrador.');
        }

        $metadata = $request->attributes->get('metadata', []);

        if (!Hash::check($request->password, $account->password)) {
            // Log failed login (user_id = null since StaffAccount is not in users table)
            AuditLog::log(
                AuditAction::LOGIN_FAILED->value,
                app('tenant.id'),
                array_merge($metadata, [
                    'user_id' => null, // Explicitly null - StaffAccount is separate from users
                    'metadata' => [
                        'staff_account_id' => $account->id,
                        'method' => 'PASSWORD',
                        'reason' => 'invalid_password',
                        'is_staff' => true,
                        'auth_version' => 'v2',
                    ],
                ])
            );

            return $this->error('INVALID_CREDENTIALS', 'Correo o contraseña incorrectos', 401);
        }

        // Record login
        $account->recordLogin();

        // Create token with staff abilities
        $token = $account->createToken('staff-token', ['staff'])->plainTextToken;

        // Log successful login (user_id = null since StaffAccount is not in users table)
        AuditLog::log(
            AuditAction::LOGIN_SUCCESS->value,
            app('tenant.id'),
            array_merge($metadata, [
                'user_id' => null, // Explicitly null - StaffAccount is separate from users
                'metadata' => [
                    'staff_account_id' => $account->id,
                    'method' => 'PASSWORD',
                    'is_staff' => true,
                    'role' => $account->role,
                    'auth_version' => 'v2',
                ],
            ])
        );

        return $this->success([
            'token' => $token,
            'user' => $this->formatStaffResponse($account),
        ]);
    }

    /**
     * Get current staff info.
     */
    public function me(Request $request): JsonResponse
    {
        $account = $request->user();

        // Ensure this is a StaffAccount
        if (!$account instanceof StaffAccount) {
            return $this->unauthorized('Token no válido para esta ruta');
        }

        $account->load('profile');

        return $this->success([
            'user' => $this->formatStaffResponse($account),
        ]);
    }

    /**
     * Logout.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var StaffAccount $account */
        $account = $request->user();

        // Delete token first
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $token */
        $token = $account->currentAccessToken();
        $token?->delete();

        // Log logout without using AuditLog for now since it has FK to users table
        // TODO: Add staff_account_id column to audit_logs or create separate staff_audit_logs table
        Log::info('Staff logout', [
            'tenant_id' => app('tenant.id'),
            'staff_account_id' => $account->id,
            'auth_version' => 'v2',
        ]);

        return $this->success(null, 'Sesión cerrada');
    }

    /**
     * Refresh token (extend session).
     */
    public function refresh(Request $request): JsonResponse
    {
        $account = $request->user();

        if (!$account instanceof StaffAccount) {
            return $this->unauthorized('Token no válido para esta ruta');
        }

        // Delete current token
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $currentToken */
        $currentToken = $account->currentAccessToken();
        $currentToken?->delete();

        // Create new token
        $newToken = $account->createToken('staff-token', ['staff'])->plainTextToken;

        return $this->success(['token' => $newToken]);
    }

    /**
     * Format staff account response.
     */
    protected function formatStaffResponse(StaffAccount $account): array
    {
        $account->loadMissing('profile');

        return [
            'id' => $account->id,
            'email' => $account->email,
            'role' => $account->role,
            'is_staff' => true,
            'is_active' => $account->is_active,
            'profile' => $account->profile ? [
                'first_name' => $account->profile->first_name,
                'last_name' => $account->profile->last_name,
                'last_name_2' => $account->profile->last_name_2,
                'full_name' => $account->profile->full_name,
                'initials' => $account->profile->initials,
                'phone' => $account->profile->phone,
                'avatar_url' => $account->profile->avatar_url,
                'title' => $account->profile->title,
            ] : null,
            'last_login_at' => $account->last_login_at?->toIso8601String(),
            'permissions' => $account->getPermissionsArray(),
        ];
    }
}
