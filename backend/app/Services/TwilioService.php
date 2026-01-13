<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\TenantApiConfig;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

class TwilioService
{
    protected ?Client $client = null;
    protected ?string $fromNumber = null;
    protected ?string $whatsappFrom = null;
    protected ?int $tenantId = null;
    protected ?TenantApiConfig $config = null;

    public function __construct(?int $tenantId = null)
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
    protected function loadTenantConfig(int $tenantId): void
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
        $this->client = new Client(
            $this->config->account_sid,
            $this->config->auth_token
        );

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

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error sending SMS', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

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

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error sending WhatsApp', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

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
     * Log message to database
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
}
