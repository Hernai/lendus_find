/**
 * Cliente HTTP de LendusFind.
 *
 * Uso típico desde servicios:
 *   import { api } from '@/http'
 *   const res = await api.get<V2ApiResponse<Foo>>('/v2/applicant/foo')
 *
 * Los interceptores (auth, tenant, platform headers, manejo de 401) se
 * registran una sola vez al primer import gracias a `registerInterceptors()`.
 */

import type { AxiosRequestConfig, AxiosResponse } from 'axios'
import { apiClient } from './client'
import { registerInterceptors } from './interceptors'

registerInterceptors()

export const api = {
  get: <T>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> =>
    apiClient.get<T>(url, config),

  post: <T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> =>
    apiClient.post<T>(url, data, config),

  put: <T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> =>
    apiClient.put<T>(url, data, config),

  patch: <T>(url: string, data?: unknown, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> =>
    apiClient.patch<T>(url, data, config),

  delete: <T>(url: string, config?: AxiosRequestConfig): Promise<AxiosResponse<T>> =>
    apiClient.delete<T>(url, config),
}

export { apiClient }
export { API_BASE } from './client'
