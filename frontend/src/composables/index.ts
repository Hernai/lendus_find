/**
 * Composables index - re-export all composables for convenient imports.
 *
 * @example
 * ```typescript
 * import { useStepForm, usePermissions, useErrorHandler } from '@/composables'
 * ```
 */

// Async utilities
export { useAsyncAction, useAsyncForm, type AsyncActionOptions, type AsyncActionResult } from './useAsyncAction'
export { useAsyncState, type AsyncState } from './useAsyncState'

// Form and validation
export { useStepForm, rules, type FieldConfig, type ValidationRule, type StepFormOptions } from './useStepForm'
export { useValidation, validation } from './useValidation'

// Error handling
export {
  useErrorHandler,
  parseError,
  translateError,
  translateFieldError,
  getErrorMessage,
  type ParsedError,
  type ValidationErrors,
  type ErrorHandlerOptions,
} from './useErrorHandler'

// Permissions (admin)
export { usePermissions, type PermissionName } from './usePermissions'

// WebSocket
export { useWebSocket } from './useWebSocket'

// Device capture
export { useDeviceCapture } from './useDeviceCapture'

// KYC
export { useKycValidation } from './useKycValidation'
