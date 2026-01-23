<?php

namespace App\Http\Controllers\Api\V2\Staff;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Http\Controllers\Api\V2\Traits\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $templates = $query->with(['creator.profile'])
            ->orderBy('event')
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
            ->with(['creator.profile', 'updater.profile'])
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
            return $this->error('Validation error', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();

        // Validate template syntax
        $validation = $this->renderer->validate($data['body']);
        if (! $validation['valid']) {
            return $this->error('Invalid template syntax: '.$validation['error'], 422);
        }

        // Check if subject is required for EMAIL channel
        $channel = NotificationChannel::from($data['channel']);
        if ($channel === NotificationChannel::EMAIL && empty($data['subject'])) {
            return $this->error('Subject is required for EMAIL channel', 422);
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
            return $this->error('Validation error', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();

        // Validate template syntax if body is being updated
        if (isset($data['body'])) {
            $validation = $this->renderer->validate($data['body']);
            if (! $validation['valid']) {
                return $this->error('Invalid template syntax: '.$validation['error'], 422);
            }
        }

        // Check if subject is required for EMAIL channel
        if (isset($data['channel'])) {
            $channel = NotificationChannel::from($data['channel']);
            if ($channel === NotificationChannel::EMAIL && empty($data['subject']) && empty($template->subject)) {
                return $this->error('Subject is required for EMAIL channel', 422);
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
            return $this->error('Validation error', 422, $validator->errors()->toArray());
        }

        $validation = $this->renderer->validate($request->body);
        if (! $validation['valid']) {
            return $this->error('Invalid template syntax: '.$validation['error'], 422);
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
