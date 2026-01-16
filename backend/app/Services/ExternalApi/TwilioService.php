<?php

namespace App\Services\ExternalApi;

use App\Contracts\SmsServiceInterface;
use App\Models\ApiLog;
use App\Models\SmsLog;
use App\Models\TenantApiConfig;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

/**
 * Twilio SMS/WhatsApp service.
 *
 * Implements SmsServiceInterface for consistent messaging API.
 */
class TwilioService implements SmsServiceInterface
{
    protected ?Client $client = null;
    protected ?string $fromNumber = null;
    protected ?string $whatsappFrom = null;
    protected ?string $tenantId = null;
    protected ?TenantApiConfig $config = null;

    /**
     * Context for API logging.
     */
    protected ?string $applicantId = null;
    protected ?string $applicationId = null;
    protected ?string $userId = null;

    /**
     * Set the applicant context for API logging.
     */
    public function forApplicant(?string $applicantId): static
    {
        $this->applicantId = $applicantId;
        return $this;
    }

    /**
     * Set the application context for API logging.
     */
    public function forApplication(?string $applicationId): static
    {
        $this->applicationId = $applicationId;
        return $this;
    }

    /**
     * Set the user context for API logging.
     */
    public function forUser(?string $userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    public function __construct(?string $tenantId = null)
    {
        $this->tenantId = $tenantId;

        if ($tenantId) {
            $this->loadTenantConfig($tenantId);
        } else {
            // Fallback to global config for testing/development
            $this->loadGlobalConfig();
        }
    }

    /**
     * Load Twilio configuration from tenant
     */
    protected function loadTenantConfig(string $tenantId): void
    {
        // Try to get SMS config first
        $this->config = TenantApiConfig::where('tenant_id', $tenantId)
            ->where('provider', 'twilio')
            ->where('service_type', 'sms')
            ->where('is_active', true)
            ->first();

        if (!$this->config || !$this->config->hasCredentials()) {
            Log::warning('Twilio not configured for tenant', ['tenant_id' => $tenantId]);
            throw new \RuntimeException('Twilio not configured for this tenant');
        }

        // Initialize Twilio client with tenant credentials
        $accountSid = $this->config->account_sid;
        $authToken = $this->config->auth_token;

        Log::debug('Twilio credentials loaded', [
            'tenant_id' => $tenantId,
            'account_sid_length' => strlen($accountSid ?? ''),
            'account_sid_preview' => $accountSid ? substr($accountSid, 0, 4) . '...' . substr($accountSid, -4) : 'null',
            'auth_token_length' => strlen($authToken ?? ''),
        ]);

        $this->client = new Client($accountSid, $authToken);

        $this->fromNumber = $this->config->from_number;

        // Check for WhatsApp config
        $whatsappConfig = TenantApiConfig::where('tenant_id', $tenantId)
            ->where('provider', 'twilio')
            ->where('service_type', 'whatsapp')
            ->where('is_active', true)
            ->first();

        $this->whatsappFrom = $whatsappConfig?->from_number ?? $this->config->extra_config['whatsapp_from'] ?? null;

        Log::info('Twilio configured for tenant', [
            'tenant_id' => $tenantId,
            'from_number' => $this->fromNumber,
            'has_whatsapp' => !empty($this->whatsappFrom),
        ]);
    }

    /**
     * Load global Twilio configuration (fallback for development/testing)
     */
    protected function loadGlobalConfig(): void
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $this->fromNumber = config('services.twilio.from_number');
        $this->whatsappFrom = config('services.twilio.whatsapp_from');

        if (!$accountSid || !$authToken) {
            throw new \RuntimeException('Twilio credentials not configured');
        }

        $this->client = new Client($accountSid, $authToken);

        Log::info('Using global Twilio configuration (no tenant)');
    }

    /**
     * Send SMS message
     */
    public function sendSms(string $to, string $body): array
    {
        $startTime = microtime(true);

        try {
            // Format phone number (ensure E.164 format)
            $to = $this->formatPhoneNumber($to);

            Log::info('Sending SMS via Twilio', [
                'tenant_id' => $this->tenantId,
                'to' => $to,
                'from' => $this->fromNumber,
                'body_length' => strlen($body),
            ]);

            // Send message
            $message = $this->client->messages->create($to, [
                'from' => $this->fromNumber,
                'body' => $body,
            ]);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // Log to database
            $this->logMessage([
                'tenant_id' => $this->tenantId,
                'to' => $to,
                'from' => $this->fromNumber,
                'body' => $body,
                'type' => 'sms',
                'status' => $message->status,
                'sid' => $message->sid,
                'price' => $message->price,
                'price_unit' => $message->priceUnit,
                'error_code' => $message->errorCode,
                'error_message' => $message->errorMessage,
                'num_segments' => $message->numSegments,
                'direction' => $message->direction,
                'sent_at' => now(),
            ]);

            // Log to api_logs
            $this->logApiCall(
                'sms_send',
                ['to' => $to, 'from' => $this->fromNumber, 'body_length' => strlen($body)],
                true,
                ['sid' => $message->sid, 'status' => $message->status, 'price' => $message->price],
                null,
                null,
                $durationMs
            );

            Log::info('SMS sent successfully', [
                'sid' => $message->sid,
                'status' => $message->status,
            ]);

            return [
                'success' => true,
                'sid' => $message->sid,
                'status' => $message->status,
                'message' => 'SMS sent successfully',
            ];
        } catch (TwilioException $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            Log::error('Twilio SMS error', [
                'to' => $to,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            // Log failed attempt
            $this->logMessage([
                'tenant_id' => $this->tenantId,
                'to' => $to,
                'from' => $this->fromNumber,
                'body' => $body,
                'type' => 'sms',
                'status' => 'failed',
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
                'sent_at' => now(),
            ]);

            // Log to api_logs
            $this->logApiCall(
                'sms_send',
                ['to' => $to, 'from' => $this->fromNumber, 'body_length' => strlen($body)],
                false,
                null,
                (string) $e->getCode(),
                $e->getMessage(),
                $durationMs
            );

            // Provide better error messages for common issues
            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, '401') || str_contains($errorMessage, 'Authenticate')) {
                $errorMessage = 'Credenciales de Twilio inválidas. Verifique Account SID y Auth Token en Integraciones.';
            } elseif (str_contains($errorMessage, '21608') || str_contains($errorMessage, 'unverified')) {
                $errorMessage = 'El número de origen no está verificado en Twilio. Verifique la configuración.';
            } elseif (str_contains($errorMessage, '21211')) {
                $errorMessage = 'Número de teléfono destino inválido.';
            }

            return [
                'success' => false,
                'error' => $errorMessage,
                'code' => $e->getCode(),
            ];
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            Log::error('Unexpected error sending SMS', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            // Log to api_logs
            $this->logApiCall(
                'sms_send',
                ['to' => $to, 'from' => $this->fromNumber, 'body_length' => strlen($body)],
                false,
                null,
                'EXCEPTION',
                $e->getMessage(),
                $durationMs
            );

            return [
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send WhatsApp message
     */
    public function sendWhatsApp(string $to, string $body): array
    {
        if (!$this->whatsappFrom) {
            return [
                'success' => false,
                'error' => 'WhatsApp not configured for this tenant',
            ];
        }

        $startTime = microtime(true);

        try {
            // Format phone numbers for WhatsApp
            $to = 'whatsapp:' . $this->formatPhoneNumber($to);
            $from = 'whatsapp:' . $this->formatPhoneNumber($this->whatsappFrom);

            Log::info('Sending WhatsApp via Twilio', [
                'tenant_id' => $this->tenantId,
                'to' => $to,
                'from' => $from,
                'body_length' => strlen($body),
            ]);

            // Send message
            $message = $this->client->messages->create($to, [
                'from' => $from,
                'body' => $body,
            ]);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // Log to database
            $this->logMessage([
                'tenant_id' => $this->tenantId,
                'to' => $to,
                'from' => $from,
                'body' => $body,
                'type' => 'whatsapp',
                'status' => $message->status,
                'sid' => $message->sid,
                'price' => $message->price,
                'price_unit' => $message->priceUnit,
                'error_code' => $message->errorCode,
                'error_message' => $message->errorMessage,
                'num_segments' => $message->numSegments,
                'direction' => $message->direction,
                'sent_at' => now(),
            ]);

            // Log to api_logs
            $this->logApiCall(
                'whatsapp_send',
                ['to' => $to, 'from' => $from, 'body_length' => strlen($body)],
                true,
                ['sid' => $message->sid, 'status' => $message->status, 'price' => $message->price],
                null,
                null,
                $durationMs
            );

            Log::info('WhatsApp sent successfully', [
                'sid' => $message->sid,
                'status' => $message->status,
            ]);

            return [
                'success' => true,
                'sid' => $message->sid,
                'status' => $message->status,
                'message' => 'WhatsApp message sent successfully',
            ];
        } catch (TwilioException $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            Log::error('Twilio WhatsApp error', [
                'to' => $to,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            // Log failed attempt
            $this->logMessage([
                'tenant_id' => $this->tenantId,
                'to' => $to,
                'from' => $from ?? 'whatsapp:' . $this->whatsappFrom,
                'body' => $body,
                'type' => 'whatsapp',
                'status' => 'failed',
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
                'sent_at' => now(),
            ]);

            // Log to api_logs
            $this->logApiCall(
                'whatsapp_send',
                ['to' => $to, 'from' => $from ?? 'whatsapp:' . $this->whatsappFrom, 'body_length' => strlen($body)],
                false,
                null,
                (string) $e->getCode(),
                $e->getMessage(),
                $durationMs
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            Log::error('Unexpected error sending WhatsApp', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            // Log to api_logs
            $this->logApiCall(
                'whatsapp_send',
                ['to' => $to, 'body_length' => strlen($body)],
                false,
                null,
                'EXCEPTION',
                $e->getMessage(),
                $durationMs
            );

            return [
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send OTP code via preferred channel
     */
    public function sendOtp(string $to, string $code, string $channel = 'sms'): array
    {
        $body = "Tu código de verificación es: {$code}\n\nEste código expirará en 10 minutos.";

        if ($channel === 'whatsapp') {
            return $this->sendWhatsApp($to, $body);
        }

        return $this->sendSms($to, $body);
    }

    /**
     * Format phone number to E.164 format
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);

        // If it's a Mexican number without country code, add +52
        if (strlen($phone) === 10) {
            $phone = '+52' . $phone;
        } elseif (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Log message to database (both sms_logs and api_logs)
     */
    protected function logMessage(array $data): void
    {
        try {
            SmsLog::create($data);
        } catch (\Exception $e) {
            Log::error('Failed to log SMS/WhatsApp message', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Log API call to api_logs table.
     */
    protected function logApiCall(
        string $service,
        array $requestPayload,
        bool $success,
        ?array $responseData = null,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?int $durationMs = null
    ): void {
        try {
            // Mask sensitive data in payload
            $maskedPayload = ApiLog::maskSensitiveData($requestPayload);
            $maskedResponse = $responseData ? ApiLog::maskSensitiveData($responseData) : null;

            ApiLog::withoutGlobalScopes()->create([
                'tenant_id' => $this->tenantId,
                'applicant_id' => $this->applicantId,
                'application_id' => $this->applicationId,
                'user_id' => $this->userId,
                'provider' => ApiLog::PROVIDER_TWILIO,
                'service' => $service,
                'endpoint' => 'https://api.twilio.com/2010-04-01/Accounts/' . ($this->config?->account_sid ?? 'global') . '/Messages.json',
                'method' => 'POST',
                'request_headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'request_payload' => $maskedPayload,
                'response_status' => $success ? 201 : 400,
                'response_body' => $maskedResponse,
                'success' => $success,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'duration_ms' => $durationMs,
                'metadata' => [
                    'sandbox' => $this->config?->is_sandbox ?? false,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log Twilio API call', [
                'error' => $e->getMessage(),
                'service' => $service,
            ]);
        }
    }

    /**
     * Get message status from Twilio
     */
    public function getMessageStatus(string $sid): ?array
    {
        try {
            $message = $this->client->messages($sid)->fetch();

            return [
                'sid' => $message->sid,
                'status' => $message->status,
                'to' => $message->to,
                'from' => $message->from,
                'body' => $message->body,
                'date_sent' => $message->dateSent?->format('Y-m-d H:i:s'),
                'date_updated' => $message->dateUpdated?->format('Y-m-d H:i:s'),
                'price' => $message->price,
                'price_unit' => $message->priceUnit,
                'error_code' => $message->errorCode,
                'error_message' => $message->errorMessage,
            ];
        } catch (TwilioException $e) {
            Log::error('Failed to fetch message status', [
                'sid' => $sid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update message status in database
     */
    public function updateMessageStatus(string $sid): bool
    {
        $status = $this->getMessageStatus($sid);

        if (!$status) {
            return false;
        }

        $log = SmsLog::where('sid', $sid)->first();

        if (!$log) {
            return false;
        }

        $log->update([
            'status' => $status['status'],
            'price' => $status['price'],
            'price_unit' => $status['price_unit'],
            'error_code' => $status['error_code'],
            'error_message' => $status['error_message'],
        ]);

        return true;
    }

    /**
     * Test connection to Twilio by fetching account info
     */
    public function testConnection(): array
    {
        try {
            $accountSid = $this->config?->account_sid ?? config('services.twilio.account_sid');

            Log::info('Testing Twilio connection', [
                'tenant_id' => $this->tenantId,
                'account_sid_preview' => $accountSid ? substr($accountSid, 0, 6) . '...' . substr($accountSid, -4) : 'null',
            ]);

            // Fetch account info to verify credentials
            $account = $this->client->api->v2010->accounts($accountSid)->fetch();

            // Update test result in config
            if ($this->config) {
                $this->config->update([
                    'last_tested_at' => now(),
                    'last_test_success' => true,
                    'last_test_error' => null,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Conexión exitosa',
                'account_name' => $account->friendlyName,
                'account_status' => $account->status,
            ];
        } catch (TwilioException $e) {
            Log::error('Twilio connection test failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            // Provide better error messages
            $errorMessage = $e->getMessage();
            $userMessage = 'Error de autenticación';

            if (str_contains($errorMessage, '401') || str_contains($errorMessage, 'Authenticate')) {
                $userMessage = 'Credenciales inválidas';
                $errorMessage = 'El Account SID o Auth Token no son válidos. Verifique en console.twilio.com que las credenciales sean correctas y que la cuenta esté activa.';
            }

            // Update test result in config
            if ($this->config) {
                $this->config->update([
                    'last_tested_at' => now(),
                    'last_test_success' => false,
                    'last_test_error' => $errorMessage,
                ]);
            }

            return [
                'success' => false,
                'message' => $userMessage,
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('Twilio connection test failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            if ($this->config) {
                $this->config->update([
                    'last_tested_at' => now(),
                    'last_test_success' => false,
                    'last_test_error' => $e->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'message' => 'Error de conexión',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create service instance for testing without throwing on missing config
     */
    public static function createForTest(string $tenantId): ?self
    {
        try {
            return new self($tenantId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if the service is properly configured.
     *
     * @implements SmsServiceInterface
     */
    public function isConfigured(): bool
    {
        return $this->client !== null && $this->fromNumber !== null;
    }

    /**
     * Check if SMS capability is available.
     *
     * @implements SmsServiceInterface
     */
    public function hasSmsCapability(): bool
    {
        return $this->isConfigured() && !empty($this->fromNumber);
    }

    /**
     * Check if WhatsApp capability is available.
     *
     * @implements SmsServiceInterface
     */
    public function hasWhatsAppCapability(): bool
    {
        return $this->isConfigured() && !empty($this->whatsappFrom);
    }
}
