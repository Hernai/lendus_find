/**
 * Conditional logger utility.
 *
 * Only logs in development mode to prevent sensitive data exposure
 * and reduce performance overhead in production.
 *
 * @example
 * ```typescript
 * import { logger } from '@/utils/logger'
 *
 * logger.debug('User data', { id: user.id })
 * logger.info('Application submitted')
 * logger.warn('Rate limit approaching')
 * logger.error('Failed to load', error)
 * ```
 */

const isDev = import.meta.env.DEV

/**
 * Safe stringify for logging objects.
 * Handles circular references and limits depth.
 */
function safeStringify(obj: unknown, maxDepth = 3): string {
  const seen = new WeakSet()

  const stringify = (value: unknown, depth: number): unknown => {
    if (depth > maxDepth) return '[Max depth reached]'

    if (value === null) return null
    if (value === undefined) return undefined

    if (typeof value === 'function') return '[Function]'

    if (typeof value === 'object') {
      if (seen.has(value as object)) return '[Circular]'
      seen.add(value as object)

      if (Array.isArray(value)) {
        return value.map((item) => stringify(item, depth + 1))
      }

      const result: Record<string, unknown> = {}
      for (const key of Object.keys(value as object)) {
        result[key] = stringify((value as Record<string, unknown>)[key], depth + 1)
      }
      return result
    }

    return value
  }

  try {
    return JSON.stringify(stringify(obj, 0), null, 2)
  } catch {
    return String(obj)
  }
}

/**
 * Sanitize sensitive data from logs.
 */
function sanitize(data: unknown): unknown {
  if (!data || typeof data !== 'object') return data

  const sensitiveKeys = [
    'password',
    'token',
    'secret',
    'auth_token',
    'api_key',
    'curp',
    'rfc',
    'clabe',
    'card_number',
    'cvv',
    'pin',
  ]

  const sanitized: Record<string, unknown> = {}
  for (const [key, value] of Object.entries(data as Record<string, unknown>)) {
    if (sensitiveKeys.some((sk) => key.toLowerCase().includes(sk))) {
      sanitized[key] = '[REDACTED]'
    } else if (typeof value === 'object' && value !== null) {
      sanitized[key] = sanitize(value)
    } else {
      sanitized[key] = value
    }
  }
  return sanitized
}

type LogLevel = 'debug' | 'info' | 'warn' | 'error'

interface LoggerOptions {
  prefix?: string
  sanitizeData?: boolean
}

function createLogger(options: LoggerOptions = {}) {
  const { prefix = '[LendusFind]', sanitizeData = true } = options

  const log = (level: LogLevel, message: string, data?: unknown): void => {
    if (!isDev) return

    const timestamp = new Date().toISOString().split('T')[1]?.slice(0, 12) ?? ''
    const formattedPrefix = `${timestamp} ${prefix}`

    const processedData = sanitizeData && data ? sanitize(data) : data

    switch (level) {
      case 'debug':
        if (processedData !== undefined) {
          console.debug(`${formattedPrefix} ${message}`, processedData)
        } else {
          console.debug(`${formattedPrefix} ${message}`)
        }
        break
      case 'info':
        if (processedData !== undefined) {
          console.info(`${formattedPrefix} ${message}`, processedData)
        } else {
          console.info(`${formattedPrefix} ${message}`)
        }
        break
      case 'warn':
        if (processedData !== undefined) {
          console.warn(`${formattedPrefix} ${message}`, processedData)
        } else {
          console.warn(`${formattedPrefix} ${message}`)
        }
        break
      case 'error':
        if (processedData !== undefined) {
          console.error(`${formattedPrefix} ${message}`, processedData)
        } else {
          console.error(`${formattedPrefix} ${message}`)
        }
        break
    }
  }

  return {
    debug: (message: string, data?: unknown) => log('debug', message, data),
    info: (message: string, data?: unknown) => log('info', message, data),
    warn: (message: string, data?: unknown) => log('warn', message, data),
    error: (message: string, data?: unknown) => log('error', message, data),

    /**
     * Create a child logger with a specific prefix.
     */
    child: (childPrefix: string) =>
      createLogger({
        ...options,
        prefix: `${prefix}[${childPrefix}]`,
      }),

    /**
     * Log only in development, with raw output (no sanitization).
     */
    raw: (message: string, data?: unknown) => {
      if (!isDev) return
      console.log(`${prefix} ${message}`, data)
    },

    /**
     * Time a function execution.
     */
    time: async <T>(label: string, fn: () => Promise<T>): Promise<T> => {
      if (!isDev) return fn()

      const start = performance.now()
      try {
        const result = await fn()
        const duration = (performance.now() - start).toFixed(2)
        console.debug(`${prefix} ${label} completed in ${duration}ms`)
        return result
      } catch (error) {
        const duration = (performance.now() - start).toFixed(2)
        console.error(`${prefix} ${label} failed after ${duration}ms`, error)
        throw error
      }
    },
  }
}

/**
 * Default logger instance.
 */
export const logger = createLogger()

/**
 * Create a scoped logger for a specific module.
 */
export const createScopedLogger = (scope: string) => logger.child(scope)

export { safeStringify, sanitize }
