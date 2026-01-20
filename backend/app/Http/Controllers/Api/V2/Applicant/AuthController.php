<?php

namespace App\Http\Controllers\Api\V2\Applicant;

use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\ApplicantAccount;
use App\Services\ApplicantAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Applicant Authentication Controller (v2).
 *
 * Uses the new ApplicantAccount model separated from User model.
 * Supports multi-identity authentication (phone, email, WhatsApp) and PIN login.
 */
class AuthController extends Controller
{
    use ApiResponses;
    public function __construct(
        private ApplicantAuthService $authService
    ) {}

    /**
     * Request OTP code.
     *
     * POST /v2/applicant/auth/otp/request
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:phone,email,whatsapp,PHONE,EMAIL,WHATSAPP',
            'identifier' => 'required|string',
            'channel' => 'nullable|string|in:sms,email,whatsapp,SMS,EMAIL,WHATSAPP',
        ]);

        $type = strtoupper($validated['type']);
        $channel = strtoupper($validated['channel'] ?? ($type === 'EMAIL' ? 'EMAIL' : 'SMS'));
        $tenantId = app('tenant.id');

        $result = $this->authService->requestOtp(
            $tenantId,
            $type,
            $validated['identifier'],
            $channel
        );

        if (!$result['success']) {
            $statusCode = match ($result['error'] ?? '') {
                'RATE_LIMIT_EXCEEDED' => 429,
                'INVALID_TYPE' => 400,
                default => 400,
            };

            return $this->error($result['error'] ?? 'OTP_REQUEST_FAILED', $result['message'], $statusCode);
        }

        return $this->success($result['data'] ?? [], $result['message']);
    }

    /**
     * Verify OTP and login/register.
     *
     * POST /v2/applicant/auth/otp/verify
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:phone,email,whatsapp,PHONE,EMAIL,WHATSAPP',
            'identifier' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $tenantId = app('tenant.id');

        $result = $this->authService->verifyOtp(
            $tenantId,
            strtoupper($validated['type']),
            $validated['identifier'],
            $validated['code']
        );

        if (!$result['success']) {
            $statusCode = match ($result['error'] ?? '') {
                'OTP_NOT_FOUND' => 400,
                'INVALID_CODE' => 400,
                'TOO_MANY_ATTEMPTS' => 429,
                default => 400,
            };

            return $this->error($result['error'] ?? 'VERIFICATION_FAILED', $result['message'], $statusCode);
        }

        return $this->success([
            'token' => $result['data']['token'],
            'is_new_user' => $result['data']['is_new_user'] ?? false,
            'user' => $result['data']['user'],
        ], $result['message']);
    }

    /**
     * Login with PIN.
     *
     * POST /v2/applicant/auth/pin/login
     */
    public function loginWithPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:phone,email,PHONE,EMAIL',
            'identifier' => 'required|string',
            'pin' => 'required|string|digits:4',
        ]);

        $tenantId = app('tenant.id');

        $result = $this->authService->loginWithPin(
            $tenantId,
            strtoupper($validated['type']),
            $validated['identifier'],
            $validated['pin']
        );

        if (!$result['success']) {
            $statusCode = match ($result['error'] ?? '') {
                'ACCOUNT_NOT_FOUND' => 404,
                'ACCOUNT_DISABLED' => 403,
                'ACCOUNT_LOCKED' => 423,
                'PIN_NOT_SET' => 400,
                'INVALID_PIN' => 401,
                default => 400,
            };

            return $this->error($result['error'] ?? 'LOGIN_FAILED', $result['message'], $statusCode);
        }

        return $this->success([
            'token' => $result['data']['token'],
            'user' => $result['data']['user'],
        ], $result['message']);
    }

    /**
     * Setup PIN (requires authentication).
     *
     * POST /v2/applicant/auth/pin/setup
     */
    public function setupPin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => 'required|string|digits:4',
            'pin_confirmation' => 'required|string|same:pin',
        ]);

        /** @var ApplicantAccount $account */
        $account = $request->user();

        if (!$account instanceof ApplicantAccount) {
            return $this->unauthorized('Token no válido para esta ruta');
        }

        if ($account->hasPin()) {
            return $this->badRequest('PIN_ALREADY_SET', 'Ya tienes un PIN configurado. Usa cambiar PIN.');
        }

        $result = $this->authService->setupPin($account, $validated['pin']);

        if (!$result['success']) {
            return $this->badRequest($result['error'] ?? 'PIN_SETUP_FAILED', $result['message']);
        }

        return $this->success(null, $result['message']);
    }

    /**
     * Change PIN (requires authentication).
     *
     * POST /v2/applicant/auth/pin/change
     */
    public function changePin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_pin' => 'required|string|digits:4',
            'new_pin' => 'required|string|digits:4',
            'new_pin_confirmation' => 'required|string|same:new_pin',
        ]);

        /** @var ApplicantAccount $account */
        $account = $request->user();

        if (!$account instanceof ApplicantAccount) {
            return $this->unauthorized('Token no válido para esta ruta');
        }

        $result = $this->authService->changePin(
            $account,
            $validated['current_pin'],
            $validated['new_pin']
        );

        if (!$result['success']) {
            return $this->badRequest($result['error'] ?? 'PIN_CHANGE_FAILED', $result['message']);
        }

        return $this->success(null, $result['message']);
    }

    /**
     * Check if user exists and has PIN.
     *
     * POST /v2/applicant/auth/check-user
     */
    public function checkUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:phone,email,PHONE,EMAIL',
            'identifier' => 'required|string',
        ]);

        $tenantId = app('tenant.id');

        $result = $this->authService->checkUser(
            $tenantId,
            strtoupper($validated['type']),
            $validated['identifier']
        );

        return $this->success($result);
    }

    /**
     * Get current user info.
     *
     * GET /v2/applicant/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();

        if (!$account instanceof ApplicantAccount) {
            return $this->unauthorized('Token no válido para esta ruta');
        }

        $account->load(['primaryIdentity', 'phoneIdentity', 'emailIdentity']);

        return $this->success([
            'user' => [
                'id' => $account->id,
                'tenant_id' => $account->tenant_id,
                'phone' => $account->primary_phone,
                'email' => $account->primary_email,
                'has_pin' => $account->hasPin(),
                'is_active' => $account->is_active,
                'onboarding_step' => $account->onboarding_step,
                'onboarding_completed' => $account->onboarding_completed,
                'preferences' => $account->preferences,
                'person_id' => $account->person_id,
                'created_at' => $account->created_at?->toIso8601String(),
                'last_login_at' => $account->last_login_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Logout.
     *
     * POST /v2/applicant/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();

        if (!$account instanceof ApplicantAccount) {
            return $this->unauthorized('Token no válido para esta ruta');
        }

        $result = $this->authService->logout($account);

        return $this->success(null, $result['message']);
    }

    /**
     * Refresh token.
     *
     * POST /v2/applicant/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        /** @var ApplicantAccount $account */
        $account = $request->user();

        if (!$account instanceof ApplicantAccount) {
            return $this->unauthorized('Token no válido para esta ruta');
        }

        // Delete current token
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $currentToken */
        $currentToken = $account->currentAccessToken();
        $currentToken?->delete();

        // Create new token
        $newToken = $account->createToken('applicant-token', ['applicant'])->plainTextToken;

        return $this->success(['token' => $newToken]);
    }
}
