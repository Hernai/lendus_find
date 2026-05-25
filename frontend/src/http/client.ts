import axios, { type AxiosInstance } from 'axios'

const isDev = import.meta.env.DEV

const API_BASE_URL =
  import.meta.env.VITE_API_URL ||
  (isDev
    ? 'http://localhost:8000/api'
    : (() => {
        throw new Error('VITE_API_URL environment variable is required in production')
      })())

/**
 * Instancia Axios base.
 *
 * No incluye interceptores — esos se registran desde `interceptors.ts`.
 * Las apps deben importar `api` desde `@/http`, no esta instancia directamente.
 */
export const apiClient: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 30000,
})

export const API_BASE = API_BASE_URL
