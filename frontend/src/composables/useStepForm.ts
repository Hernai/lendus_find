import { reactive, ref, watch, type UnwrapRef } from 'vue'
import { useRouter } from 'vue-router'
import { useOnboardingStore } from '@/stores'
import { getErrorMessage } from '@/types/api'

/**
 * Validation rule for a form field.
 * Using `any` for flexibility with mixed field types in forms.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export interface ValidationRule<T = any> {
  /** Validation function - returns true if valid */
  validate: (value: T, form: Record<string, unknown>) => boolean
  /** Error message to show when validation fails */
  message: string
  /** Optional: only run this rule when condition is met */
  when?: (form: Record<string, unknown>) => boolean
}

/**
 * Field configuration for step form.
 * Using `any` for flexibility with mixed field types in forms.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export interface FieldConfig<T = any> {
  /** Default value for the field */
  default: T
  /** Validation rules for the field */
  rules?: ValidationRule<T>[]
  /** Transform value before saving to store */
  transform?: (value: T) => T
}

/**
 * Options for useStepForm composable.
 */
export interface StepFormOptions<T extends Record<string, FieldConfig>> {
  /** Step number (1-8) */
  step: number
  /** Field configurations */
  fields: T
  /** Next step route */
  nextRoute: string
  /** Previous step route */
  prevRoute: string
  /** Custom validation function (runs after field validations) */
  customValidate?: (form: FormValues<T>) => string | null
  /** Called before saving to store */
  beforeSave?: (form: FormValues<T>) => FormValues<T> | void
  /** Called after successful save */
  afterSave?: () => void
}

/**
 * Extract form values type from field configs.
 */
type FormValues<T extends Record<string, FieldConfig>> = {
  [K in keyof T]: T[K]['default']
}

/**
 * Extract errors type from field configs.
 */
type FormErrors<T extends Record<string, FieldConfig>> = {
  [K in keyof T]: string
}

/**
 * Composable for standardized onboarding step forms.
 *
 * Eliminates repeated patterns across Step1-Step8 components:
 * - Reactive form state
 * - Error tracking
 * - Validation with rules
 * - Store sync (load on mount, save on change)
 * - Navigation
 *
 * @example
 * ```typescript
 * const { form, errors, submitError, validate, handleSubmit, prevStep, isSaving } = useStepForm({
 *   step: 4,
 *   fields: {
 *     employment_type: { default: '', rules: [{ validate: v => !!v, message: 'Requerido' }] },
 *     company_name: { default: '', rules: [...], transform: v => v.toUpperCase() },
 *     monthly_income: { default: 0, rules: [{ validate: v => v >= 1000, message: 'Mínimo $1,000' }] },
 *   },
 *   nextRoute: '/solicitud/paso-5',
 *   prevRoute: '/solicitud/paso-3',
 * })
 * ```
 */
export function useStepForm<T extends Record<string, FieldConfig>>(options: StepFormOptions<T>) {
  const router = useRouter()
  const onboardingStore = useOnboardingStore()

  // Build initial form values from field configs
  const initialValues = Object.entries(options.fields).reduce((acc, [key, config]) => {
    acc[key] = config.default
    return acc
  }, {} as Record<string, unknown>) as FormValues<T>

  // Build initial errors (all empty strings)
  const initialErrors = Object.keys(options.fields).reduce((acc, key) => {
    acc[key] = ''
    return acc
  }, {} as Record<string, string>) as FormErrors<T>

  const form = reactive(initialValues) as UnwrapRef<FormValues<T>>
  const errors = reactive(initialErrors) as UnwrapRef<FormErrors<T>>
  const submitError = ref('')
  const isSaving = ref(false)
  const isInitialized = ref(false)

  /**
   * Load form data from store.
   */
  const loadFromStore = () => {
    const stepKey = `step${options.step}` as keyof typeof onboardingStore.data
    const stepData = onboardingStore.data[stepKey] as Record<string, unknown>

    if (stepData) {
      Object.keys(options.fields).forEach((key) => {
        const value = stepData[key]
        if (value !== undefined && value !== null) {
          ;(form as Record<string, unknown>)[key] = value
        }
      })
    }
  }

  /**
   * Save form data to store.
   */
  const saveToStore = () => {
    if (!isInitialized.value) return

    const stepKey = `step${options.step}` as 'step1' | 'step2' | 'step3' | 'step4' | 'step5' | 'step6' | 'step7'
    const data: Record<string, unknown> = {}

    // Copy form values
    Object.keys(options.fields).forEach((key) => {
      data[key] = (form as Record<string, unknown>)[key]
    })

    // Apply transforms
    Object.entries(options.fields).forEach(([key, config]) => {
      if (config.transform) {
        data[key] = config.transform(data[key])
      }
    })

    onboardingStore.updateStepData(stepKey, data)
  }

  /**
   * Initialize form - call in onMounted.
   */
  const init = async () => {
    await onboardingStore.init()
    loadFromStore()
    isInitialized.value = true
  }

  /**
   * Validate all fields.
   */
  const validate = (): boolean => {
    let isValid = true

    // Clear all errors first
    Object.keys(options.fields).forEach((key) => {
      ;(errors as Record<string, string>)[key] = ''
    })

    // Validate each field
    Object.entries(options.fields).forEach(([key, config]) => {
      const fieldConfig = config as FieldConfig
      const value = (form as Record<string, unknown>)[key]

      if (fieldConfig.rules) {
        for (const rule of fieldConfig.rules) {
          // Check if rule should run
          if (rule.when && !rule.when(form as Record<string, unknown>)) {
            continue
          }

          if (!rule.validate(value as never, form as Record<string, unknown>)) {
            ;(errors as Record<string, string>)[key] = rule.message
            isValid = false
            break // Stop at first failed rule for this field
          }
        }
      }
    })

    // Run custom validation
    if (options.customValidate) {
      const customError = options.customValidate(form as FormValues<T>)
      if (customError) {
        submitError.value = customError
        isValid = false
      }
    }

    return isValid
  }

  /**
   * Handle form submission.
   */
  const handleSubmit = async (): Promise<boolean> => {
    if (!validate()) return false

    isSaving.value = true
    submitError.value = ''

    try {
      // Build data to save
      const dataToSave: Record<string, unknown> = {}
      Object.keys(options.fields).forEach((key) => {
        dataToSave[key] = (form as Record<string, unknown>)[key]
      })

      // Apply beforeSave transform if provided
      if (options.beforeSave) {
        const transformed = options.beforeSave(dataToSave as FormValues<T>)
        if (transformed) {
          Object.assign(dataToSave, transformed)
        }
      }

      // Apply field transforms
      Object.entries(options.fields).forEach(([key, config]) => {
        if (config.transform) {
          dataToSave[key] = config.transform(dataToSave[key])
        }
      })

      // Save to store
      const stepKey = `step${options.step}` as 'step1' | 'step2' | 'step3' | 'step4' | 'step5' | 'step6' | 'step7'
      onboardingStore.updateStepData(stepKey, dataToSave as Record<string, unknown>)

      // Complete the step
      await onboardingStore.completeStep(options.step)

      // Call afterSave callback
      options.afterSave?.()

      // Navigate to next step
      router.push(options.nextRoute)
      return true
    } catch (e: unknown) {
      submitError.value = getErrorMessage(e, 'Error al guardar')
      return false
    } finally {
      isSaving.value = false
    }
  }

  /**
   * Navigate to previous step.
   */
  const prevStep = () => {
    router.push(options.prevRoute)
  }

  /**
   * Clear a specific field error.
   */
  const clearError = (field: keyof T) => {
    ;(errors as Record<string, string>)[field as string] = ''
  }

  /**
   * Clear all errors.
   */
  const clearAllErrors = () => {
    Object.keys(options.fields).forEach((key) => {
      ;(errors as Record<string, string>)[key] = ''
    })
    submitError.value = ''
  }

  // Auto-save to store when form changes (after initialization)
  watch(
    () => form,
    () => {
      saveToStore()
    },
    { deep: true }
  )

  return {
    form,
    errors,
    submitError,
    isSaving,
    isInitialized,
    init,
    validate,
    handleSubmit,
    prevStep,
    clearError,
    clearAllErrors,
    loadFromStore,
    saveToStore,
  }
}

/**
 * Common validation rules factory.
 * All rules return ValidationRule (with any as default type) for flexibility.
 */
export const rules = {
  required: (message = 'Este campo es requerido'): ValidationRule => ({
    validate: (v) => v !== '' && v !== null && v !== undefined,
    message,
  }),

  minLength: (min: number, message?: string): ValidationRule => ({
    validate: (v) => typeof v === 'string' && v.length >= min,
    message: message || `Mínimo ${min} caracteres`,
  }),

  maxLength: (max: number, message?: string): ValidationRule => ({
    validate: (v) => typeof v === 'string' && v.length <= max,
    message: message || `Máximo ${max} caracteres`,
  }),

  min: (min: number, message?: string): ValidationRule => ({
    validate: (v) => typeof v === 'number' && v >= min,
    message: message || `Mínimo ${min.toLocaleString('es-MX')}`,
  }),

  max: (max: number, message?: string): ValidationRule => ({
    validate: (v) => typeof v === 'number' && v <= max,
    message: message || `Máximo ${max.toLocaleString('es-MX')}`,
  }),

  pattern: (regex: RegExp, message: string): ValidationRule => ({
    validate: (v) => typeof v === 'string' && regex.test(v),
    message,
  }),

  email: (message = 'Email inválido'): ValidationRule => ({
    validate: (v) => typeof v === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v),
    message,
  }),

  phone: (message = 'Teléfono debe tener 10 dígitos'): ValidationRule => ({
    validate: (v) => typeof v === 'string' && v.replace(/\D/g, '').length === 10,
    message,
  }),

  postalCode: (message = 'Código postal debe tener 5 dígitos'): ValidationRule => ({
    validate: (v) => typeof v === 'string' && /^\d{5}$/.test(v),
    message,
  }),

  curp: (message = 'CURP inválido'): ValidationRule => ({
    validate: (v) => typeof v === 'string' && /^[A-Z]{4}[0-9]{6}[HM][A-Z]{2}[A-Z]{3}[A-Z0-9][0-9]$/i.test(v),
    message,
  }),

  rfc: (message = 'RFC inválido'): ValidationRule => ({
    validate: (v) => typeof v === 'string' && /^[A-Z]{3,4}[0-9]{6}[A-Z0-9]{3}$/i.test(v),
    message,
  }),

  /** Conditional rule - only validates when condition is true */
  when: (
    condition: (form: Record<string, unknown>) => boolean,
    rule: ValidationRule
  ): ValidationRule => ({
    ...rule,
    when: condition,
  }),
}
