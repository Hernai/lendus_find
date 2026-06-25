<?php

namespace App\Services\ExternalApi\Nubarium;

use App\Contracts\SmsServiceInterface;
use App\Models\Tenant;

/**
 * Cliente OTP/SMS de Nubarium.
 *
 * Nubarium maneja el OTP de forma "administrada": tú envías el mensaje con el
 * placeholder `#code#`, Nubarium genera el código, lo sustituye, lo envía y lo
 * guarda. Después validas llamando a su endpoint con el código que tecleó el
 * usuario. Es decir, NOSOTROS no generamos ni comparamos el código — Nubarium
 * es la fuente de verdad. Por eso `managesCode()` devuelve true y el flujo de
 * verify en ApplicantAuthService delega aquí en lugar de comparar contra la BD.
 *
 * Endpoints (de documenter.nubarium.com, sección OTP & Contact Validation):
 *   POST /glo/otp/v1/send-sms        { mensaje, numeroMovil }
 *   POST /glo/otp/v1/validate-sms    { otp, numeroMovil }
 *   POST /glo/otp/v1/email-send-otp  { email, message }
 *   POST /glo/otp/v1/email-validate-otp { email, otp }
 *   POST /glo/notifications/v1/send-sms { phone, message }   (SMS plano, sin OTP)
 *
 * Importante: Nubarium responde HTTP 200 incluso en errores. El éxito se
 * determina por `status == "OK"` (o `estatus == "OK"`) Y `messageCode == 0`
 * (o `claveMensaje == 0`). El SMS no debe exceder 160 caracteres.
 *
 * Auth: hereda el JWT de BaseNubariumService (Basic Auth → generate-jwt →
 * bearer cacheado). Reusa las mismas credenciales api_key/api_secret del tenant.
 */
class NubariumOtpService extends BaseNubariumService implements SmsServiceInterface
{
    /** Placeholder que Nubarium sustituye por el código generado. */
    private const CODE_PLACEHOLDER = '#code#';

    public function __construct(Tenant $tenant, string $serviceType = 'sms')
    {
        // Debe setearse ANTES de parent::__construct para que loadConfig()
        // (que corre en el constructor de BaseExternalApiService) busque la
        // fila TenantApiConfig correcta: (provider=nubarium, service_type=$x).
        $this->serviceType = $serviceType;
        parent::__construct($tenant);
    }

    /**
     * Este proveedor administra el ciclo de vida del código (genera, envía,
     * valida). El flujo de auth NO debe comparar contra la BD.
     */
    public function managesCode(): bool
    {
        return true;
    }

    // =====================================================
    // OTP administrado (SMS)
    // =====================================================

    /**
     * Envía un OTP por SMS. Nubarium genera el código y lo inserta donde está
     * el placeholder #code#. `$message` es opcional; si no trae el placeholder
     * se le antepone uno por defecto.
     */
    public function sendSmsOtp(string $to, ?string $message = null): array
    {
        $mensaje = $this->buildOtpMessage($message);

        // El endpoint global valida el campo `message` (inglés); el doc también
        // muestra `mensaje`. Mandamos ambos para compatibilidad — `numeroMovil`
        // sí es el nombre correcto del teléfono (Nubarium lo aceptó en pruebas).
        $response = $this->apiCall('global', 'POST', '/glo/otp/v1/send-sms', [
            'message' => $mensaje,
            'mensaje' => $mensaje,
            'numeroMovil' => $this->toNationalNumber($to),
        ]);
        $this->logResponse($response, '/glo/otp/v1/send-sms', 'otp_send_sms');

        return $this->normalizeResult($response, 'No se pudo enviar el SMS OTP');
    }

    /**
     * Valida un OTP por SMS contra Nubarium.
     */
    public function validateSmsOtp(string $to, string $otp): array
    {
        $response = $this->apiCall('global', 'POST', '/glo/otp/v1/validate-sms', [
            'otp' => $otp,
            'numeroMovil' => $this->toNationalNumber($to),
        ]);
        $this->logResponse($response, '/glo/otp/v1/validate-sms', 'otp_validate_sms');

        return $this->normalizeResult($response, 'Código incorrecto');
    }

    // =====================================================
    // OTP administrado (Email)
    // =====================================================

    public function sendEmailOtp(string $email, ?string $message = null): array
    {
        $msg = $this->buildOtpMessage($message);

        $response = $this->apiCall('global', 'POST', '/glo/otp/v1/email-send-otp', [
            'email' => $email,
            'message' => $msg,
            'mensaje' => $msg,
        ]);
        $this->logResponse($response, '/glo/otp/v1/email-send-otp', 'otp_send_email');

        return $this->normalizeResult($response, 'No se pudo enviar el correo OTP');
    }

    public function validateEmailOtp(string $email, string $otp): array
    {
        $response = $this->apiCall('global', 'POST', '/glo/otp/v1/email-validate-otp', [
            'email' => $email,
            'otp' => $otp,
        ]);
        $this->logResponse($response, '/glo/otp/v1/email-validate-otp', 'otp_validate_email');

        return $this->normalizeResult($response, 'Código incorrecto');
    }

    // =====================================================
    // SmsServiceInterface (SMS plano, sin manejo de OTP)
    // =====================================================

    public function sendSms(string $to, string $message): array
    {
        $response = $this->apiCall('global', 'POST', '/glo/notifications/v1/send-sms', [
            'phone' => $this->toE164Digits($to),
            'message' => mb_substr($message, 0, 160),
        ]);
        $this->logResponse($response, '/glo/notifications/v1/send-sms', 'send_sms');

        $result = $this->normalizeResult($response, 'No se pudo enviar el SMS');
        // Normaliza al shape que espera SmsServiceInterface.
        return [
            'success' => $result['success'],
            'message_id' => $result['validation_code'] ?? null,
            'error' => $result['success'] ? null : $result['message'],
        ];
    }

    /**
     * Nubarium OTP no expone WhatsApp en su API pública (solo SMS + Email).
     */
    public function sendWhatsApp(string $to, string $message): array
    {
        return [
            'success' => false,
            'error' => 'Nubarium no soporta WhatsApp; usa Twilio para ese canal.',
        ];
    }

    public function hasSmsCapability(): bool
    {
        return $this->isConfigured();
    }

    public function hasWhatsAppCapability(): bool
    {
        return false;
    }

    // =====================================================
    // Helpers
    // =====================================================

    /**
     * Garantiza que el mensaje traiga el placeholder #code# y no exceda 160
     * caracteres (límite SMS de Nubarium). Sin acentos para no caer en UCS-2.
     */
    private function buildOtpMessage(?string $message): string
    {
        $msg = $message && str_contains($message, self::CODE_PLACEHOLDER)
            ? $message
            : 'Tu codigo de verificacion es: ' . self::CODE_PLACEHOLDER;

        return mb_substr($msg, 0, 160);
    }

    /**
     * Número nacional de 10 dígitos (Nubarium `numeroMovil` espera 10 dígitos,
     * sin lada país). Toma los últimos 10 dígitos.
     */
    private function toNationalNumber(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        return substr($digits, -10);
    }

    /**
     * Para el SMS plano Nubarium espera lada país + número (ej. 52 + 10).
     */
    private function toE164Digits(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) === 10) {
            return '52' . $digits;
        }
        return $digits;
    }

    /**
     * Interpreta la respuesta de Nubarium. Devuelve `success` true sólo cuando
     * status/estatus == OK Y messageCode/claveMensaje == 0 (Nubarium responde
     * 200 incluso en errores). Mantiene el código del mensaje para diagnóstico.
     */
    private function normalizeResult(\Illuminate\Http\Client\Response $response, string $defaultError): array
    {
        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => $defaultError . ' (HTTP ' . $response->status() . ')',
                'code' => null,
                'validation_code' => null,
            ];
        }

        $json = $response->json() ?? [];
        $status = strtoupper((string) ($json['status'] ?? $json['estatus'] ?? ''));
        $msgCode = $json['messageCode'] ?? $json['claveMensaje'] ?? null;
        $ok = $status === 'OK' && ($msgCode === 0 || $msgCode === '0');

        return [
            'success' => $ok,
            'message' => $json['message'] ?? $json['mensaje'] ?? ($ok ? 'OK' : $defaultError),
            'code' => $msgCode,
            'validation_code' => $json['validationCode'] ?? null,
        ];
    }
}
