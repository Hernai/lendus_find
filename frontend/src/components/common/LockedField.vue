<script setup lang="ts">
import type { VerifiedField } from '@/stores/kyc'

interface Props {
  /** Display label for the field */
  label: string
  /** The value to display */
  value: string | number | null | undefined
  /** Optional hint text below the field */
  hint?: string
  /** Show as verified with checkmark */
  verified?: boolean
  /** Verification details from KYC store */
  verification?: VerifiedField | null
  /** Format the value (e.g., 'date', 'curp', 'phone') */
  format?: 'none' | 'date' | 'curp' | 'phone' | 'uppercase' | 'rfc'
  /** Placeholder when value is empty */
  placeholder?: string
  /** Compact mode (smaller padding) */
  compact?: boolean
  /** Show method badge */
  showMethod?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  verified: true,
  format: 'none',
  placeholder: '-',
  compact: false,
  showMethod: true
})

/**
 * Format the value based on the format prop
 */
const formatValue = (val: string | number | null | undefined): string => {
  if (val === null || val === undefined || val === '') {
    return props.placeholder
  }

  const strVal = String(val)

  switch (props.format) {
    case 'date':
      try {
        // Try to parse different date formats from Nubarium OCR
        // Formats: DD/MM/YYYY, YYYY-MM-DD, DD-MM-YYYY
        let date: Date | null = null

        // Check if it's already a valid ISO date
        if (/^\d{4}-\d{2}-\d{2}/.test(strVal)) {
          date = new Date(strVal)
        }
        // Check DD/MM/YYYY or DD-MM-YYYY format (common in Mexican documents)
        else if (/^\d{2}[/-]\d{2}[/-]\d{4}$/.test(strVal)) {
          const parts = strVal.split(/[/-]/)
          date = new Date(parseInt(parts[2]), parseInt(parts[1]) - 1, parseInt(parts[0]))
        }
        // Check YYYY/MM/DD format
        else if (/^\d{4}[/-]\d{2}[/-]\d{2}$/.test(strVal)) {
          date = new Date(strVal.replace(/\//g, '-'))
        }

        if (date && !isNaN(date.getTime())) {
          return date.toLocaleDateString('es-MX', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
          })
        }
        return strVal
      } catch {
        return strVal
      }

    case 'curp':
      // Show CURP as-is (uppercase, no spaces) - already cleaned by store
      return strVal.replace(/\s+/g, '').toUpperCase()

    case 'rfc':
      // Show RFC as-is (uppercase)
      return strVal.toUpperCase()

    case 'phone':
      // Format Mexican phone: XX XXXX XXXX
      const digits = strVal.replace(/\D/g, '')
      if (digits.length === 10) {
        return `${digits.slice(0, 2)} ${digits.slice(2, 6)} ${digits.slice(6)}`
      }
      return strVal

    case 'uppercase':
      return strVal.toUpperCase()

    default:
      return strVal
  }
}

/**
 * Get the method badge color based on verification method
 */
const getMethodColor = (method: string): string => {
  if (method.startsWith('KYC_')) {
    return 'bg-green-100 text-green-700'
  }
  switch (method) {
    case 'NUBARIUM':
      return 'bg-blue-100 text-blue-700'
    case 'OTP':
      return 'bg-purple-100 text-purple-700'
    case 'MANUAL':
      return 'bg-gray-100 text-gray-700'
    case 'API':
      return 'bg-indigo-100 text-indigo-700'
    case 'BUREAU':
      return 'bg-amber-100 text-amber-700'
    default:
      return 'bg-gray-100 text-gray-700'
  }
}

/**
 * Format method label for display
 */
const formatMethodLabel = (method: string, label?: string): string => {
  if (label) return label

  // Map method codes to short labels
  const methodLabels: Record<string, string> = {
    'KYC_INE_OCR': 'INE OCR',
    'KYC_INE_LIST': 'Lista Nominal',
    'KYC_CURP_RENAPO': 'RENAPO',
    'KYC_RFC_SAT': 'SAT',
    'KYC_FACE_MATCH': 'Biométrico',
    'KYC_LIVENESS': 'Prueba de vida',
    'KYC_OFAC': 'OFAC',
    'KYC_PLD': 'PLD',
    'NUBARIUM': 'Nubarium',
    'OTP': 'OTP',
    'MANUAL': 'Manual',
    'API': 'API',
    'BUREAU': 'Buró'
  }

  return methodLabels[method] || method
}
</script>

<template>
  <div class="w-full">
    <!-- Label with lock icon -->
    <label class="flex items-center gap-1.5 text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">
      <svg class="w-3.5 h-3.5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
        <path
          fill-rule="evenodd"
          d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
          clip-rule="evenodd"
        />
      </svg>
      {{ label }}
    </label>

    <!-- Value display box -->
    <div
      class="bg-gray-50 border-2 rounded-xl flex items-center justify-between"
      :class="[
        compact ? 'px-3 py-2' : 'px-4 py-3',
        verified && value ? 'border-green-200 bg-green-50/50' : 'border-gray-200'
      ]"
    >
      <span
        :class="[
          'font-medium',
          value ? 'text-gray-900' : 'text-gray-400'
        ]"
      >
        {{ formatValue(value) }}
      </span>

      <!-- Lock or verified icon -->
      <div class="flex items-center gap-2">
        <!-- Verification method badge -->
        <span
          v-if="showMethod && verification?.method && verified && value"
          :class="[
            'text-xs px-2 py-0.5 rounded-full font-medium',
            getMethodColor(verification.method)
          ]"
        >
          {{ formatMethodLabel(verification.method, verification.method_label) }}
        </span>

        <!-- Verified checkmark -->
        <svg
          v-if="verified && value"
          class="w-5 h-5 text-green-500 flex-shrink-0"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd"
          />
        </svg>

        <!-- Lock icon (only show if not showing verified checkmark) -->
        <svg
          v-else
          class="w-4 h-4 text-gray-400 flex-shrink-0"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
    </div>

    <!-- Hint text or verification timestamp -->
    <div class="mt-1 flex items-center justify-between">
      <p v-if="hint" class="text-xs text-gray-500">
        {{ hint }}
      </p>
      <p
        v-if="verification?.verified_at && verified && value"
        class="text-xs text-gray-400 ml-auto"
      >
        Verificado {{ new Date(verification.verified_at).toLocaleDateString('es-MX') }}
      </p>
    </div>
  </div>
</template>
