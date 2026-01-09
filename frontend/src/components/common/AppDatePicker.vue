<script setup lang="ts">
import { computed, ref, onMounted, watch, nextTick } from 'vue'

interface Props {
  modelValue: string
  label?: string
  placeholder?: string
  error?: string
  hint?: string
  disabled?: boolean
  required?: boolean
  min?: string
  max?: string
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
  required: false
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const isMobile = ref(false)
const isOpen = ref(false)

// Wheel picker refs
const dayRef = ref<HTMLElement | null>(null)
const monthRef = ref<HTMLElement | null>(null)
const yearRef = ref<HTMLElement | null>(null)

// Current selection for wheel picker
const selectedDay = ref(1)
const selectedMonth = ref(0) // 0-indexed
const selectedYear = ref(1990)

// Parse initial value
const parseValue = (value: string) => {
  if (!value) {
    const now = new Date()
    selectedDay.value = now.getDate()
    selectedMonth.value = now.getMonth()
    selectedYear.value = now.getFullYear() - 30 // Default to 30 years ago
    return
  }
  const [year, month, day] = value.split('-').map(Number)
  if (year && month && day) {
    selectedYear.value = year
    selectedMonth.value = month - 1
    selectedDay.value = day
  }
}

// Watch for external changes
watch(() => props.modelValue, parseValue, { immediate: true })

// Detect mobile on mount
onMounted(() => {
  isMobile.value = window.matchMedia('(max-width: 640px)').matches
  const mediaQuery = window.matchMedia('(max-width: 640px)')
  mediaQuery.addEventListener('change', (e) => {
    isMobile.value = e.matches
  })
})

const months = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
]

const days = computed(() => {
  const daysInMonth = new Date(selectedYear.value, selectedMonth.value + 1, 0).getDate()
  return Array.from({ length: daysInMonth }, (_, i) => i + 1)
})

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  const minYear = props.min ? parseInt(props.min.split('-')[0] || '1920') : 1920
  const maxYear = props.max ? parseInt(props.max.split('-')[0] || String(currentYear)) : currentYear
  const result = []
  for (let y = maxYear; y >= minYear; y--) {
    result.push(y)
  }
  return result
})

const displayValue = computed(() => {
  if (!props.modelValue) return ''
  const [year, month, day] = props.modelValue.split('-').map(Number)
  if (!year || !month || !day) return ''
  return `${day} de ${months[month - 1]} de ${year}`
})

const inputClasses = computed(() => {
  const base = 'w-full px-4 py-3 border-2 rounded-xl transition-colors duration-200 focus:outline-none bg-white cursor-pointer text-left'
  const state = props.error
    ? 'border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-100'
    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-100'
  const disabled = props.disabled ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''
  const hasValue = props.modelValue ? 'text-gray-900' : 'text-gray-400'

  return [base, state, disabled, hasValue].join(' ')
})

const openPicker = () => {
  if (!props.disabled) {
    parseValue(props.modelValue)
    isOpen.value = true
  }
}

const confirmSelection = () => {
  // Ensure day is valid for the month
  const maxDay = new Date(selectedYear.value, selectedMonth.value + 1, 0).getDate()
  const day = Math.min(selectedDay.value, maxDay)

  const value = `${selectedYear.value}-${String(selectedMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  emit('update:modelValue', value)
  isOpen.value = false
}

const handleNativeChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  emit('update:modelValue', target.value)
}

// Constants for scroll calculations
const ITEM_HEIGHT = 48 // h-12 = 3rem = 48px
const CONTAINER_HEIGHT = 256 // h-64 = 16rem = 256px
const PADDING_TOP = 96 // h-24 = 6rem = 96px
const CENTER_OFFSET = (CONTAINER_HEIGHT / 2) - (ITEM_HEIGHT / 2)

// Debounce timers for scroll end detection
let dayScrollTimer: ReturnType<typeof setTimeout> | null = null
let monthScrollTimer: ReturnType<typeof setTimeout> | null = null
let yearScrollTimer: ReturnType<typeof setTimeout> | null = null

// Calculate selected index from scroll position
const getIndexFromScroll = (scrollTop: number): number => {
  const adjustedScroll = scrollTop - PADDING_TOP + CENTER_OFFSET
  return Math.round(adjustedScroll / ITEM_HEIGHT)
}

// Handle day scroll - detect scroll end and update selection
const handleDayScroll = () => {
  if (dayScrollTimer) clearTimeout(dayScrollTimer)
  dayScrollTimer = setTimeout(() => {
    if (!dayRef.value) return
    const index = getIndexFromScroll(dayRef.value.scrollTop)
    const maxDay = days.value.length
    const newDay = Math.max(1, Math.min(maxDay, index + 1))
    if (newDay !== selectedDay.value) {
      selectedDay.value = newDay
    }
  }, 100)
}

// Handle month scroll
const handleMonthScroll = () => {
  if (monthScrollTimer) clearTimeout(monthScrollTimer)
  monthScrollTimer = setTimeout(() => {
    if (!monthRef.value) return
    const index = getIndexFromScroll(monthRef.value.scrollTop)
    const newMonth = Math.max(0, Math.min(11, index))
    if (newMonth !== selectedMonth.value) {
      selectedMonth.value = newMonth
    }
  }, 100)
}

// Handle year scroll
const handleYearScroll = () => {
  if (yearScrollTimer) clearTimeout(yearScrollTimer)
  yearScrollTimer = setTimeout(() => {
    if (!yearRef.value) return
    const index = getIndexFromScroll(yearRef.value.scrollTop)
    const clampedIndex = Math.max(0, Math.min(years.value.length - 1, index))
    const newYear = years.value[clampedIndex]
    if (newYear && newYear !== selectedYear.value) {
      selectedYear.value = newYear
    }
  }, 100)
}

// Adjust day if month changes and day exceeds max
watch(selectedMonth, () => {
  const maxDay = days.value.length
  if (selectedDay.value > maxDay) {
    selectedDay.value = maxDay
  }
})

// Scroll wheels to selected values
const scrollToSelected = () => {
  nextTick(() => {
    const itemHeight = 48 // h-12 = 3rem = 48px
    const containerHeight = 256 // h-64 = 16rem = 256px
    const paddingTop = 96 // h-24 = 6rem = 96px
    const centerOffset = (containerHeight / 2) - (itemHeight / 2)

    // Scroll day wheel
    if (dayRef.value) {
      const dayIndex = selectedDay.value - 1
      const dayScrollTop = paddingTop + (dayIndex * itemHeight) - centerOffset
      dayRef.value.scrollTop = dayScrollTop
    }

    // Scroll month wheel
    if (monthRef.value) {
      const monthScrollTop = paddingTop + (selectedMonth.value * itemHeight) - centerOffset
      monthRef.value.scrollTop = monthScrollTop
    }

    // Scroll year wheel
    if (yearRef.value) {
      const yearIndex = years.value.indexOf(selectedYear.value)
      if (yearIndex >= 0) {
        const yearScrollTop = paddingTop + (yearIndex * itemHeight) - centerOffset
        yearRef.value.scrollTop = yearScrollTop
      }
    }
  })
}

// Scroll to selected when picker opens
watch(isOpen, (open) => {
  if (open) {
    scrollToSelected()
  }
})
</script>

<template>
  <div class="w-full">
    <!-- Label -->
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <!-- Mobile: Custom picker -->
    <template v-if="isMobile">
      <div class="relative">
        <button
          type="button"
          :disabled="disabled"
          :class="inputClasses"
          @click="openPicker"
        >
          {{ displayValue || placeholder || 'Seleccionar fecha' }}
        </button>

        <!-- Calendar icon -->
        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
      </div>

      <!-- Full screen date picker -->
      <Teleport to="body">
        <Transition name="datepicker">
          <div
            v-if="isOpen"
            class="fixed inset-0 z-50 bg-white flex flex-col"
          >
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
              <button
                type="button"
                class="p-2 -ml-2 text-gray-500 hover:text-gray-700"
                @click="isOpen = false"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
              <h2 class="text-lg font-semibold text-gray-900">{{ label || 'Fecha' }}</h2>
              <button
                type="button"
                class="px-4 py-2 text-primary-600 font-semibold hover:text-primary-700"
                @click="confirmSelection"
              >
                Listo
              </button>
            </div>

            <!-- Selected date display -->
            <div class="px-4 py-6 bg-gray-50 text-center">
              <p class="text-3xl font-bold text-gray-900">
                {{ selectedDay }} de {{ months[selectedMonth] }}
              </p>
              <p class="text-xl text-gray-600 mt-1">{{ selectedYear }}</p>
            </div>

            <!-- Wheel picker -->
            <div class="flex-1 flex items-center justify-center px-4">
              <div class="flex gap-2 w-full max-w-md">
                <!-- Day wheel -->
                <div class="flex-1 relative">
                  <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 h-12 bg-primary-50 rounded-xl pointer-events-none z-0" />
                  <div
                    ref="dayRef"
                    class="h-64 overflow-y-auto snap-y snap-mandatory scrollbar-hide relative z-10"
                    @scroll="handleDayScroll"
                  >
                    <div class="h-24" />
                    <button
                      v-for="day in days"
                      :key="day"
                      type="button"
                      class="w-full h-12 flex items-center justify-center snap-center transition-all"
                      :class="day === selectedDay ? 'text-2xl font-bold text-primary-700' : 'text-lg text-gray-400'"
                      @click="selectedDay = day"
                    >
                      {{ day }}
                    </button>
                    <div class="h-24" />
                  </div>
                </div>

                <!-- Month wheel -->
                <div class="flex-[2] relative">
                  <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 h-12 bg-primary-50 rounded-xl pointer-events-none z-0" />
                  <div
                    ref="monthRef"
                    class="h-64 overflow-y-auto snap-y snap-mandatory scrollbar-hide relative z-10"
                    @scroll="handleMonthScroll"
                  >
                    <div class="h-24" />
                    <button
                      v-for="(month, idx) in months"
                      :key="month"
                      type="button"
                      class="w-full h-12 flex items-center justify-center snap-center transition-all"
                      :class="idx === selectedMonth ? 'text-xl font-bold text-primary-700' : 'text-base text-gray-400'"
                      @click="selectedMonth = idx"
                    >
                      {{ month }}
                    </button>
                    <div class="h-24" />
                  </div>
                </div>

                <!-- Year wheel -->
                <div class="flex-1 relative">
                  <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 h-12 bg-primary-50 rounded-xl pointer-events-none z-0" />
                  <div
                    ref="yearRef"
                    class="h-64 overflow-y-auto snap-y snap-mandatory scrollbar-hide relative z-10"
                    @scroll="handleYearScroll"
                  >
                    <div class="h-24" />
                    <button
                      v-for="year in years"
                      :key="year"
                      type="button"
                      class="w-full h-12 flex items-center justify-center snap-center transition-all"
                      :class="year === selectedYear ? 'text-2xl font-bold text-primary-700' : 'text-lg text-gray-400'"
                      @click="selectedYear = year"
                    >
                      {{ year }}
                    </button>
                    <div class="h-24" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Confirm button -->
            <div class="p-4 border-t border-gray-200">
              <button
                type="button"
                class="w-full py-4 bg-primary-600 text-white font-semibold rounded-xl hover:bg-primary-700 transition-colors"
                @click="confirmSelection"
              >
                Confirmar
              </button>
            </div>
          </div>
        </Transition>
      </Teleport>
    </template>

    <!-- Desktop: Native date input -->
    <template v-else>
      <div class="relative">
        <input
          type="date"
          :value="modelValue"
          :disabled="disabled"
          :min="min"
          :max="max"
          :class="inputClasses"
          @input="handleNativeChange"
        />
      </div>
    </template>

    <!-- Error message -->
    <p v-if="error" class="mt-1 text-sm text-red-600">
      {{ error }}
    </p>

    <!-- Hint -->
    <p v-else-if="hint" class="mt-1 text-sm text-gray-500">
      {{ hint }}
    </p>
  </div>
</template>

<style scoped>
.datepicker-enter-active,
.datepicker-leave-active {
  transition: opacity 0.2s ease, transform 0.3s ease;
}

.datepicker-enter-from,
.datepicker-leave-to {
  opacity: 0;
  transform: translateY(100%);
}

.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
</style>