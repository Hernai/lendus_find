<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantApiConfig;
use App\Services\ExternalApi\Nubarium\NubariumOtpService;
use App\Services\ExternalApi\NubariumService;
use App\Services\ExternalApi\SmtpService;
use App\Services\ExternalApi\TwilioService;

/**
 * Prueba real de una integración de tenant (envía SMS/OTP/email o valida el
 * token según el proveedor) y persiste el resultado en la config.
 *
 * Fuente única de verdad: la usan los TRES endpoints de prueba que existían
 * duplicados y sólo verificaban `hasCredentials()`:
 *   - ConfigController::testApiConfig   (/v2/staff/config/api-configs/{id}/test)
 *   - TenantController::testApiConfig    (/v2/staff/tenants/{id}/api-configs/{cid}/test)
 *   - IntegrationController::test        (/v2/staff/integrations/{id}/test)
 *
 * @phpstan-type TestResult array{success: bool, message: string, details: array, error: ?string, requires: ?string}
 */
class IntegrationTester
{
    /**
     * Ejecuta la prueba y guarda last_tested_at/success/error en la config.
     *
     * @return array{success: bool, message: string, details: array, error: ?string, requires: ?string}
     */
    public function test(TenantApiConfig $config, ?string $testPhone = null, ?string $testEmail = null): array
    {
        try {
            $result = $this->runProviderTest($config, $testPhone, $testEmail);
        } catch (\Throwable $e) {
            $result = [
                'success' => false,
                'message' => 'Error en la prueba: ' . $e->getMessage(),
                'details' => [],
                'error' => $e->getMessage(),
                'requires' => null,
            ];
        }

        $config->update([
            'last_tested_at' => now(),
            'last_test_success' => $result['success'],
            'last_test_error' => $result['success']
                ? null
                : ($result['error'] ?? $result['message'] ?? 'Error desconocido'),
        ]);

        return $result;
    }

    /**
     * @return array{success: bool, message: string, details: array, error: ?string, requires: ?string}
     */
    private function runProviderTest(TenantApiConfig $config, ?string $testPhone, ?string $testEmail): array
    {
        $tenant = Tenant::withoutGlobalScopes()->find($config->tenant_id);
        $provider = $config->provider;
        $serviceType = $config->service_type;

        // Nubarium KYC: sólo genera el token (sin teléfono).
        if ($provider === 'nubarium' && $serviceType === 'kyc') {
            $r = (new NubariumService($tenant))->testConnection();
            return $this->ok(
                (bool) ($r['success'] ?? false),
                $r['message'] ?? (($r['success'] ?? false) ? 'Conexión exitosa - Token obtenido' : 'Error de autenticación'),
                ['token_preview' => $r['token_preview'] ?? null],
                $r['error'] ?? null,
            );
        }

        // Nubarium OTP por SMS/WhatsApp: envía un OTP real (Nubarium genera el código).
        if ($provider === 'nubarium' && in_array($serviceType, ['sms', 'whatsapp'], true)) {
            if (!$testPhone) {
                return $this->needs('test_phone', 'Se requiere un número de teléfono de prueba');
            }
            $r = (new NubariumOtpService($tenant, $serviceType))->sendSmsOtp($testPhone);
            return $this->ok(
                (bool) ($r['success'] ?? false),
                ($r['success'] ?? false) ? 'OTP enviado — revisa el SMS en el teléfono de prueba' : ($r['message'] ?? 'No se pudo enviar el OTP'),
                ['validation_code' => $r['validation_code'] ?? null],
                ($r['success'] ?? false) ? null : ($r['message'] ?? null),
            );
        }

        // Nubarium OTP por Email: envía un OTP real al correo.
        if ($provider === 'nubarium' && $serviceType === 'email') {
            if (!$testEmail) {
                return $this->needs('test_email', 'Se requiere un email de prueba');
            }
            $r = (new NubariumOtpService($tenant, 'email'))->sendEmailOtp($testEmail);
            return $this->ok(
                (bool) ($r['success'] ?? false),
                ($r['success'] ?? false) ? 'OTP enviado — revisa el correo de prueba' : ($r['message'] ?? 'No se pudo enviar el OTP'),
                [],
                ($r['success'] ?? false) ? null : ($r['message'] ?? null),
            );
        }

        // Twilio SMS/WhatsApp: envía un mensaje real de prueba.
        if ($provider === 'twilio' && in_array($serviceType, ['sms', 'whatsapp'], true)) {
            if (!$testPhone) {
                return $this->needs('test_phone', 'Se requiere un número de teléfono de prueba');
            }
            $svc = TwilioService::createFromConfig($config);
            $msg = 'Prueba de integración LendusFind - ' . now()->format('H:i:s');
            $r = $serviceType === 'whatsapp' ? $svc->sendWhatsApp($testPhone, $msg) : $svc->sendSms($testPhone, $msg);
            return $this->ok(
                (bool) ($r['success'] ?? false),
                ($r['success'] ?? false) ? 'Mensaje enviado exitosamente' : ($r['error'] ?? 'No se pudo enviar'),
                ['sid' => $r['sid'] ?? null, 'status' => $r['status'] ?? null],
                ($r['success'] ?? false) ? null : ($r['error'] ?? null),
            );
        }

        // SMTP email: envía un correo de prueba (o valida la conexión).
        if ($provider === 'smtp' && $serviceType === 'email') {
            $svc = SmtpService::createFromConfig($config);
            $r = $testEmail ? $svc->sendTestEmail($testEmail) : $svc->testConnection();
            return $this->ok(
                (bool) ($r['success'] ?? false),
                $r['message'] ?? (($r['success'] ?? false) ? 'Prueba exitosa' : 'Error de conexión SMTP'),
                $r['details'] ?? [],
                ($r['success'] ?? false) ? null : ($r['message'] ?? null),
            );
        }

        // Fallback (proveedores beta / sin prueba de envío): valida credenciales.
        $ok = $config->hasCredentials();
        return $this->ok(
            $ok,
            $ok
                ? 'Credenciales válidas (este proveedor aún no soporta prueba de envío)'
                : 'Credenciales incompletas',
            [],
            $ok ? null : 'Credenciales incompletas',
        );
    }

    /**
     * @return array{success: bool, message: string, details: array, error: ?string, requires: ?string}
     */
    private function ok(bool $success, string $message, array $details = [], ?string $error = null): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'details' => $details,
            'error' => $error,
            'requires' => null,
        ];
    }

    /**
     * @return array{success: bool, message: string, details: array, error: ?string, requires: ?string}
     */
    private function needs(string $field, string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'details' => [],
            'error' => strtoupper($field) . '_REQUIRED',
            'requires' => $field,
        ];
    }
}
