import api from './api'

export interface NotificationPreferences {
  sms_enabled: boolean
  whatsapp_enabled: boolean
  email_enabled: boolean
  in_app_enabled: boolean
  disabled_events: string[]
}

export interface UpdatePreferencesData {
  sms_enabled?: boolean
  whatsapp_enabled?: boolean
  email_enabled?: boolean
  in_app_enabled?: boolean
  disabled_events?: string[]
}

export const notificationPreferencesApi = {
  /**
   * Get current user's notification preferences (works for both applicant and staff)
   */
  async get(userType: 'applicant' | 'staff' = 'applicant') {
    const response = await api.get<{ preferences: NotificationPreferences }>(
      `/v2/${userType}/notification-preferences`
    )
    return response.data.preferences
  },

  /**
   * Update notification preferences
   */
  async update(data: UpdatePreferencesData, userType: 'applicant' | 'staff' = 'applicant') {
    const response = await api.put<{ preferences: NotificationPreferences; message: string }>(
      `/v2/${userType}/notification-preferences`,
      data
    )
    return response.data
  },

  /**
   * Disable a specific event type
   */
  async disableEvent(event: string, userType: 'applicant' | 'staff' = 'applicant') {
    const response = await api.post<{ preferences: { disabled_events: string[] }; message: string }>(
      `/v2/${userType}/notification-preferences/events/${event}/disable`
    )
    return response.data
  },

  /**
   * Enable a specific event type
   */
  async enableEvent(event: string, userType: 'applicant' | 'staff' = 'applicant') {
    const response = await api.post<{ preferences: { disabled_events: string[] }; message: string }>(
      `/v2/${userType}/notification-preferences/events/${event}/enable`
    )
    return response.data
  },
}
