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
export { useKycBiometrics, type BiometricTokenData, type FaceMatchResult, type LivenessResult } from './useKycBiometrics'
export { useKycCompliance, type ComplianceMatch, type ComplianceResult } from './useKycCompliance'

// Document types (from backend enum)
export { useDocumentTypes } from './useDocumentTypes'

// Toast notifications
export { useToast, useToastState, type Toast, type ToastType, type ToastOptions } from './useToast'

// Modal management
export { useModal, useConfirmModal, type UseModalOptions, type UseModalReturn, type UseConfirmModalReturn } from './useModal'

// Input formatting
export {
  usePhoneInput,
  formatPhoneValue,
  stripPhoneFormatting,
  PHONE_INPUT_CONFIG,
  PHONE_MAX_DIGITS,
  PHONE_MAX_LENGTH_FORMATTED,
  type UsePhoneInputOptions,
  type UsePhoneInputReturn,
} from './usePhoneInput'
