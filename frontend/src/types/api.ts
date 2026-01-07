// API Types - Common API Response Types

export interface ApiResponse<T = any> {
  success: boolean
  data?: T
  error?: string
  message?: string
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
