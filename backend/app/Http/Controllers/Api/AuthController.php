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
                'name' => $request->phone ?: $request->email,
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
