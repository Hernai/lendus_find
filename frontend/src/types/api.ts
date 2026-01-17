// API Types - Common API Response Types

export interface ApiResponse<T = unknown> {
  success: boolean
  data?: T
  error?: string
  message?: string
}

// Axios error response type for catch blocks
export interface AxiosErrorResponse {
  response?: {
    data?: {
      message?: string
      error?: string
      errors?: Record<string, string[]>
    }
    status?: number
  }
  message?: string
}

// Helper function to extract error message from unknown error
export function getErrorMessage(error: unknown, defaultMessage = 'Error desconocido'): string {
  const axiosError = error as AxiosErrorResponse
  return axiosError.response?.data?.message ||
         axiosError.response?.data?.error ||
         axiosError.message ||
         defaultMessage
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    from: number
    last_page: number
    per_page: number
    to: number
    total: number
  }
}

export interface ApiError {
  success: false
  error: string
  message: string
  errors?: Record<string, string[]>
}

export interface PostalCodeResult {
  postal_code: string
  neighborhoods: string[]
  municipality: string
  city: string
  state: string
}
