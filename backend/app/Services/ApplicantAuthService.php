<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\ApplicantAccount;
use App\Models\ApplicantIdentity;
use App\Models\AuditLog;
use App\Models\OtpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for applicant authentication operations.
 *
 * Handles OTP request/verification, PIN authentication, account creation,
 * and all auth-related business logic for applicants.
 */
class ApplicantAuthService
{
    /**
     * Request OTP for a phone or email.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function requestOtp(
        string $tenantId,
        string $type,
        string $identifier,
        string $channel = 'SMS'
    ): array {
        // Normalize type
        $type = strtoupper($type);
        $channel = strtoupper($channel);

        // Validate type
        if (!in_array($type, ['PHONE', 'EMAIL', 'WHATSAPP'])) {
            return [
                'success' => false,
                'message' => 'Tipo de identidad no válido',
                'error' => 'INVALID_TYPE',
            ];
        }

        // Check rate limit
        if (!OtpRequest::canSendOtp($type, $identifier)) {
            return [
                'success' => false,
                'message' => 'Has alcanzado el límite de solicitudes. Intenta en una hora.',
                'error' => 'RATE_LIMIT_EXCEEDED',
            ];
        }

        // Find existing identity or this will be a new registration
        $identity = ApplicantIdentity::findByIdentifier($type, $identifier, $tenantId);

        // Create OTP request
        $otpRequest = OtpRequest::createForTarget(
            $type,
            $identifier,
            $channel,
            $identity?->id
        );

        // Log the request
        $this->logOtpRequest($tenantId, $type, $identifier, $channel);

        // In production, send OTP via Twilio/MessageBird
        // For now, just return the code in dev mode
        $data = [
            'expires_in' => 600,
            'masked_target' => $this->maskIdentifier($type, $identifier),
        ];

        // In development, include the code for testing
        if (app()->environment('local', 'testing')) {
            $data['code'] = $otpRequest->code;
        }

        return [
            'success' => true,
            'message' => $this->getOtpSentMessage($channel),
            'data' => $data,
        ];
    }

    /**
     * Verify OTP and authenticate or register.
     *
     * @return array{success: bool, message: string, data?: array, error?: string}
     */
    public function verifyOtp(
        string $tenantId,
        string $type,
        string $identifier,
        string $code
    ): array {
        $type = strtoupper($type);

        // Get the latest valid OTP
        $otpRequest = OtpRequest::getLatestValidOtp($type, $identifier);

        if (!$otpRequest) {
            $this->logOtpVerificationFailed($tenantId, $type, $identifier, 'no_valid_otp');
            return [
                'success' => false,
                'message' => 'Código expirado o inválido. Solicita uno nuevo.',
                'error' => 'OTP_NOT_FOUND',
            ];
        }

        // Verify the code
        if (!$otpRequest->verify($code)) {
            $reason = 'invalid_code';
            if ($otpRequest->isExpired()) {
                $reason = 'expired';
            } elseif ($otpRequest->hasTooManyAttempts()) {
                $reason = 'too_many_attempts';
            }

            $this->logOtpVerificationFailed($tenantId, $type, $identifier, $reason);

            if ($otpRequest->hasTooManyAttempts()) {
                return [
                    'success' => false,
                    'message' => 'Demasiados intentos fallidos. Solicita un nuevo código.',
                    'error' => 'TOO_MANY_ATTEMPTS',
                    'data' => ['remaining_attempts' => 0],
                ];
            }

            return [
                'success' => false,
                'message' => 'Código incorrecto',
                'error' => 'INVALID_CODE',
                'data' => ['remaining_attempts' => $otpRequest->remaining_attempts],
            ];
        }

        // OTP verified - find or create account
        return DB::transaction(function () use ($tenantId, $type, $identifier) {
            $identity = ApplicantIdentity::findByIdentifier($type, $identifier, $tenantId);

            if ($identity) {
                // Existing user - verify identity if not already verified
                if (!$identity->isVerified()) {
                    $identity->update([
                        'verified_at' => now(),
                        'last_used_at' => now(),
                    ]);
                } else {
                    $identity->update(['last_used_at' => now()]);
                }

                return $this->createLoginResponse($identity->account, 'OTP_' . $type);
            }

            // New user - create account and identity
            return $this->createNewAccount($tenantId, $type, $identifier);
        });
    }

    /**
     * Authenticate with PIN.
     *
     * @return array{success: bool, message: string, data?: array, error?: string}
     */
    public function loginWithPin(
        string $tenantId,
        string $type,
        string $identifier,
        string $pin
    ): array {
        $type = strtoupper($type);

        // Find identity
        $identity = ApplicantIdentity::findByIdentifier($type, $identifier, $tenantId);

        if (!$identity) {
            return [
                'success' => false,
                'message' => 'Cuenta no encontrada',
                'error' => 'ACCOUNT_NOT_FOUND',
            ];
        }

        $account = $identity->account;

        // Check if active
        if (!$account->is_active) {
            $this->logLoginFailed($tenantId, $account->id, 'PIN', 'account_disabled');
            return [
                'success' => false,
                'message' => 'Tu cuenta ha sido desactivada. Contacta soporte.',
                'error' => 'ACCOUNT_DISABLED',
            ];
        }

        // Check if has PIN
        if (!$account->hasPin()) {
            return [
                'success' => false,
                'message' => 'No has configurado un PIN. Usa código de verificación.',
                'error' => 'PIN_NOT_SET',
            ];
        }

        // Check if locked
        if ($account->isPinLocked()) {
            $this->logLoginFailed($tenantId, $account->id, 'PIN', 'account_locked');
            return [
                'success' => false,
                'message' => 'Cuenta bloqueada. Intenta en ' . $account->lockout_minutes . ' minutos.',
                'error' => 'ACCOUNT_LOCKED',
                'data' => ['lockout_minutes' => $account->lockout_minutes],
            ];
        }

        // Verify PIN
        if (!$account->verifyPin($pin)) {
            $account->incrementPinAttempts();

            $this->logLoginFailed($tenantId, $account->id, 'PIN', 'invalid_pin');

            if ($account->isPinLocked()) {
                return [
                    'success' => false,
                    'message' => 'Demasiados intentos. Cuenta bloqueada por 30 minutos.',
                    'error' => 'ACCOUNT_LOCKED',
                    'data' => ['lockout_minutes' => 30],
                ];
            }

            return [
                'success' => false,
                'message' => 'PIN incorrecto',
                'error' => 'INVALID_PIN',
                'data' => ['remaining_attempts' => $account->remaining_pin_attempts],
            ];
        }

        // Successful login
        $account->resetPinAttempts();
        $identity->update(['last_used_at' => now()]);

        return $this->createLoginResponse($account, 'PIN');
    }

    /**
     * Setup PIN for account.
     *
     * @return array{success: bool, message: string}
     */
    public function setupPin(ApplicantAccount $account, string $pin): array
    {
        // Validate PIN format (6 digits)
        if (!preg_match('/^\d{6}$/', $pin)) {
            return [
                'success' => false,
                'message' => 'El PIN debe ser de 6 dígitos numéricos',
                'error' => 'INVALID_PIN_FORMAT',
            ];
        }

        // Check if PIN is too simple
        if ($this->isPinTooSimple($pin)) {
            return [
                'success' => false,
                'message' => 'El PIN es muy simple. Elige uno más seguro.',
                'error' => 'PIN_TOO_SIMPLE',
            ];
        }

        $account->setPin($pin);

        // Log PIN setup (user_id = null since ApplicantAccount is not in users table)
        AuditLog::log(
            AuditAction::PIN_SET->value,
            $account->tenant_id,
            [
                'user_id' => null, // Explicitly null - ApplicantAccount is separate from users
                'applicant_id' => $account->id,
                'metadata' => [
                    'account_id' => $account->id,
                    'is_applicant' => true,
                    'auth_version' => 'v2',
                ],
            ]
        );

        return [
            'success' => true,
            'message' => 'PIN configurado correctamente',
        ];
    }

    /**
     * Change PIN.
     *
     * @return array{success: bool, message: string}
     */
    public function changePin(ApplicantAccount $account, string $currentPin, string $newPin): array
    {
        // Verify current PIN
        if (!$account->verifyPin($currentPin)) {
            return [
                'success' => false,
                'message' => 'PIN actual incorrecto',
                'error' => 'INVALID_CURRENT_PIN',
            ];
        }

        // Validate new PIN
        if (!preg_match('/^\d{6}$/', $newPin)) {
            return [
                'success' => false,
                'message' => 'El nuevo PIN debe ser de 6 dígitos numéricos',
                'error' => 'INVALID_PIN_FORMAT',
            ];
        }

        if ($this->isPinTooSimple($newPin)) {
            return [
                'success' => false,
                'message' => 'El nuevo PIN es muy simple. Elige uno más seguro.',
                'error' => 'PIN_TOO_SIMPLE',
            ];
        }

        $account->setPin($newPin);

        return [
            'success' => true,
            'message' => 'PIN cambiado correctamente',
        ];
    }

    /**
     * Check if user exists and has PIN.
     *
     * @return array{exists: bool, has_pin: bool, is_locked: bool, lockout_minutes: int}
     */
    public function checkUser(string $tenantId, string $type, string $identifier): array
    {
        $type = strtoupper($type);

        $identity = ApplicantIdentity::findByIdentifier($type, $identifier, $tenantId);

        if (!$identity) {
            return [
                'exists' => false,
                'has_pin' => false,
                'is_locked' => false,
                'lockout_minutes' => 0,
            ];
        }

        $account = $identity->account;

        return [
            'exists' => true,
            'has_pin' => $account->hasPin(),
            'is_locked' => $account->isPinLocked(),
            'lockout_minutes' => $account->lockout_minutes,
        ];
    }

    /**
     * Logout - revoke current token.
     */
    public function logout(ApplicantAccount $account): array
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $token */
        $token = $account->currentAccessToken();
        $token?->delete();

        Log::info('Applicant logout', [
            'tenant_id' => $account->tenant_id,
            'account_id' => $account->id,
            'auth_version' => 'v2',
        ]);

        return [
            'success' => true,
            'message' => 'Sesión cerrada correctamente',
        ];
    }

    // =====================================================
    // Private Helpers
    // =====================================================

    /**
     * Create a new account with identity.
     */
    private function createNewAccount(string $tenantId, string $type, string $identifier): array
    {
        $account = ApplicantAccount::create([
            'tenant_id' => $tenantId,
            'is_active' => true,
            'onboarding_step' => 0,
        ]);

        $identity = ApplicantIdentity::create([
            'account_id' => $account->id,
            'type' => $type,
            'identifier' => $identifier,
            'verified_at' => now(),
            'is_primary' => true,
            'last_used_at' => now(),
        ]);

        // Log registration
        AuditLog::log(
            AuditAction::USER_CREATED->value,
            $tenantId,
            [
                'applicant_id' => $account->id,
                'metadata' => [
                    'identity_type' => $type,
                    'is_applicant' => true,
                    'auth_version' => 'v2',
                ],
            ]
        );

        return $this->createLoginResponse($account, 'OTP_' . $type, true);
    }

    /**
     * Create login response with token.
     */
    private function createLoginResponse(ApplicantAccount $account, string $method, bool $isNew = false): array
    {
        // Record login
        $account->recordLogin($method);

        // Create token
        $token = $account->createToken('applicant-token', ['applicant'])->plainTextToken;

        // Log successful login
        AuditLog::log(
            AuditAction::LOGIN_SUCCESS->value,
            $account->tenant_id,
            [
                'applicant_id' => $account->id,
                'metadata' => [
                    'method' => $method,
                    'is_applicant' => true,
                    'auth_version' => 'v2',
                ],
            ]
        );

        // Load identity for response
        $account->load(['primaryIdentity', 'phoneIdentity', 'emailIdentity']);

        return [
            'success' => true,
            'message' => $isNew ? 'Registro exitoso' : 'Inicio de sesión exitoso',
            'data' => [
                'token' => $token,
                'is_new_user' => $isNew,
                'user' => $this->formatUserResponse($account),
            ],
        ];
    }

    /**
     * Format user response.
     */
    private function formatUserResponse(ApplicantAccount $account): array
    {
        return [
            'id' => $account->id,
            'phone' => $account->primary_phone,
            'email' => $account->primary_email,
            'has_pin' => $account->hasPin(),
            'is_active' => $account->is_active,
            'onboarding_step' => $account->onboarding_step,
            'onboarding_completed' => $account->onboarding_completed,
            'preferences' => $account->preferences,
        ];
    }

    /**
     * Get OTP sent message based on channel.
     */
    private function getOtpSentMessage(string $channel): string
    {
        return match ($channel) {
            'SMS' => 'Código enviado por SMS',
            'EMAIL' => 'Código enviado a tu correo',
            'WHATSAPP' => 'Código enviado por WhatsApp',
            default => 'Código enviado',
        };
    }

    /**
     * Mask identifier for display.
     */
    private function maskIdentifier(string $type, string $identifier): string
    {
        if ($type === 'EMAIL') {
            $parts = explode('@', $identifier);
            if (count($parts) !== 2) {
                return '***';
            }
            $local = $parts[0];
            $domain = $parts[1];
            $maskedLocal = substr($local, 0, min(2, strlen($local))) . '***';
            return $maskedLocal . '@' . $domain;
        }

        // Phone
        if (strlen($identifier) < 6) {
            return '***';
        }
        return substr($identifier, 0, 4) . '****' . substr($identifier, -2);
    }

    /**
     * Check if PIN is too simple.
     */
    private function isPinTooSimple(string $pin): bool
    {
        // All same digits
        if (preg_match('/^(\d)\1{5}$/', $pin)) {
            return true;
        }

        // Sequential ascending
        $sequential = true;
        for ($i = 1; $i < 6; $i++) {
            if (intval($pin[$i]) !== intval($pin[$i - 1]) + 1) {
                $sequential = false;
                break;
            }
        }
        if ($sequential) {
            return true;
        }

        // Sequential descending
        $sequential = true;
        for ($i = 1; $i < 6; $i++) {
            if (intval($pin[$i]) !== intval($pin[$i - 1]) - 1) {
                $sequential = false;
                break;
            }
        }
        if ($sequential) {
            return true;
        }

        return false;
    }

    // =====================================================
    // Logging Helpers
    // =====================================================

    private function logOtpRequest(string $tenantId, string $type, string $identifier, string $channel): void
    {
        AuditLog::log(
            AuditAction::OTP_REQUESTED->value,
            $tenantId,
            [
                'metadata' => [
                    'identity_type' => $type,
                    'channel' => $channel,
                    'masked_identifier' => $this->maskIdentifier($type, $identifier),
                    'is_applicant' => true,
                    'auth_version' => 'v2',
                ],
            ]
        );
    }

    private function logOtpVerificationFailed(string $tenantId, string $type, string $identifier, string $reason): void
    {
        AuditLog::log(
            AuditAction::OTP_VERIFIED->value,
            $tenantId,
            [
                'metadata' => [
                    'identity_type' => $type,
                    'masked_identifier' => $this->maskIdentifier($type, $identifier),
                    'success' => false,
                    'reason' => $reason,
                    'is_applicant' => true,
                    'auth_version' => 'v2',
                ],
            ]
        );
    }

    private function logLoginFailed(string $tenantId, string $accountId, string $method, string $reason): void
    {
        AuditLog::log(
            AuditAction::LOGIN_FAILED->value,
            $tenantId,
            [
                'applicant_id' => $accountId,
                'metadata' => [
                    'method' => $method,
                    'reason' => $reason,
                    'is_applicant' => true,
                    'auth_version' => 'v2',
                ],
            ]
        );
    }
}
