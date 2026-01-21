/**
 * Composable for handling Mexican phone number input with formatting.
 *
 * Provides reactive formatting for phone inputs, ensuring consistent
 * display (XX XXXX XXXX) while storing only digits.
 *
 * @example
 * ```vue
 * <script setup lang="ts">
 * import { usePhoneInput } from '@/composables'
 *
 * const phone = ref('')
 * const { displayValue, onInput, onBlur, rawValue } = usePhoneInput(phone)
 * </script>
 *
 * <template>
 *   <input
 *     :value="displayValue"
 *     @input="onInput"
 *     @blur="onBlur"
 *     type="tel"
 *     maxlength="14"
 *     inputmode="numeric"
 *   />
 * </template>
 * ```
 */

import { computed, ref, watch, type Ref } from 'vue'

/**
 * Format a phone number for display (XX XXXX XXXX).
 */
function formatForDisplay(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 10)
  if (digits.length >= 6) {
    return `${digits.slice(0, 2)} ${digits.slice(2, 6)} ${digits.slice(6)}`
  }
  if (digits.length >= 2) {
    return `${digits.slice(0, 2)} ${digits.slice(2)}`
  }
  return digits
}

/**
 * Extract only digits from a value.
 */
function extractDigits(value: string): string {
  return value.replace(/\D/g, '').slice(0, 10)
}

export interface UsePhoneInputOptions {
  /** Format to store: 'digits' (default) or 'formatted' */
  storeFormat?: 'digits' | 'formatted'
}

export interface UsePhoneInputReturn {
  /** The formatted display value */
  displayValue: Ref<string>
  /** The raw digits only */
  rawValue: Ref<string>
  /** Handle input event */
  onInput: (event: Event) => void
  /** Handle blur event (final formatting) */
  onBlur: () => void
  /** Check if phone is valid (10 digits) */
  isValid: Ref<boolean>
  /** Check if phone is empty */
  isEmpty: Ref<boolean>
}

/**
 * Composable for Mexican phone number input with formatting.
 *
 * @param modelValue - Reactive ref to bind to (will be updated with digits only)
 * @param options - Configuration options
 */
export function usePhoneInput(
  modelValue: Ref<string | null | undefined>,
  options: UsePhoneInputOptions = {}
): UsePhoneInputReturn {
  const { storeFormat = 'digits' } = options

  // Internal display value
  const displayValue = ref('')

  // Computed raw value (digits only)
  const rawValue = computed(() => extractDigits(displayValue.value))

  // Validity check
  const isValid = computed(() => rawValue.value.length === 10)
  const isEmpty = computed(() => rawValue.value.length === 0)

  // Initialize display value from model
  const initializeFromModel = () => {
    const initial = modelValue.value || ''
    displayValue.value = formatForDisplay(initial)
  }

  // Watch for external changes to modelValue
  watch(
    () => modelValue.value,
    (newVal) => {
      const newDigits = extractDigits(newVal || '')
      const currentDigits = rawValue.value
      // Only update if digits actually changed (avoid formatting loops)
      if (newDigits !== currentDigits) {
        displayValue.value = formatForDisplay(newVal || '')
      }
    },
    { immediate: true }
  )

  // Handle input event
  const onInput = (event: Event) => {
    const target = event.target as HTMLInputElement
    const cursorPosition = target.selectionStart || 0
    const oldValue = displayValue.value
    const newValue = target.value

    // Get the digits and format them
    const digits = extractDigits(newValue)
    const formatted = formatForDisplay(digits)

    // Update display
    displayValue.value = formatted

    // Update model value
    modelValue.value = storeFormat === 'digits' ? digits : formatted

    // Adjust cursor position after formatting
    // This is a simplified approach - could be enhanced for better UX
    requestAnimationFrame(() => {
      if (target === document.activeElement) {
        // Calculate new cursor position based on digit count change
        const oldDigits = extractDigits(oldValue)
        const addedDigits = digits.length - oldDigits.length

        if (addedDigits > 0) {
          // User added digits, move cursor forward accounting for spaces
          const newPosition = Math.min(formatted.length, cursorPosition + addedDigits)
          target.setSelectionRange(newPosition, newPosition)
        } else if (addedDigits < 0) {
          // User deleted digits
          const newPosition = Math.max(0, cursorPosition + addedDigits)
          target.setSelectionRange(newPosition, newPosition)
        }
      }
    })
  }

  // Handle blur event - ensure final formatting
  const onBlur = () => {
    displayValue.value = formatForDisplay(rawValue.value)
  }

  // Initialize on mount
  initializeFromModel()

  return {
    displayValue,
    rawValue,
    onInput,
    onBlur,
    isValid,
    isEmpty,
  }
}

/**
 * Simple phone formatting directive-like function.
 * Use when you don't need full composable functionality.
 *
 * @param value - The value to format
 * @returns Formatted phone string
 */
export function formatPhoneValue(value: string | null | undefined): string {
  return formatForDisplay(value || '')
}

/**
 * Strip formatting from a phone value.
 *
 * @param value - The formatted or unformatted value
 * @returns Digits only (max 10)
 */
export function stripPhoneFormatting(value: string | null | undefined): string {
  return extractDigits(value || '')
}
