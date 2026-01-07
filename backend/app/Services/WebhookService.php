<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Tenant;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookService
{
    /**
     * Event types.
     */
    public const EVENT_APPLICATION_CREATED = 'application.created';
    public const EVENT_APPLICATION_SUBMITTED = 'application.submitted';
    public const EVENT_APPLICATION_STATUS_CHANGED = 'application.status_changed';
    public const EVENT_APPLICATION_APPROVED = 'application.approved';
    public const EVENT_APPLICATION_REJECTED = 'application.rejected';
    public const EVENT_APPLICATION_DISBURSED = 'application.disbursed';
    public const EVENT_DOCUMENT_UPLOADED = 'document.uploaded';
    public const EVENT_DOCUMENT_APPROVED = 'document.approved';
    public const EVENT_DOCUMENT_REJECTED = 'document.rejected';

    /**
     * Dispatch a webhook event.
     */
    public function dispatch(string $event, array $payload, Tenant $tenant): void
    {
        $webhookUrls = $this->getWebhookUrls($tenant, $event);

        if (empty($webhookUrls)) {
            return;
        }

        $payload = array_merge($payload, [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'tenant_id' => $tenant->uuid,
        ]);

        foreach ($webhookUrls as $url) {
            $this->sendWebhook($url, $event, $payload, $tenant);
        }
    }

    /**
     * Send webhook to a specific URL.
     */
    protected function sendWebhook(string $url, string $event, array $payload, Tenant $tenant): void
    {
        $webhookId = Str::uuid()->toString();
        $signature = $this->generateSignature($payload, $tenant);

        $headers = [
            'Content-Type' => 'application/json',
            'X-Webhook-ID' => $webhookId,
            'X-Webhook-Event' => $event,
            'X-Webhook-Signature' => $signature,
            'X-Webhook-Timestamp' => now()->timestamp,
            'User-Agent' => 'LendusFind-Webhook/1.0',
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders($headers)
                ->post($url, $payload);

            $this->logWebhook(
                $tenant,
                $event,
                $url,
                $payload,
                $response->status(),
                $response->body(),
                $response->successful()
            );

            if (!$response->successful()) {
                Log::warning('Webhook delivery failed', [
                    'webhook_id' => $webhookId,
                    'url' => $url,
                    'event' => $event,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                // Queue for retry if needed
                $this->scheduleRetry($tenant, $event, $url, $payload);
            }
        } catch (\Exception $e) {
            $this->logWebhook(
                $tenant,
                $event,
                $url,
                $payload,
                0,
                $e->getMessage(),
                false
            );

            Log::error('Webhook delivery error', [
                'webhook_id' => $webhookId,
                'url' => $url,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            // Queue for retry
            $this->scheduleRetry($tenant, $event, $url, $payload);
        }
    }

    /**
     * Get webhook URLs for a tenant and event.
     */
    protected function getWebhookUrls(Tenant $tenant, string $event): array
    {
        $webhooks = $tenant->settings['webhooks'] ?? [];
        $urls = [];

        foreach ($webhooks as $webhook) {
            if (!($webhook['enabled'] ?? true)) {
                continue;
            }

            $events = $webhook['events'] ?? ['*'];

            if (in_array('*', $events) || in_array($event, $events)) {
                $urls[] = $webhook['url'];
            }
        }

        return $urls;
    }

    /**
     * Generate webhook signature.
     */
    protected function generateSignature(array $payload, Tenant $tenant): string
    {
        $secret = $tenant->settings['webhook_secret'] ?? config('app.key');
        $payloadString = json_encode($payload);

        return hash_hmac('sha256', $payloadString, $secret);
    }

    /**
     * Log webhook delivery.
     */
    protected function logWebhook(
        Tenant $tenant,
        string $event,
        string $url,
        array $payload,
        int $statusCode,
        string $response,
        bool $success
    ): void {
        try {
            WebhookLog::create([
                'tenant_id' => $tenant->id,
                'event' => $event,
                'url' => $url,
                'payload' => $payload,
                'status' => $success ? WebhookLog::STATUS_SENT : WebhookLog::STATUS_FAILED,
                'attempts' => 1,
                'max_attempts' => 3,
                'last_attempt_at' => now(),
                'response_code' => $statusCode,
                'response_body' => $response,
                'error_message' => $success ? null : $response,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log webhook', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Schedule webhook retry.
     */
    protected function scheduleRetry(Tenant $tenant, string $event, string $url, array $payload): void
    {
        // In a full implementation, this would dispatch a job to retry
        // For now, we just log that a retry should be scheduled
        Log::info('Webhook retry scheduled', [
            'tenant_id' => $tenant->uuid,
            'event' => $event,
            'url' => $url,
        ]);
    }

    /**
     * Build application payload.
     */
    public function buildApplicationPayload(Application $application): array
    {
        return [
            'application' => [
                'id' => $application->uuid,
                'folio' => $application->folio,
                'status' => $application->status,
                'applicant' => [
                    'id' => $application->applicant?->uuid,
                    'name' => $application->applicant?->full_name,
                    'curp' => $application->applicant?->curp,
                    'phone' => $application->applicant?->phone,
                    'email' => $application->applicant?->email,
                ],
                'product' => [
                    'id' => $application->product?->uuid,
                    'name' => $application->product?->name,
                    'type' => $application->product?->type,
                ],
                'loan' => [
                    'requested_amount' => (float) $application->requested_amount,
                    'approved_amount' => $application->approved_amount ? (float) $application->approved_amount : null,
                    'term_months' => $application->term_months,
                    'payment_frequency' => $application->payment_frequency,
                    'interest_rate' => (float) $application->interest_rate,
                    'monthly_payment' => (float) $application->monthly_payment,
                    'total_to_pay' => (float) $application->total_to_pay,
                ],
                'risk' => [
                    'score' => $application->risk_score,
                    'level' => $application->risk_level,
                ],
                'created_at' => $application->created_at->toIso8601String(),
                'updated_at' => $application->updated_at->toIso8601String(),
                'approved_at' => $application->approved_at?->toIso8601String(),
                'disbursed_at' => $application->disbursed_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * Dispatch application created event.
     */
    public function dispatchApplicationCreated(Application $application): void
    {
        $this->dispatch(
            self::EVENT_APPLICATION_CREATED,
            $this->buildApplicationPayload($application),
            $application->tenant
        );
    }

    /**
     * Dispatch application submitted event.
     */
    public function dispatchApplicationSubmitted(Application $application): void
    {
        $this->dispatch(
            self::EVENT_APPLICATION_SUBMITTED,
            $this->buildApplicationPayload($application),
            $application->tenant
        );
    }

    /**
     * Dispatch application status changed event.
     */
    public function dispatchApplicationStatusChanged(Application $application, string $oldStatus): void
    {
        $payload = $this->buildApplicationPayload($application);
        $payload['previous_status'] = $oldStatus;

        $this->dispatch(
            self::EVENT_APPLICATION_STATUS_CHANGED,
            $payload,
            $application->tenant
        );

        // Also dispatch specific events
        match ($application->status) {
            Application::STATUS_APPROVED => $this->dispatch(
                self::EVENT_APPLICATION_APPROVED,
                $payload,
                $application->tenant
            ),
            Application::STATUS_REJECTED => $this->dispatch(
                self::EVENT_APPLICATION_REJECTED,
                $payload,
                $application->tenant
            ),
            Application::STATUS_DISBURSED => $this->dispatch(
                self::EVENT_APPLICATION_DISBURSED,
                $payload,
                $application->tenant
            ),
            default => null,
        };
    }
}
