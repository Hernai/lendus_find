import axios, { type AxiosInstance, type AxiosRequestConfig, type AxiosResponse } from 'axios'

// API base URL
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'

// Base URL without /api for CSRF cookie
const BASE_URL = API_BASE_URL.replace('/api', '')

// Tenant header (can be configured per deployment)
const TENANT_HEADER = import.meta.env.VITE_TENANT_ID || 'demo'

// Helper to get cookie value
const getCookie = (name: string): string | null => {
  const match = document.cookie.match(new RegExp('(^|;\\s*)(' + name + ')=([^;]*)'))
  return match && match[3] ? decodeURIComponent(match[3]) : null
}

// Create axios instance
const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Tenant-ID': TENANT_HEADER,
  },
  timeout: 30000,
  withCredentials: true, // Required for Sanctum CSRF cookies
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

// CSRF cookie initialization
let csrfInitialized = false
let csrfInitializing: Promise<void> | null = null

// Initialize CSRF cookie for Sanctum
export const initCsrf = async (): Promise<void> => {
  if (csrfInitialized) return

  // If already initializing, wait for it
  if (csrfInitializing) {
    return csrfInitializing
  }

  csrfInitializing = (async () => {
    try {
      await axios.get(`${BASE_URL}/sanctum/csrf-cookie`, {
        withCredentials: true,
        headers: {
          'X-Tenant-ID': TENANT_HEADER,
        },
      })
      csrfInitialized = true
    } catch (error) {
      console.warn('Failed to initialize CSRF cookie:', error)
    } finally {
      csrfInitializing = null
    }
  })()

  return csrfInitializing
}

// Request interceptor - add auth token, handle FormData, and ensure CSRF
apiClient.interceptors.request.use(
  async (config) => {
    // Ensure CSRF cookie is set before POST/PUT/PATCH/DELETE requests
    if (config.method && ['post', 'put', 'patch', 'delete'].includes(config.method.toLowerCase())) {
      await initCsrf()

      // Manually add XSRF token header (axios xsrf handling doesn't work cross-origin)
      const xsrfToken = getCookie('XSRF-TOKEN')
      if (xsrfToken && config.headers) {
        config.headers['X-XSRF-TOKEN'] = xsrfToken
      }
    }

    const token = localStorage.getItem('auth_token')
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }

    // If the data is FormData, remove Content-Type so browser sets it with boundary
    if (config.data instanceof FormData) {
      delete config.headers['Content-Type']
    }

    return config
  },
  (error) => Promise.reject(error)
)

// Response interceptor - handle errors
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response) {
      const { status } = error.response

      // Handle 401 Unauthorized - redirect to login
      if (status === 401) {
        localStorage.removeItem('auth_token')
        window.location.href = '/auth'
      }

      // Handle 403 Forbidden
      if (status === 403) {
        console.error('Access denied')
      }

      // Handle validation errors
      if (status === 422) {
        return Promise.reject(error.response.data)
      }
    }

    return Promise.reject(error)
  }
)

// Generic API methods
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

// Export the axios instance for direct use if needed
export default apiClient
