<?php

namespace App\Jobs;

use App\Enums\NotificationChannel;
use App\Models\NotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\TenantApiConfig;
use App\Services\ExternalApi\SmtpService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;

/**
 * Job to send a queued notification.
 *
 * Handles actual delivery via SMS, WhatsApp, Email, or In-App channels.
 * Implements retry logic with exponential backoff.
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of retry attempts.
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public NotificationLog $log
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get channel enum
            $channel = NotificationChannel::from($this->log->channel);

            // Send via appropriate channel
            $result = match ($channel) {
                NotificationChannel::SMS => $this->sendSms(),
                NotificationChannel::WHATSAPP => $this->sendWhatsApp(),
                NotificationChannel::EMAIL => $this->sendEmail(),
                NotificationChannel::IN_APP => $this->sendInApp(),
            };

            if ($result['success']) {
                $this->log->markAsSent($result['external_id'] ?? null);
                Log::info('Notification sent', [
                    'log_id' => $this->log->id,
                    'channel' => $channel->value,
                ]);
            } else {
                throw new \Exception($result['error'] ?? 'Unknown error');
            }
        } catch (\Exception $e) {
            $this->log->incrementRetryCount();
            $this->log->markAsFailed($e->getMessage());

            Log::error('Notification failed', [
                'log_id' => $this->log->id,
                'channel' => $this->log->channel,
                'error' => $e->getMessage(),
                'retry_count' => $this->log->retry_count,
            ]);

            // Re-throw to trigger Laravel's retry mechanism
            throw $e;
        }
    }

    /**
     * Send SMS via Twilio.
     */
    protected function sendSms(): array
    {
        try {
            $tenant = $this->log->tenant;
            $settings = $tenant->settings ?? [];

            // Check if Twilio is configured
            if (! isset($settings['twilio_sid']) || ! isset($settings['twilio_token'])) {
                return ['success' => false, 'error' => 'Twilio not configured'];
            }

            $twilio = new TwilioClient(
                $settings['twilio_sid'],
                $settings['twilio_token']
            );

            $message = $twilio->messages->create(
                $this->log->recipient,
                [
                    'from' => $settings['twilio_phone'],
                    'body' => $this->log->body,
                ]
            );

            return [
                'success' => true,
                'external_id' => $message->sid,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send WhatsApp via Twilio.
     */
    protected function sendWhatsApp(): array
    {
        try {
            $tenant = $this->log->tenant;
            $settings = $tenant->settings ?? [];

            // Check if Twilio WhatsApp is configured
            if (! isset($settings['twilio_sid']) || ! isset($settings['twilio_token'])) {
                return ['success' => false, 'error' => 'Twilio not configured'];
            }

            $twilio = new TwilioClient(
                $settings['twilio_sid'],
                $settings['twilio_token']
            );

            // Format phone numbers for WhatsApp
            $from = 'whatsapp:'.$settings['twilio_whatsapp_phone'];
            $to = 'whatsapp:'.$this->log->recipient;

            $message = $twilio->messages->create(
                $to,
                [
                    'from' => $from,
                    'body' => $this->log->body,
                ]
            );

            return [
                'success' => true,
                'external_id' => $message->sid,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send Email via configured provider.
     *
     * Priority: TenantApiConfig SMTP integration > tenant settings > default SMTP.
     */
    protected function sendEmail(): array
    {
        try {
            $tenant = $this->log->tenant;

            // Check for SMTP integration in TenantApiConfig first
            $smtpConfig = TenantApiConfig::where('tenant_id', $tenant->id)
                ->where('provider', 'smtp')
                ->where('service_type', 'email')
                ->where('is_active', true)
                ->first();

            if ($smtpConfig && $smtpConfig->hasCredentials()) {
                return $this->sendEmailViaSmtpIntegration($smtpConfig);
            }

            // Fallback to tenant settings
            $settings = $tenant->settings ?? [];
            $provider = $settings['email_provider'] ?? 'sendgrid';

            return match ($provider) {
                'sendgrid' => $this->sendEmailViaSendGrid(),
                'mailgun' => $this->sendEmailViaMailgun(),
                default => $this->sendEmailViaSmtp(),
            };
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send email via tenant SMTP integration (TenantApiConfig).
     */
    protected function sendEmailViaSmtpIntegration(TenantApiConfig $config): array
    {
        try {
            $smtpService = SmtpService::createFromConfig($config);

            return $smtpService->sendEmail(
                $this->log->recipient,
                $this->log->subject,
                $this->log->body,
                $this->log->html_body
            );
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send email via SendGrid.
     */
    protected function sendEmailViaSendGrid(): array
    {
        $tenant = $this->log->tenant;
        $settings = $tenant->settings ?? [];

        if (! isset($settings['sendgrid_api_key'])) {
            return ['success' => false, 'error' => 'SendGrid not configured'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$settings['sendgrid_api_key'],
            'Content-Type' => 'application/json',
        ])->post('https://api.sendgrid.com/v3/mail/send', [
            'personalizations' => [
                [
                    'to' => [['email' => $this->log->recipient]],
                    'subject' => $this->log->subject,
                ],
            ],
            'from' => [
                'email' => $settings['from_email'] ?? $tenant->email,
                'name' => $settings['from_name'] ?? $tenant->name,
            ],
            'content' => [
                [
                    'type' => $this->log->html_body ? 'text/html' : 'text/plain',
                    'value' => $this->log->html_body ?? $this->log->body,
                ],
            ],
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'external_id' => $response->header('X-Message-Id'),
            ];
        }

        return ['success' => false, 'error' => $response->body()];
    }

    /**
     * Send email via Mailgun.
     */
    protected function sendEmailViaMailgun(): array
    {
        $tenant = $this->log->tenant;
        $settings = $tenant->settings ?? [];

        if (! isset($settings['mailgun_api_key']) || ! isset($settings['mailgun_domain'])) {
            return ['success' => false, 'error' => 'Mailgun not configured'];
        }

        $response = Http::withBasicAuth('api', $settings['mailgun_api_key'])
            ->asForm()
            ->post("https://api.mailgun.net/v3/{$settings['mailgun_domain']}/messages", [
                'from' => ($settings['from_name'] ?? $tenant->name).' <'.($settings['from_email'] ?? $tenant->email).'>',
                'to' => $this->log->recipient,
                'subject' => $this->log->subject,
                'text' => $this->log->body,
                'html' => $this->log->html_body,
            ]);

        if ($response->successful()) {
            $data = $response->json();

            return [
                'success' => true,
                'external_id' => $data['id'] ?? null,
            ];
        }

        return ['success' => false, 'error' => $response->body()];
    }

    /**
     * Send email via SMTP (Laravel Mail).
     */
    protected function sendEmailViaSmtp(): array
    {
        try {
            \Mail::raw($this->log->body, function ($message) {
                $message->to($this->log->recipient)
                    ->subject($this->log->subject);

                if ($this->log->html_body) {
                    $message->html($this->log->html_body);
                }
            });

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send in-app notification.
     */
    protected function sendInApp(): array
    {
        try {
            // In-app notifications are already created in NotificationLog
            // Just mark as sent
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Calculate exponential backoff delay.
     */
    public function backoff(): int
    {
        return $this->log->retry_count * 60; // 60, 120, 180 seconds
    }
}
