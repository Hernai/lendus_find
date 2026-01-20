/**
 * V2 Staff Config Service
 *
 * Handles tenant configuration management for staff users.
 * All endpoints are under /api/v2/staff/config
 */

import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'

const BASE_PATH = '/v2/staff/config'

// =====================================================
// Types
// =====================================================

export interface V2TenantInfo {
  id: string
  name: string
  slug: string
  legal_name: string | null
  rfc: string | null
  email: string | null
  phone: string | null
  website: string | null
}

export interface V2Branding {
  primary_color: string | null
  secondary_color: string | null
  accent_color: string | null
  background_color: string | null
  text_color: string | null
  logo_url: string | null
  logo_dark_url: string | null
  favicon_url: string | null
  login_background_url: string | null
  font_family: string | null
  heading_font_family: string | null
  border_radius: string | null
  button_style: 'rounded' | 'pill' | 'square' | null
  custom_css: string | null
}

export interface V2ApiConfig {
  id: string
  provider: string
  provider_label: string
  service_type: string
  service_type_label: string
  from_number: string | null
  from_email: string | null
  domain: string | null
  is_active: boolean
  is_sandbox: boolean
  has_credentials: boolean
  masked_credentials: Record<string, string | null>
  last_tested_at: string | null
  last_test_success: boolean | null
  last_test_error: string | null
}

export interface V2ConfigResponse {
  tenant: V2TenantInfo
  branding: V2Branding
  api_configs: V2ApiConfig[]
  available_providers: Record<string, string>
  available_service_types: Record<string, string>
}

export interface V2TenantUpdatePayload {
  name?: string
  legal_name?: string | null
  rfc?: string | null
  email?: string | null
  phone?: string | null
  website?: string | null
}

export interface V2BrandingUpdatePayload {
  primary_color?: string | null
  secondary_color?: string | null
  accent_color?: string | null
  background_color?: string | null
  text_color?: string | null
  logo_url?: string | null
  logo_dark_url?: string | null
  favicon_url?: string | null
  login_background_url?: string | null
  font_family?: string | null
  heading_font_family?: string | null
  border_radius?: string | null
  button_style?: 'rounded' | 'pill' | 'square' | null
  custom_css?: string | null
}

export interface V2ApiConfigPayload {
  provider: string
  service_type: string
  api_key?: string
  api_secret?: string
  account_sid?: string
  auth_token?: string
  from_number?: string
  from_email?: string
  domain?: string
  webhook_url?: string
  webhook_secret?: string
  extra_config?: Record<string, unknown>
  is_active?: boolean
  is_sandbox?: boolean
}

// =====================================================
// API Functions
// =====================================================

/**
 * Get current tenant's configuration.
 */
export async function getConfig(): Promise<V2ApiResponse<V2ConfigResponse>> {
  const response = await api.get<V2ApiResponse<V2ConfigResponse>>(BASE_PATH)
  return response.data
}

/**
 * Update tenant basic info.
 */
export async function updateTenant(payload: V2TenantUpdatePayload): Promise<V2ApiResponse<{ tenant: V2TenantInfo }>> {
  const response = await api.put<V2ApiResponse<{ tenant: V2TenantInfo }>>(`${BASE_PATH}/tenant`, payload)
  return response.data
}

/**
 * Update tenant branding.
 */
export async function updateBranding(payload: V2BrandingUpdatePayload): Promise<V2ApiResponse<{ branding: V2Branding }>> {
  const response = await api.put<V2ApiResponse<{ branding: V2Branding }>>(`${BASE_PATH}/branding`, payload)
  return response.data
}

/**
 * List API configurations.
 */
export async function listApiConfigs(): Promise<V2ApiResponse<{
  api_configs: V2ApiConfig[]
  available_providers: Record<string, string>
  available_service_types: Record<string, string>
}>> {
  const response = await api.get<V2ApiResponse<{
    api_configs: V2ApiConfig[]
    available_providers: Record<string, string>
    available_service_types: Record<string, string>
  }>>(`${BASE_PATH}/api-configs`)
  return response.data
}

/**
 * Create or update an API configuration.
 */
export async function saveApiConfig(payload: V2ApiConfigPayload): Promise<V2ApiResponse<{ api_config: V2ApiConfig }>> {
  const response = await api.post<V2ApiResponse<{ api_config: V2ApiConfig }>>(`${BASE_PATH}/api-configs`, payload)
  return response.data
}

/**
 * Delete an API configuration.
 */
export async function deleteApiConfig(id: string): Promise<V2ApiResponse<null>> {
  const response = await api.delete<V2ApiResponse<null>>(`${BASE_PATH}/api-configs/${id}`)
  return response.data
}

/**
 * Test an API configuration.
 */
export async function testApiConfig(id: string): Promise<V2ApiResponse<{ api_config: V2ApiConfig }>> {
  const response = await api.post<V2ApiResponse<{ api_config: V2ApiConfig }>>(`${BASE_PATH}/api-configs/${id}/test`)
  return response.data
}

export default {
  getConfig,
  updateTenant,
  updateBranding,
  listApiConfigs,
  saveApiConfig,
  deleteApiConfig,
  testApiConfig,
}
