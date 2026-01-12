<script setup lang="ts">
interface Props {
  /** Display label for the field */
  label: string
  /** The value to display */
  value: string | number | null | undefined
  /** Optional hint text below the field */
  hint?: string
  /** Show as verified with checkmark */
  verified?: boolean
  /** Format the value (e.g., 'date', 'curp', 'phone') */
  format?: 'none' | 'date' | 'curp' | 'phone' | 'uppercase'
  /** Placeholder when value is empty */
  placeholder?: string
}

const props = withDefaults(defineProps<Props>(), {
  verified: true,
  format: 'none',
  placeholder: '-'
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
      class="bg-gray-50 border-2 border-gray-200 rounded-xl px-4 py-3 flex items-center justify-between"
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
        <!-- Verified checkmark -->
        <svg
          v-if="verified && value"
          class="w-5 h-5 text-green-500"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fill-rule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clip-rule="evenodd"
          />
        </svg>

        <!-- Lock icon -->
        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
          <path
            fill-rule="evenodd"
            d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
            clip-rule="evenodd"
          />
        </svg>
      </div>
    </div>

    <!-- Hint text -->
    <p v-if="hint" class="mt-1 text-xs text-gray-500">
      {{ hint }}
    </p>
  </div>
</template>
