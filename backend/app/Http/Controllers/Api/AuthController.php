<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\ExternalApi\TwilioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
                purpose: 'LOGIN',
                tenantId: app('tenant.id')
            );

            // Send OTP via Twilio
            $sent = false;
            $sendError = null;

            if ($channel === 'SMS' || $channel === 'WHATSAPP') {
                try {
                    $twilioService = new TwilioService(app('tenant.id'));

                    if ($channel === 'WHATSAPP') {
                        $result = $twilioService->sendOtp($phone, $otp->code, 'whatsapp');
                    } else {
                        $result = $twilioService->sendOtp($phone, $otp->code, 'sms');
                    }

                    $sent = $result['success'] ?? false;
                    if (!$sent) {
                        $sendError = $result['error'] ?? 'Failed to send OTP';
                        Log::warning('Failed to send OTP via Twilio', [
                            'tenant_id' => app('tenant.id'),
                            'phone' => $phone,
                            'channel' => $channel,
                            'error' => $sendError,
                        ]);
                    }
                } catch (\Exception $e) {
                    $sendError = $e->getMessage();
                    Log::error('Exception sending OTP via Twilio', [
                        'tenant_id' => app('tenant.id'),
                        'phone' => $phone,
                        'channel' => $channel,
                        'error' => $e->getMessage(),
                    ]);
                }
            } elseif ($channel === 'EMAIL') {
                // TODO: Implement email sending
                Log::info('Email OTP not implemented yet', ['email' => $email]);
                $sent = false;
                $sendError = 'Email OTP not implemented';
            }

            // Log OTP request
            $metadata = $request->attributes->get('metadata', []);
            AuditLog::log(
                AuditAction::OTP_REQUESTED->value,
                app('tenant.id'),
                array_merge($metadata, [
                    'metadata' => [
                        'channel' => $channel,
                        'destination_masked' => $email
                            ? substr($email, 0, 3) . '***@' . substr($email, strpos($email, '@') + 1)
                            : substr($phone, 0, 3) . '****' . substr($phone, -2),
                        'sent_successfully' => $sent,
                        'send_error' => $sendError,
                    ],
                ])
            );

            $response = [
                'success' => true,
                'message' => $sent
                    ? 'Código enviado correctamente'
                    : 'Código generado (no se pudo enviar por ' . ($channel === 'WHATSAPP' ? 'WhatsApp' : 'SMS') . ')',
                'channel' => $channel,
            ];

            // Only in dev/local mode, return the code
            if (app()->environment('local', 'development')) {
                $response['code'] = $otp->code;
                $response['sent_via_provider'] = $sent;
                if (!$sent && $sendError) {
                    $response['send_error'] = $sendError;
                }
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('OTP generation error', [
                'tenant_id' => app('tenant.id'),
                'destination' => $destination,
                'error' => $e->getMessage(),
            ]);

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

        \Log::info('🔐 OTP Verify Request', [
            'phone' => $request->phone,
            'email' => $request->email,
            'destination' => $destination,
            'code' => $request->code,
        ]);

        $otp = OtpCode::verify($destination, $request->code);

        if (!$otp) {
            \Log::warning('🔐 OTP Verify Failed - Invalid code');
            return response()->json([
                'error' => 'Invalid code',
                'message' => 'El código es inválido o ha expirado',
            ], 401);
        }

        // Find or create user
        $user = User::where('tenant_id', app('tenant.id'))
            ->where(function ($query) use ($request) {
                if ($request->phone) {
                    $query->where('phone', $request->phone);
                }
                if ($request->email) {
                    $query->orWhere('email', $request->email);
                }
            })
            ->first();

        \Log::info('🔐 OTP Verify - User Search Result', [
            'tenant_id' => app('tenant.id'),
            'phone_search' => $request->phone,
            'email_search' => $request->email,
            'user_found' => $user !== null,
            'user_id' => $user?->id,
            'user_phone' => $user?->phone,
            'user_email' => $user?->email,
            'user_name' => $user?->name,
        ]);

        $isNewUser = false;
        if (!$user) {
            $isNewUser = true;
            $user = User::create([
                'tenant_id' => app('tenant.id'),
                'phone' => $request->phone,
                'email' => $request->email,
                'name' => '', // Name will be set when applicant completes personal data
                'type' => 'APPLICANT',
                'is_active' => true,
                'phone_verified_at' => $request->phone ? now() : null,
                'email_verified_at' => $request->email ? now() : null,
            ]);
        }

        // Mark phone as verified if not already
        if ($request->phone && !$user->hasVerifiedPhone()) {
            $user->markPhoneAsVerified();

            // Also mark phone as verified in Applicant if exists
            if ($user->applicant) {
                // Update applicant phone_verified_at if not set
                if (!$user->applicant->phone_verified_at) {
                    $user->applicant->update(['phone_verified_at' => now()]);
                }

                // Check if phone verification is already recorded
                $existingVerification = \App\Models\DataVerification::where('applicant_id', $user->applicant->id)
                    ->where('field_name', 'phone')
                    ->where('is_verified', true)
                    ->exists();

                // Only create if not already verified
                if (!$existingVerification) {
                    \App\Models\DataVerification::create([
                        'tenant_id' => app('tenant.id'),
                        'applicant_id' => $user->applicant->id,
                        'field_name' => 'phone',
                        'field_value' => $request->phone,
                        'method' => \App\Enums\VerificationMethod::OTP,
                        'is_verified' => true,
                        'is_locked' => true, // Lock phone verified by OTP
                        'status' => \App\Enums\VerificationStatus::VERIFIED,
                        'notes' => 'Verificado vía OTP/SMS',
                        'metadata' => ['otp_verified_at' => now()->toIso8601String()],
                    ]);
                }
            }
        }

        // Sync verification for users with verified phone but applicant not synced
        if ($request->phone && $user->hasVerifiedPhone() && $user->applicant) {
            // Update applicant phone_verified_at if user has verified phone but applicant doesn't
            if (!$user->applicant->phone_verified_at) {
                $user->applicant->update(['phone_verified_at' => $user->phone_verified_at]);
            }

            // Create DataVerification if doesn't exist
            $existingVerification = \App\Models\DataVerification::where('applicant_id', $user->applicant->id)
                ->where('field_name', 'phone')
                ->where('is_verified', true)
                ->exists();

            if (!$existingVerification) {
                \App\Models\DataVerification::create([
                    'tenant_id' => app('tenant.id'),
                    'applicant_id' => $user->applicant->id,
                    'field_name' => 'phone',
                    'field_value' => $request->phone,
                    'method' => \App\Enums\VerificationMethod::OTP,
                    'is_verified' => true,
                    'is_locked' => true, // Lock phone verified by OTP
                    'status' => \App\Enums\VerificationStatus::VERIFIED,
                    'notes' => 'Verificado vía OTP/SMS (sincronizado)',
                    'metadata' => ['otp_verified_at' => $user->phone_verified_at->toIso8601String()],
                ]);
            }
        }

        // Mark email as verified if OTP was sent to email
        if ($request->email && !$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            // Also mark email as verified in Applicant if exists
            if ($user->applicant) {
                // Update applicant email_verified_at if not set
                if (!$user->applicant->email_verified_at) {
                    $user->applicant->update(['email_verified_at' => now()]);
                }

                // Check if email verification is already recorded
                $existingEmailVerification = \App\Models\DataVerification::where('applicant_id', $user->applicant->id)
                    ->where('field_name', 'email')
                    ->where('is_verified', true)
                    ->exists();

                // Only create if not already verified
                if (!$existingEmailVerification) {
                    \App\Models\DataVerification::create([
                        'tenant_id' => app('tenant.id'),
                        'applicant_id' => $user->applicant->id,
                        'field_name' => 'email',
                        'field_value' => $request->email,
                        'method' => \App\Enums\VerificationMethod::OTP,
                        'is_verified' => true,
                        'is_locked' => true, // Lock email verified by OTP
                        'status' => \App\Enums\VerificationStatus::VERIFIED,
                        'notes' => 'Verificado vía OTP/Email',
                        'metadata' => ['otp_verified_at' => now()->toIso8601String()],
                    ]);
                }
            }
        }

        // Sync verification for users with verified email but applicant not synced
        if ($request->email && $user->hasVerifiedEmail() && $user->applicant) {
            // Update applicant email_verified_at if user has verified email but applicant doesn't
            if (!$user->applicant->email_verified_at) {
                $user->applicant->update(['email_verified_at' => $user->email_verified_at]);
            }

            // Create DataVerification if doesn't exist
            $existingEmailVerification = \App\Models\DataVerification::where('applicant_id', $user->applicant->id)
                ->where('field_name', 'email')
                ->where('is_verified', true)
                ->exists();

            if (!$existingEmailVerification) {
                \App\Models\DataVerification::create([
                    'tenant_id' => app('tenant.id'),
                    'applicant_id' => $user->applicant->id,
                    'field_name' => 'email',
                    'field_value' => $request->email,
                    'method' => \App\Enums\VerificationMethod::OTP,
                    'is_verified' => true,
                    'is_locked' => true, // Lock email verified by OTP
                    'status' => \App\Enums\VerificationStatus::VERIFIED,
                    'notes' => 'Verificado vía OTP/Email (sincronizado)',
                    'metadata' => ['otp_verified_at' => $user->email_verified_at->toIso8601String()],
                ]);
            }
        }

        // Record login
        $user->recordLogin();

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Log OTP verification and potential user creation
        $metadata = $request->attributes->get('metadata', []);
        AuditLog::log(
            AuditAction::OTP_VERIFIED->value,
            app('tenant.id'),
            array_merge($metadata, [
                'user_id' => $user->id,
                'applicant_id' => $user->applicant?->id,
            ])
        );

        if ($isNewUser) {
            AuditLog::log(
                AuditAction::USER_CREATED->value,
                app('tenant.id'),
                array_merge($metadata, [
                    'user_id' => $user->id,
                    'entity_type' => 'User',
                    'entity_id' => $user->id,
                    'new_values' => [
                        'phone' => $user->phone,
                        'email' => $user->email,
                        'type' => $user->type,
                    ],
                ])
            );
        }

        AuditLog::log(
            AuditAction::LOGIN_SUCCESS->value,
            app('tenant.id'),
            array_merge($metadata, [
                'user_id' => $user->id,
                'applicant_id' => $user->applicant?->id,
                'metadata' => ['method' => 'OTP'],
            ])
        );

        \Log::info('✅ OTP Verify Success - Returning User', [
            'user_id' => $user->id,
            'user_phone' => $user->phone,
            'user_email' => $user->email,
            'user_name' => $user->name,
            'is_new_user' => $isNewUser,
        ]);

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

        \Log::info('🔐 Check User', [
            'phone_received' => $request->phone,
            'tenant_id' => app('tenant.id'),
            'user_found' => $user !== null,
            'user_id' => $user?->id,
            'user_phone' => $user?->phone,
            'user_name' => $user?->name,
            'has_pin' => $user?->hasPin() ?? false,
        ]);

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

        // Log PIN setup
        $metadata = $request->attributes->get('metadata', []);
        AuditLog::log(
            AuditAction::PIN_SET->value,
            app('tenant.id'),
            array_merge($metadata, [
                'user_id' => $user->id,
                'applicant_id' => $user->applicant?->id,
            ])
        );

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

        \Log::info('🔐 PIN Login Attempt', [
            'phone_received' => $request->phone,
            'tenant_id' => app('tenant.id'),
            'user_found' => $user !== null,
            'user_id' => $user?->id,
            'user_phone' => $user?->phone,
            'user_name' => $user?->name,
        ]);

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

        $metadata = $request->attributes->get('metadata', []);

        if (!$user->verifyPin($request->pin)) {
            $user->incrementPinAttempts();
            $remaining = User::MAX_PIN_ATTEMPTS - $user->pin_attempts;

            // Log failed login attempt
            AuditLog::log(
                AuditAction::LOGIN_FAILED->value,
                app('tenant.id'),
                array_merge($metadata, [
                    'user_id' => $user->id,
                    'applicant_id' => $user->applicant?->id,
                    'metadata' => [
                        'method' => 'PIN',
                        'reason' => 'invalid_pin',
                        'attempts_remaining' => $remaining,
                    ],
                ])
            );

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

        // Log successful login
        AuditLog::log(
            AuditAction::LOGIN_SUCCESS->value,
            app('tenant.id'),
            array_merge($metadata, [
                'user_id' => $user->id,
                'applicant_id' => $user->applicant?->id,
                'metadata' => ['method' => 'PIN'],
            ])
        );

        \Log::info('✅ PIN Login Success', [
            'phone_request' => $request->phone,
            'user_id' => $user->id,
            'user_phone' => $user->phone,
            'user_name' => $user->name,
            'user_email' => $user->email,
        ]);

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
     * Login with email + password (for admin/staff users).
     */
    public function loginWithPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)
            ->where('tenant_id', app('tenant.id'))
            ->first();

        if (!$user) {
            return response()->json([
                'error' => 'User not found',
                'message' => 'No existe una cuenta con este correo electrónico',
            ], 404);
        }

        // Only allow staff users to login with password
        if (!$user->isStaff()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Este método de autenticación no está disponible para tu cuenta',
            ], 403);
        }

        if (!$user->password || !\Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Invalid credentials',
                'message' => 'Correo o contraseña incorrectos',
            ], 401);
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
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'type' => $user->type,
                'role' => $user->type, // Alias for frontend compatibility
                'is_admin' => $user->isAdmin(),
                'is_staff' => $user->isStaff(),
                'permissions' => [
                    'canViewAllApplications' => $user->canViewAllApplications(),
                    'canReviewDocuments' => $user->canReviewDocuments(),
                    'canVerifyReferences' => $user->canVerifyReferences(),
                    'canChangeApplicationStatus' => $user->canChangeApplicationStatus(),
                    'canApproveRejectApplications' => $user->canApproveRejectApplications(),
                    'canAssignApplications' => $user->canAssignApplications(),
                    'canManageProducts' => $user->canManageProducts(),
                    'canManageUsers' => $user->canManageUsers(),
                    'canViewReports' => $user->canViewReports(),
                    'canConfigureTenant' => $user->canConfigureTenant(),
                ],
            ],
        ]);
    }

    /**
     * Admin/Staff Login (email + password).
     * Specific endpoint for admin panel authentication.
     */
    public function adminLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)
            ->where('tenant_id', app('tenant.id'))
            ->first();

        if (!$user) {
            return response()->json([
                'error' => 'Invalid credentials',
                'message' => 'Correo o contraseña incorrectos',
            ], 401);
        }

        // Only allow staff users to login via admin endpoint
        if (!$user->isStaff()) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Acceso denegado. Solo personal autorizado.',
            ], 403);
        }

        // Check if account is active
        if (!$user->is_active) {
            return response()->json([
                'error' => 'Account disabled',
                'message' => 'Tu cuenta ha sido desactivada. Contacta al administrador.',
            ], 403);
        }

        $metadata = $request->attributes->get('metadata', []);

        if (!$user->password || !\Hash::check($request->password, $user->password)) {
            // Log failed admin login
            AuditLog::log(
                AuditAction::LOGIN_FAILED->value,
                app('tenant.id'),
                array_merge($metadata, [
                    'user_id' => $user->id,
                    'metadata' => [
                        'method' => 'PASSWORD',
                        'reason' => 'invalid_password',
                        'is_admin' => true,
                    ],
                ])
            );

            return response()->json([
                'error' => 'Invalid credentials',
                'message' => 'Correo o contraseña incorrectos',
            ], 401);
        }

        // Record login
        $user->recordLogin();

        // Create token with admin-specific abilities
        $token = $user->createToken('admin-token', ['admin'])->plainTextToken;

        // Log successful admin login
        AuditLog::log(
            AuditAction::LOGIN_SUCCESS->value,
            app('tenant.id'),
            array_merge($metadata, [
                'user_id' => $user->id,
                'metadata' => [
                    'method' => 'PASSWORD',
                    'is_admin' => true,
                    'role' => $user->type,
                ],
            ])
        );

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->type,
                'is_staff' => true,
                'permissions' => [
                    'canViewAllApplications' => $user->canViewAllApplications(),
                    'canReviewDocuments' => $user->canReviewDocuments(),
                    'canVerifyReferences' => $user->canVerifyReferences(),
                    'canChangeApplicationStatus' => $user->canChangeApplicationStatus(),
                    'canApproveRejectApplications' => $user->canApproveRejectApplications(),
                    'canAssignApplications' => $user->canAssignApplications(),
                    'canManageProducts' => $user->canManageProducts(),
                    'canManageUsers' => $user->canManageUsers(),
                    'canViewReports' => $user->canViewReports(),
                    'canConfigureTenant' => $user->canConfigureTenant(),
                ],
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
        $user = $request->user();

        // Log logout
        $metadata = $request->attributes->get('metadata', []);
        AuditLog::log(
            AuditAction::LOGOUT->value,
            app('tenant.id'),
            array_merge($metadata, [
                'user_id' => $user->id,
                'applicant_id' => $user->applicant?->id,
            ])
        );

        $user->currentAccessToken()->delete();

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

        $response = [
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'email' => $user->email,
                'type' => $user->type,
                'is_admin' => $user->isAdmin(),
                'is_staff' => $user->isStaff(),
                'has_pin' => $user->hasPin(),
                'applicant' => $user->applicant,
            ],
        ];

        // Include permissions for staff users
        if ($user->isStaff()) {
            $response['user']['permissions'] = [
                'canViewAllApplications' => $user->canViewAllApplications(),
                'canReviewDocuments' => $user->canReviewDocuments(),
                'canVerifyReferences' => $user->canVerifyReferences(),
                'canChangeApplicationStatus' => $user->canChangeApplicationStatus(),
                'canApproveRejectApplications' => $user->canApproveRejectApplications(),
                'canAssignApplications' => $user->canAssignApplications(),
                'canManageProducts' => $user->canManageProducts(),
                'canManageUsers' => $user->canManageUsers(),
                'canViewReports' => $user->canViewReports(),
                'canConfigureTenant' => $user->canConfigureTenant(),
            ];
        }

        return response()->json($response);
    }
}
