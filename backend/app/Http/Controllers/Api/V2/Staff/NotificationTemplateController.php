<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\TenantApiConfig;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Staff controller for managing notification templates.
 *
 * All endpoints are under /api/v2/staff/notification-templates
 */
class NotificationTemplateController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected TemplateRenderer $renderer
    ) {}

    /**
     * Get all notification templates for the tenant.
     *
     * GET /v2/staff/notification-templates
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        $query = NotificationTemplate::where('tenant_id', $tenant->id);

        // Filter by event
        if ($request->has('event')) {
            $query->forEvent($request->event);
        }

        // Filter by channel
        if ($request->has('channel')) {
            $query->forChannel($request->channel);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $templates = $query->orderBy('event')
            ->orderBy('channel')
            ->orderBy('priority')
            ->get()
            ->map(fn ($t) => $this->formatTemplate($t));

        return $this->success([
            'templates' => $templates,
        ]);
    }

    /**
     * Get a single notification template.
     *
     * GET /v2/staff/notification-templates/{id}
     */
    public function show(string $id): JsonResponse
    {
        $tenant = app('tenant');

        $template = NotificationTemplate::where('tenant_id', $tenant->id)
            ->findOrFail($id);

        return $this->success([
            'template' => $this->formatTemplate($template),
        ]);
    }

    /**
     * Create a new notification template.
     *
     * POST /v2/staff/notification-templates
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'event' => 'required|string|in:'.implode(',', array_column(NotificationEvent::cases(), 'value')),
            'channel' => 'required|string|in:'.implode(',', array_column(NotificationChannel::cases(), 'value')),
            'is_active' => 'boolean',
            'priority' => 'integer|min:1|max:10',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'html_body' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Error de validación', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();

        // Validate template syntax
        $validation = $this->renderer->validate($data['body']);
        if (! $validation['valid']) {
            return $this->error('INVALID_TEMPLATE', 'Invalid template syntax: '.$validation['error'], 422);
        }

        // Check if subject is required for EMAIL channel
        $channel = NotificationChannel::from($data['channel']);
        if ($channel === NotificationChannel::EMAIL && empty($data['subject'])) {
            return $this->error('MISSING_SUBJECT', 'Subject is required for EMAIL channel', 422);
        }

        // Get available variables for this event
        $event = NotificationEvent::from($data['event']);
        $data['available_variables'] = $event->getAvailableVariables();

        $data['tenant_id'] = $tenant->id;
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;

        $template = NotificationTemplate::create($data);

        return $this->success([
            'template' => $this->formatTemplate($template),
        ], 'Template created successfully', 201);
    }

    /**
     * Update a notification template.
     *
     * PUT /v2/staff/notification-templates/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = app('tenant');
        $user = $request->user();

        $template = NotificationTemplate::where('tenant_id', $tenant->id)
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'event' => 'sometimes|required|string|in:'.implode(',', array_column(NotificationEvent::cases(), 'value')),
            'channel' => 'sometimes|required|string|in:'.implode(',', array_column(NotificationChannel::cases(), 'value')),
            'is_active' => 'sometimes|boolean',
            'priority' => 'sometimes|integer|min:1|max:10',
            'subject' => 'nullable|string|max:255',
            'body' => 'sometimes|required|string',
            'html_body' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Error de validación', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();

        // Validate template syntax if body is being updated
        if (isset($data['body'])) {
            $validation = $this->renderer->validate($data['body']);
            if (! $validation['valid']) {
                return $this->error('INVALID_TEMPLATE', 'Invalid template syntax: '.$validation['error'], 422);
            }
        }

        // Check if subject is required for EMAIL channel
        if (isset($data['channel'])) {
            $channel = NotificationChannel::from($data['channel']);
            if ($channel === NotificationChannel::EMAIL && empty($data['subject']) && empty($template->subject)) {
                return $this->error('MISSING_SUBJECT', 'Subject is required for EMAIL channel', 422);
            }
        }

        $data['updated_by'] = $user->id;

        $template->update($data);

        return $this->success([
            'template' => $this->formatTemplate($template->fresh()),
        ], 'Template updated successfully');
    }

    /**
     * Delete a notification template.
     *
     * DELETE /v2/staff/notification-templates/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $tenant = app('tenant');

        $template = NotificationTemplate::where('tenant_id', $tenant->id)
            ->findOrFail($id);

        $template->delete();

        return $this->success([], 'Template deleted successfully');
    }

    /**
     * Test render a template with sample data.
     *
     * POST /v2/staff/notification-templates/test-render
     */
    public function testRender(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'body' => 'required|string',
            'variables' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Error de validación', 422, $validator->errors()->toArray());
        }

        $validation = $this->renderer->validate($request->body);
        if (! $validation['valid']) {
            return $this->error('INVALID_TEMPLATE', 'Invalid template syntax: '.$validation['error'], 422);
        }

        $rendered = $this->renderer->render($request->body, $request->variables);
        $extractedVars = $this->renderer->extractVariables($request->body);

        return $this->success([
            'rendered' => $rendered,
            'extracted_variables' => $extractedVars,
        ]);
    }

    /**
     * Get available events and channels with their details.
     *
     * GET /v2/staff/notification-templates/config
     */
    public function config(): JsonResponse
    {
        $events = collect(NotificationEvent::cases())->map(fn ($event) => [
            'value' => $event->value,
            'label' => $event->label(),
            'available_variables' => $event->getAvailableVariables(),
            'recommended_channels' => array_map(fn ($ch) => $ch->value, $event->getRecommendedChannels()),
            'enabled_by_default' => $event->isEnabledByDefault(),
        ]);

        $channels = collect(NotificationChannel::cases())->map(fn ($channel) => [
            'value' => $channel->value,
            'label' => $channel->label(),
            'supports_html' => $channel->supportsHtml(),
            'requires_subject' => $channel->requiresSubject(),
            'character_limit' => $channel->characterLimit(),
        ]);

        return $this->success([
            'events' => $events,
            'channels' => $channels,
        ]);
    }

    /**
     * Send a test notification using a template.
     *
     * POST /v2/staff/notification-templates/{id}/send-test
     */
    public function sendTest(Request $request, string $id): JsonResponse
    {
        $tenant = app('tenant');

        $template = NotificationTemplate::where('tenant_id', $tenant->id)
            ->findOrFail($id);

        $channel = $template->channel;

        // IN_APP cannot be sent as external test
        if ($channel === NotificationChannel::IN_APP) {
            return $this->error('CHANNEL_NOT_SUPPORTED', 'Las notificaciones internas no se pueden enviar como prueba externa.', 422);
        }

        // Validate recipient based on channel
        $recipientRules = match ($channel) {
            NotificationChannel::EMAIL => 'required|email',
            NotificationChannel::SMS, NotificationChannel::WHATSAPP => 'required|string|regex:/^\d{10}$/',
            default => 'required|string',
        };

        $validator = Validator::make($request->all(), [
            'recipient' => $recipientRules,
            'variables' => 'nullable|array',
        ], [
            'recipient.required' => 'El destinatario es requerido.',
            'recipient.email' => 'Ingresa un correo electrónico válido.',
            'recipient.regex' => 'Ingresa un número de teléfono válido (10 dígitos).',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Error de validación', 422, $validator->errors()->toArray());
        }

        $recipient = $request->input('recipient');

        // Normalize phone to E.164 for SMS/WhatsApp
        if (in_array($channel, [NotificationChannel::SMS, NotificationChannel::WHATSAPP])) {
            $recipient = '+52' . $recipient;
        }

        // Verify provider is configured for the channel
        $providerError = $this->checkProviderConfigured($tenant, $channel);
        if ($providerError) {
            return $this->error('PROVIDER_NOT_CONFIGURED', $providerError, 422);
        }

        // Render template with variables
        $variables = $request->input('variables', []);
        $renderedBody = $this->renderer->render($template->body, $variables);
        $renderedSubject = $template->subject
            ? $this->renderer->render($template->subject, $variables)
            : null;
        $renderedHtmlBody = $template->html_body
            ? $this->renderer->render($template->html_body, $variables)
            : null;

        // Create notification log with test flag
        $log = NotificationLog::create([
            'tenant_id' => $tenant->id,
            'notification_template_id' => $template->id,
            'channel' => $channel->value,
            'event' => $template->event->value,
            'recipient' => $recipient,
            'subject' => $renderedSubject,
            'body' => $renderedBody,
            'html_body' => $renderedHtmlBody,
            'status' => NotificationLog::STATUS_PENDING,
            'metadata' => [
                'is_test' => true,
                'sent_by' => $request->user()->id,
            ],
        ]);

        // Send synchronously for immediate feedback
        try {
            SendNotificationJob::dispatchSync($log);

            $log->refresh();

            if ($log->status === NotificationLog::STATUS_FAILED) {
                return $this->error('SEND_FAILED', $log->error_message ?? 'Error al enviar la notificación de prueba.', 422);
            }

            return $this->success([
                'log_id' => $log->id,
                'status' => $log->status,
            ], 'Notificación de prueba enviada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Test notification failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            $log->refresh();

            return $this->error('SEND_FAILED', $log->error_message ?? $e->getMessage(), 500);
        }
    }

    /**
     * Check if the tenant has a provider configured for the given channel.
     */
    protected function checkProviderConfigured($tenant, NotificationChannel $channel): ?string
    {
        $settings = $tenant->settings ?? [];

        return match ($channel) {
            NotificationChannel::EMAIL => $this->checkEmailProviderConfigured($tenant, $settings),
            NotificationChannel::SMS, NotificationChannel::WHATSAPP => isset($settings['twilio_sid']) && isset($settings['twilio_token'])
                ? null
                : 'No hay proveedor de SMS/WhatsApp (Twilio) configurado. Configura uno en Integraciones.',
            default => null,
        };
    }

    /**
     * Check if email provider is configured.
     */
    protected function checkEmailProviderConfigured($tenant, array $settings): ?string
    {
        // Check SMTP integration first
        $smtpConfig = TenantApiConfig::where('tenant_id', $tenant->id)
            ->where('provider', 'smtp')
            ->where('service_type', 'email')
            ->where('is_active', true)
            ->first();

        if ($smtpConfig && $smtpConfig->hasCredentials()) {
            return null;
        }

        // Check tenant settings for email providers
        $provider = $settings['email_provider'] ?? null;

        if ($provider === 'sendgrid' && isset($settings['sendgrid_api_key'])) {
            return null;
        }
        if ($provider === 'mailgun' && isset($settings['mailgun_api_key']) && isset($settings['mailgun_domain'])) {
            return null;
        }

        // Check default SMTP (Laravel Mail) - always available as fallback
        return null;
    }

    /**
     * Format template for API response.
     */
    protected function formatTemplate(NotificationTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'event' => $template->event?->value,
            'event_label' => $template->event?->label(),
            'channel' => $template->channel?->value,
            'channel_label' => $template->channel?->label(),
            'is_active' => $template->is_active,
            'priority' => $template->priority,
            'subject' => $template->subject,
            'body' => $template->body,
            'html_body' => $template->html_body,
            'available_variables' => $template->available_variables ?? $template->getDefaultAvailableVariables(),
            'metadata' => $template->metadata,
            'created_by' => $template->creator ? [
                'id' => $template->creator->id,
                'name' => $template->creator->name,
                'email' => $template->creator->email,
            ] : null,
            'updated_by' => $template->updater ? [
                'id' => $template->updater->id,
                'name' => $template->updater->name,
                'email' => $template->updater->email,
            ] : null,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}
