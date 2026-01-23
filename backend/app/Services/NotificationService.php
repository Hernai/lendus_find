<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Jobs\SendNotificationJob;
use App\Models\ApplicantAccount;
use App\Models\NotificationLog;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\StaffAccount;
use App\Models\Tenant;

/**
 * Notification service for sending multi-channel notifications.
 *
 * Handles template lookup, rendering, user preferences, and queueing.
 */
class NotificationService
{
    public function __construct(
        protected TemplateRenderer $renderer
    ) {}

    /**
     * Send notification for an event.
     *
     * @param  NotificationEvent|string  $event  Event that triggered the notification
     * @param  ApplicantAccount|StaffAccount|string  $recipient  Account object or phone/email
     * @param  array  $variables  Template variables
     * @param  array|null  $channels  Channels to send to (null = use all active)
     * @param  Tenant|null  $tenant  Tenant (defaults to current)
     * @return array Array of NotificationLog IDs created
     */
    public function send(
        NotificationEvent|string $event,
        ApplicantAccount|StaffAccount|string $recipient,
        array $variables = [],
        ?array $channels = null,
        ?Tenant $tenant = null
    ): array {
        // Get tenant
        $tenant = $tenant ?? app('tenant');

        // Convert event to enum
        if (is_string($event)) {
            $event = NotificationEvent::from($event);
        }

        // Determine channels to use
        if ($channels === null) {
            $channels = $event->getRecommendedChannels();
        }

        // Get account preferences if recipient is an account
        $account = is_object($recipient) ? $recipient : null;
        $preferences = $account ? $this->getAccountPreferences($account, $tenant) : null;

        $logIds = [];

        // Send to each channel
        foreach ($channels as $channel) {
            if (is_string($channel)) {
                $channel = NotificationChannel::from($channel);
            }

            // Check user preferences
            if ($preferences && ! $preferences->shouldReceive($event, $channel)) {
                continue;
            }

            // Find active template for this event and channel
            $template = NotificationTemplate::query()
                ->where('tenant_id', $tenant->id)
                ->forEvent($event)
                ->forChannel($channel)
                ->active()
                ->orderBy('priority', 'asc')
                ->first();

            if (! $template) {
                \Log::warning('No template found for notification', [
                    'event' => $event->value,
                    'channel' => $channel->value,
                    'tenant_id' => $tenant->id,
                ]);
                continue;
            }

            // Render template
            $renderedBody = $this->renderer->render($template->body, $variables);
            $renderedSubject = $template->subject
                ? $this->renderer->render($template->subject, $variables)
                : null;
            $renderedHtmlBody = $template->html_body
                ? $this->renderer->render($template->html_body, $variables)
                : null;

            // Determine recipient address
            $recipientAddress = $this->getRecipientAddress($recipient, $channel);

            if (! $recipientAddress) {
                \Log::warning('Could not determine recipient address', [
                    'channel' => $channel->value,
                    'account_id' => $account?->id,
                ]);
                continue;
            }

            // Determine recipient type
            [$recipientId, $recipientType] = $this->getRecipientIdentity($account);

            // Create notification log
            $log = NotificationLog::create([
                'tenant_id' => $tenant->id,
                'notification_template_id' => $template->id,
                'recipient_id' => $recipientId,
                'recipient_type' => $recipientType,
                'channel' => $channel->value,
                'event' => $event->value,
                'recipient' => $recipientAddress,
                'status' => NotificationLog::STATUS_PENDING,
                'subject' => $renderedSubject,
                'body' => $renderedBody,
                'html_body' => $renderedHtmlBody,
            ]);

            // Queue the notification
            $priority = $template->priority ?? 5;
            $queueName = $this->getQueueName($priority);

            SendNotificationJob::dispatch($log)
                ->onQueue($queueName)
                ->delay($this->getDelay($channel));

            $logIds[] = $log->id;
        }

        return $logIds;
    }

    /**
     * Get account notification preferences.
     */
    protected function getAccountPreferences(ApplicantAccount|StaffAccount $account, Tenant $tenant): NotificationPreference
    {
        [$recipientId, $recipientType] = $this->getRecipientIdentity($account);

        return NotificationPreference::firstOrCreate([
            'tenant_id' => $tenant->id,
            'recipient_id' => $recipientId,
            'recipient_type' => $recipientType,
        ], [
            'sms_enabled' => true,
            'whatsapp_enabled' => true,
            'email_enabled' => true,
            'in_app_enabled' => true,
        ]);
    }

    /**
     * Get recipient identity [id, type].
     */
    protected function getRecipientIdentity(?object $account): array
    {
        if (! $account) {
            return [null, null];
        }

        if ($account instanceof ApplicantAccount) {
            return [$account->id, 'APPLICANT'];
        }

        if ($account instanceof StaffAccount) {
            return [$account->id, 'STAFF'];
        }

        return [null, null];
    }

    /**
     * Get recipient address for a specific channel.
     */
    protected function getRecipientAddress(ApplicantAccount|StaffAccount|string $recipient, NotificationChannel $channel): ?string
    {
        if (is_string($recipient)) {
            return $recipient;
        }

        return match ($channel) {
            NotificationChannel::SMS, NotificationChannel::WHATSAPP => $recipient->phone,
            NotificationChannel::EMAIL => $recipient->email,
            NotificationChannel::IN_APP => $recipient->id,
            default => null,
        };
    }

    /**
     * Get queue name based on priority.
     */
    protected function getQueueName(int $priority): string
    {
        return match (true) {
            $priority <= 3 => 'notifications-high',
            $priority <= 7 => 'notifications-medium',
            default => 'notifications-low',
        };
    }

    /**
     * Get delay for channel (for rate limiting).
     */
    protected function getDelay(NotificationChannel $channel): int
    {
        return match ($channel) {
            NotificationChannel::SMS, NotificationChannel::WHATSAPP => 0, // No delay for urgent
            NotificationChannel::EMAIL => 5, // 5 second delay to batch
            NotificationChannel::IN_APP => 0, // No delay
            default => 0,
        };
    }

    /**
     * Send OTP notification (convenience method).
     */
    public function sendOtp(ApplicantAccount|StaffAccount $account, string $code, int $expiresInMinutes = 10): array
    {
        return $this->send(
            NotificationEvent::OTP_SENT,
            $account,
            [
                'user' => [
                    'first_name' => $account->first_name ?? '',
                    'last_name' => $account->last_name ?? '',
                    'phone' => $account->phone,
                    'email' => $account->email,
                ],
                'otp' => [
                    'code' => $code,
                    'expires_in' => $expiresInMinutes,
                ],
                'tenant' => $this->getTenantVariables(),
            ],
            [NotificationChannel::SMS, NotificationChannel::WHATSAPP]
        );
    }

    /**
     * Get tenant variables for templates.
     */
    protected function getTenantVariables(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? app('tenant');

        return [
            'name' => $tenant->name,
            'phone' => $tenant->phone,
            'email' => $tenant->email,
            'website' => $tenant->website,
        ];
    }
}
