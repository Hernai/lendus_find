/**
 * V2 Staff Tenant Service
 *
 * Handles tenant management for super admin users.
 * All endpoints are under /api/v2/staff/tenants
 */

import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'

const BASE_PATH = '/v2/staff/tenants'

// =====================================================
// Types
// =====================================================

export interface V2Tenant {
  id: string
  name: string
  slug: string
  legal_name: string | null
  rfc: string | null
  email: string | null
  phone: string | null
  website: string | null
  is_active: boolean
  branding: {
    primary_color: string
    logo_url: string | null
  }
  users_count?: number
  applications_count?: number
  created_at: string
  activated_at: string | null
  suspended_at: string | null
}

export interface V2TenantDetailed extends Omit<V2Tenant, 'branding'> {
  branding: {
    primary_color?: string
    secondary_color?: string
    accent_color?: string
    logo_url?: string | null
    favicon_url?: string | null
    font_family?: string
    border_radius?: string
  } | null
  settings: Record<string, unknown> | null
  webhook_config: {
    url?: string
    secret_key?: string
    events?: string[]
  } | null
  updated_at: string
}

export interface V2TenantCreatePayload {
  name: string
  slug: string
  legal_name?: string | null
  rfc?: string | null
  email?: string | null
  phone?: string | null
  website?: string | null
  branding?: Record<string, unknown>
  settings?: Record<string, unknown>
  webhook_config?: Record<string, unknown> | null
  is_active?: boolean
}

export interface V2TenantUpdatePayload {
  name?: string
  slug?: string
  legal_name?: string | null
  rfc?: string | null
  email?: string | null
  phone?: string | null
  website?: string | null
  branding?: Record<string, unknown>
  settings?: Record<string, unknown>
  webhook_config?: Record<string, unknown> | null
  is_active?: boolean
}

export interface V2TenantFilters {
  search?: string
  active?: boolean
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  per_page?: number
  page?: number
}

export interface V2TenantStats {
  users_count: number
  staff_count: number
  applications_count: number
  applications_by_status: Record<string, number>
  products_count: number
  active_products_count: number
}

export interface V2TenantBranding {
  primary_color: string
  secondary_color: string
  accent_color: string
  background_color: string
  text_color: string
  logo_url: string | null
  logo_dark_url: string | null
  favicon_url: string | null
  login_background_url: string | null
  font_family: string
  heading_font_family: string | null
  border_radius: string
  button_style: string
  custom_css: string | null
}

export interface V2TenantApiConfig {
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
  last_tested_at: string | null
  last_test_success: boolean | null
}

export interface V2TenantConfig {
  tenant: {
    id: string
    name: string
    slug: string
    legal_name: string | null
    rfc: string | null
    email: string | null
    phone: string | null
    website: string | null
  }
  branding: V2TenantBranding
  api_configs: V2TenantApiConfig[]
  available_providers: Record<string, string>
  available_service_types: Record<string, string>
}

export interface V2TenantApiConfigPayload {
  provider: string
  service_type: string
  api_key?: string
  api_secret?: string
  account_sid?: string
  auth_token?: string
  from_number?: string | null
  from_email?: string | null
  domain?: string | null
  webhook_url?: string | null
  webhook_secret?: string | null
  extra_config?: Record<string, unknown>
  is_active?: boolean
  is_sandbox?: boolean
}

export interface V2TenantBrandingPayload {
  primary_color?: string
  secondary_color?: string
  accent_color?: string
  background_color?: string
  text_color?: string
  logo_url?: string | null
  logo_dark_url?: string | null
  favicon_url?: string | null
  login_background_url?: string | null
  font_family?: string
  heading_font_family?: string | null
  border_radius?: string
  button_style?: 'rounded' | 'pill' | 'square'
  custom_css?: string | null
}

// =====================================================
// Response Types
// =====================================================

export interface V2TenantListResponse {
  tenants: V2Tenant[]
  meta: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
}

// =====================================================
// API Functions
// =====================================================

/**
 * List tenants with filters and pagination.
 */
export async function list(filters?: V2TenantFilters): Promise<V2ApiResponse<V2TenantListResponse>> {
  const response = await api.get<V2ApiResponse<V2TenantListResponse>>(BASE_PATH, { params: filters })
  return response.data
}

/**
 * Get a single tenant by ID.
 */
export async function get(id: string): Promise<V2ApiResponse<{ tenant: V2TenantDetailed }>> {
  const response = await api.get<V2ApiResponse<{ tenant: V2TenantDetailed }>>(`${BASE_PATH}/${id}`)
  return response.data
}

/**
 * Create a new tenant.
 */
export async function create(payload: V2TenantCreatePayload): Promise<V2ApiResponse<{ tenant: V2TenantDetailed }>> {
  const response = await api.post<V2ApiResponse<{ tenant: V2TenantDetailed }>>(BASE_PATH, payload)
  return response.data
}

/**
 * Update a tenant.
 */
export async function update(id: string, payload: V2TenantUpdatePayload): Promise<V2ApiResponse<{ tenant: V2TenantDetailed }>> {
  const response = await api.put<V2ApiResponse<{ tenant: V2TenantDetailed }>>(`${BASE_PATH}/${id}`, payload)
  return response.data
}

/**
 * Delete a tenant.
 */
export async function destroy(id: string): Promise<V2ApiResponse<null>> {
  const response = await api.delete<V2ApiResponse<null>>(`${BASE_PATH}/${id}`)
  return response.data
}

/**
 * Get tenant statistics.
 */
export async function getStats(id: string): Promise<V2ApiResponse<V2TenantStats>> {
  const response = await api.get<V2ApiResponse<V2TenantStats>>(`${BASE_PATH}/${id}/stats`)
  return response.data
}

/**
 * Get full tenant configuration.
 */
export async function getConfig(id: string): Promise<V2ApiResponse<V2TenantConfig>> {
  const response = await api.get<V2ApiResponse<V2TenantConfig>>(`${BASE_PATH}/${id}/config`)
  return response.data
}

/**
 * Update tenant branding.
 */
export async function updateBranding(id: string, payload: V2TenantBrandingPayload): Promise<V2ApiResponse<{ branding: V2TenantBranding }>> {
  const response = await api.put<V2ApiResponse<{ branding: V2TenantBranding }>>(`${BASE_PATH}/${id}/branding`, payload)
  return response.data
}

/**
 * Upload a logo for a tenant.
 */
export async function uploadLogo(id: string, file: File, field: 'logo_url' | 'logo_dark_url' | 'favicon_url' | 'login_background_url'): Promise<V2ApiResponse<{ url: string; field: string }>> {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('field', field)

  const response = await api.post<V2ApiResponse<{ url: string; field: string }>>(`${BASE_PATH}/${id}/upload-logo`, formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })
  return response.data
}

/**
 * List API configurations for a tenant.
 */
export async function listApiConfigs(id: string): Promise<V2ApiResponse<{
  api_configs: V2TenantApiConfig[]
  available_providers: Record<string, string>
  available_service_types: Record<string, string>
}>> {
  const response = await api.get<V2ApiResponse<{
    api_configs: V2TenantApiConfig[]
    available_providers: Record<string, string>
    available_service_types: Record<string, string>
  }>>(`${BASE_PATH}/${id}/api-configs`)
  return response.data
}

/**
 * Create or update an API configuration for a tenant.
 */
export async function saveApiConfig(id: string, payload: V2TenantApiConfigPayload): Promise<V2ApiResponse<{ api_config: V2TenantApiConfig }>> {
  const response = await api.post<V2ApiResponse<{ api_config: V2TenantApiConfig }>>(`${BASE_PATH}/${id}/api-configs`, payload)
  return response.data
}

/**
 * Delete an API configuration.
 */
export async function deleteApiConfig(tenantId: string, configId: string): Promise<V2ApiResponse<null>> {
  const response = await api.delete<V2ApiResponse<null>>(`${BASE_PATH}/${tenantId}/api-configs/${configId}`)
  return response.data
}

/**
 * Test an API configuration.
 */
export async function testApiConfig(
  tenantId: string,
  configId: string,
  payload?: { test_phone?: string; test_email?: string }
): Promise<V2ApiResponse<{ api_config: V2TenantApiConfig }>> {
  const response = await api.post<V2ApiResponse<{ api_config: V2TenantApiConfig }>>(
    `${BASE_PATH}/${tenantId}/api-configs/${configId}/test`,
    payload
  )
  return response.data
}

export default {
  list,
  get,
  create,
  update,
  destroy,
  getStats,
  getConfig,
  updateBranding,
  uploadLogo,
  listApiConfigs,
  saveApiConfig,
  deleteApiConfig,
  testApiConfig,
}
