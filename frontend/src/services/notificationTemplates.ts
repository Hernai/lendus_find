import api from './api'

export interface NotificationTemplate {
  id: string
  name: string
  event: string
  event_label: string
  channel: string
  channel_label: string
  is_active: boolean
  priority: number
  subject: string | null
  body: string
  html_body: string | null
  available_variables: Record<string, string>
  metadata: Record<string, any> | null
  created_by: {
    id: string
    name: string
    email: string
  } | null
  updated_by: {
    id: string
    name: string
    email: string
  } | null
  created_at: string
  updated_at: string
}

export interface NotificationEvent {
  value: string
  label: string
  available_variables: Record<string, string>
  recommended_channels: string[]
  enabled_by_default: boolean
}

export interface NotificationChannel {
  value: string
  label: string
  supports_html: boolean
  requires_subject: boolean
  character_limit: number | null
}

export interface TemplateConfig {
  events: NotificationEvent[]
  channels: NotificationChannel[]
}

export interface CreateTemplateData {
  name: string
  event: string
  channel: string
  is_active?: boolean
  priority?: number
  subject?: string | null
  body: string
  html_body?: string | null
  metadata?: Record<string, any> | null
}

export interface TestRenderData {
  body: string
  variables: Record<string, any>
}

export interface TestRenderResponse {
  rendered: string
  extracted_variables: string[]
}

export const notificationTemplatesApi = {
  /**
   * Get all notification templates
   */
  async getAll(params?: { event?: string; channel?: string; is_active?: boolean }) {
    const response = await api.get<{ data: { templates: NotificationTemplate[] } }>(
      '/v2/staff/notification-templates',
      { params }
    )
    return response.data.data.templates
  },

  /**
   * Get a single notification template
   */
  async getById(id: string) {
    const response = await api.get<{ data: { template: NotificationTemplate } }>(
      `/v2/staff/notification-templates/${id}`
    )
    return response.data.data.template
  },

  /**
   * Create a new notification template
   */
  async create(data: CreateTemplateData) {
    const response = await api.post<{ data: { template: NotificationTemplate } }>(
      '/v2/staff/notification-templates',
      data
    )
    return response.data.data.template
  },

  /**
   * Update a notification template
   */
  async update(id: string, data: Partial<CreateTemplateData>) {
    const response = await api.put<{ data: { template: NotificationTemplate } }>(
      `/v2/staff/notification-templates/${id}`,
      data
    )
    return response.data.data.template
  },

  /**
   * Delete a notification template
   */
  async delete(id: string) {
    await api.delete(`/v2/staff/notification-templates/${id}`)
  },

  /**
   * Get configuration (events and channels)
   */
  async getConfig() {
    const response = await api.get<{ data: TemplateConfig }>('/v2/staff/notification-templates/config')
    return response.data.data
  },

  /**
   * Test render a template
   */
  async testRender(data: TestRenderData) {
    const response = await api.post<{ data: TestRenderResponse }>(
      '/v2/staff/notification-templates/test-render',
      data
    )
    return response.data.data
  },
}
