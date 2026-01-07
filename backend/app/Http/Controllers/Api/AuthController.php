<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Request OTP code.
     */
    public function requestOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required_without:email|string|regex:/^[0-9]{10}$/',
            'email' => 'required_without:phone|email',
            'channel' => 'sometimes|in:SMS,WHATSAPP,EMAIL',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $request->phone;
        $email = $request->email;
        $channel = $request->channel ?? ($email ? 'EMAIL' : 'SMS');

        $destination = $email ?: $phone;

        try {
            $otp = OtpCode::generate(
                destination: $destination,
                channel: $channel,
                purpose: OtpCode::PURPOSE_LOGIN,
                tenantId: app('tenant.id')
            );

            // TODO: Send OTP via provider (Twilio, MessageBird, etc.)
            // For development, we'll just return success
            // In production, integrate with SMS/Email provider

            return response()->json([
                'success' => true,
                'message' => 'Código enviado correctamente',
                'channel' => $channel,
                // Only in dev mode
                'code' => app()->environment('local') ? $otp->code : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to send OTP',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify OTP code and authenticate.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required_without:email|string|regex:/^[0-9]{10}$/',
            'email' => 'required_without:phone|email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $destination = $request->email ?: $request->phone;

        $otp = OtpCode::verify($destination, $request->code);

        if (!$otp) {
            return response()->json([
                'error' => 'Invalid code',
                'message' => 'El código es inválido o ha expirado',
            ], 401);
        }

        // Find or create user
        $user = User::where('phone', $request->phone)
            ->orWhere('email', $request->email)
            ->first();

        if (!$user) {
            $user = User::create([
                'tenant_id' => app('tenant.id'),
                'phone' => $request->phone,
                'email' => $request->email,
                'name' => '', // Name will be set when applicant completes personal data
                'type' => User::TYPE_APPLICANT,
                'is_active' => true,
                'phone_verified_at' => $request->phone ? now() : null,
                'email_verified_at' => $request->email ? now() : null,
            ]);
        }

        // Mark phone as verified if not already
        if ($request->phone && !$user->hasVerifiedPhone()) {
            $user->markPhoneAsVerified();
        }

        // Record login
        $user->recordLogin();

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'email' => $user->email,
                'type' => $user->type,
                'is_admin' => $user->isAdmin(),
                'has_pin' => $user->hasPin(),
            ],
        ]);
    }

    /**
     * Check if user has PIN set (for login flow decision).
     */
    public function checkUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9]{10}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('phone', $request->phone)
            ->where('tenant_id', app('tenant.id'))
            ->first();

        return response()->json([
            'exists' => $user !== null,
            'has_pin' => $user?->hasPin() ?? false,
            'is_locked' => $user?->isPinLocked() ?? false,
            'lockout_minutes' => $user?->getPinLockoutMinutes() ?? 0,
        ]);
    }

    /**
     * Setup PIN after OTP verification.
     */
    public function setupPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pin' => 'required|string|digits:4',
            'pin_confirmation' => 'required|string|same:pin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if ($user->hasPin()) {
            return response()->json([
                'error' => 'PIN already set',
                'message' => 'Ya tienes un NIP configurado. Usa la opción de cambiar NIP.',
            ], 400);
        }

        $user->setPin($request->pin);

        return response()->json([
            'success' => true,
            'message' => 'NIP configurado correctamente',
        ]);
    }

    /**
     * Login with phone + PIN.
     */
    public function loginWithPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9]{10}$/',
            'pin' => 'required|string|digits:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('phone', $request->phone)
            ->where('tenant_id', app('tenant.id'))
            ->first();

        if (!$user) {
            return response()->json([
                'error' => 'User not found',
                'message' => 'No existe una cuenta con este número de teléfono',
            ], 404);
        }

        if (!$user->hasPin()) {
            return response()->json([
                'error' => 'No PIN set',
                'message' => 'No tienes un NIP configurado. Inicia sesión con OTP.',
                'requires_otp' => true,
            ], 400);
        }

        if ($user->isPinLocked()) {
            return response()->json([
                'error' => 'Account locked',
                'message' => 'Cuenta bloqueada por intentos fallidos. Intenta en ' . $user->getPinLockoutMinutes() . ' minutos.',
                'lockout_minutes' => $user->getPinLockoutMinutes(),
            ], 423);
        }

        if (!$user->verifyPin($request->pin)) {
            $user->incrementPinAttempts();
            $remaining = User::MAX_PIN_ATTEMPTS - $user->pin_attempts;

            return response()->json([
                'error' => 'Invalid PIN',
                'message' => 'NIP incorrecto. ' . ($remaining > 0 ? "Te quedan $remaining intentos." : 'Cuenta bloqueada.'),
                'attempts_remaining' => $remaining,
            ], 401);
        }

        // Reset attempts on success
        $user->resetPinAttempts();

        // Record login
        $user->recordLogin();

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'email' => $user->email,
                'type' => $user->type,
                'is_admin' => $user->isAdmin(),
                'has_pin' => true,
            ],
        ]);
    }

    /**
     * Change PIN (requires authentication).
     */
    public function changePin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_pin' => 'required|string|digits:4',
            'new_pin' => 'required|string|digits:4',
            'new_pin_confirmation' => 'required|string|same:new_pin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        if (!$user->hasPin()) {
            return response()->json([
                'error' => 'No PIN set',
                'message' => 'No tienes un NIP configurado.',
            ], 400);
        }

        if ($user->isPinLocked()) {
            return response()->json([
                'error' => 'Account locked',
                'message' => 'Cuenta bloqueada por intentos fallidos.',
                'lockout_minutes' => $user->getPinLockoutMinutes(),
            ], 423);
        }

        if (!$user->changePin($request->current_pin, $request->new_pin)) {
            return response()->json([
                'error' => 'Invalid PIN',
                'message' => 'NIP actual incorrecto',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'NIP cambiado correctamente',
        ]);
    }

    /**
     * Reset PIN via OTP (forgot PIN flow).
     */
    public function resetPinWithOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9]{10}$/',
            'code' => 'required|string|size:6',
            'new_pin' => 'required|string|digits:4',
            'new_pin_confirmation' => 'required|string|same:new_pin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $otp = OtpCode::verify($request->phone, $request->code);

        if (!$otp) {
            return response()->json([
                'error' => 'Invalid code',
                'message' => 'El código es inválido o ha expirado',
            ], 401);
        }

        $user = User::where('phone', $request->phone)
            ->where('tenant_id', app('tenant.id'))
            ->first();

        if (!$user) {
            return response()->json([
                'error' => 'User not found',
                'message' => 'No existe una cuenta con este número de teléfono',
            ], 404);
        }

        $user->setPin($request->new_pin);

        // Record login
        $user->recordLogin();

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'NIP restablecido correctamente',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'email' => $user->email,
                'type' => $user->type,
                'is_admin' => $user->isAdmin(),
                'has_pin' => true,
            ],
        ]);
    }

    /**
     * Logout.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current user info.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('applicant');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'email' => $user->email,
                'type' => $user->type,
                'is_admin' => $user->isAdmin(),
                'applicant' => $user->applicant,
            ],
        ]);
    }
}
