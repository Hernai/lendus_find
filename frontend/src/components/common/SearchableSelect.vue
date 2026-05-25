<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'

interface Option {
  readonly value: string | number
  readonly label: string
}

interface Props {
  modelValue: string | number | null
  options: readonly Option[]
  placeholder?: string
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Seleccionar...',
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number | null]
}>()

const isOpen = ref(false)
const search = ref('')
const highlightIndex = ref(-1)
const containerRef = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)
const listRef = ref<HTMLElement | null>(null)

const selectedOption = computed(() =>
  props.options.find((o) => o.value === props.modelValue) ?? null,
)

const filteredOptions = computed(() => {
  if (!search.value) return props.options
  const q = search.value.toLowerCase()
  return props.options.filter((o) => o.label.toLowerCase().includes(q))
})

const open = () => {
  if (props.disabled) return
  isOpen.value = true
  search.value = ''
  highlightIndex.value = -1
  nextTick(() => inputRef.value?.focus())
}

const close = () => {
  isOpen.value = false
  search.value = ''
}

const select = (option: Option) => {
  emit('update:modelValue', option.value)
  close()
}

const clear = () => {
  emit('update:modelValue', null)
  close()
}

const onKeydown = (e: KeyboardEvent) => {
  const len = filteredOptions.value.length
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    highlightIndex.value = (highlightIndex.value + 1) % len
    scrollToHighlighted()
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    highlightIndex.value = (highlightIndex.value - 1 + len) % len
    scrollToHighlighted()
  } else if (e.key === 'Enter' && highlightIndex.value >= 0) {
    e.preventDefault()
    select(filteredOptions.value[highlightIndex.value])
  } else if (e.key === 'Escape') {
    close()
  }
}

const scrollToHighlighted = () => {
  nextTick(() => {
    const el = listRef.value?.children[highlightIndex.value] as HTMLElement | undefined
    el?.scrollIntoView({ block: 'nearest' })
  })
}

watch(search, () => {
  highlightIndex.value = 0
})

// Click outside
const onClickOutside = (e: MouseEvent) => {
  if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
    close()
  }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <div ref="containerRef" class="relative">
    <!-- Trigger -->
    <button
      type="button"
      :disabled="disabled"
      class="w-full flex items-center gap-2 px-3 py-2 text-sm border rounded-lg bg-white transition-colors text-left"
      :class="[
        isOpen
          ? 'border-indigo-400 ring-2 ring-indigo-100'
          : 'border-gray-300 hover:border-gray-400',
        disabled ? 'bg-gray-50 cursor-not-allowed opacity-60' : 'cursor-pointer',
      ]"
      @click="open"
    >
      <span class="flex-1 truncate" :class="selectedOption ? 'text-gray-900' : 'text-gray-400'">
        {{ selectedOption?.label || placeholder }}
      </span>

      <!-- Clear -->
      <span
        v-if="selectedOption && !disabled"
        role="button"
        class="flex-shrink-0 p-0.5 rounded hover:bg-gray-200 text-gray-400 hover:text-gray-600 transition-colors"
        @click.stop="clear"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </span>

      <!-- Chevron -->
      <svg
        class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform"
        :class="isOpen ? 'rotate-180' : ''"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <!-- Dropdown -->
    <Transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div
        v-if="isOpen"
        class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden"
      >
        <!-- Search -->
        <div class="p-2 border-b border-gray-100">
          <div class="relative">
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              ref="inputRef"
              v-model="search"
              type="text"
              class="w-full pl-8 pr-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-100"
              placeholder="Buscar..."
              @keydown="onKeydown"
            />
          </div>
        </div>

        <!-- Options -->
        <ul ref="listRef" class="max-h-52 overflow-y-auto py-1">
          <li
            v-for="(option, idx) in filteredOptions"
            :key="option.value"
            class="flex items-center justify-between px-3 py-2 text-sm cursor-pointer transition-colors"
            :class="[
              option.value === modelValue
                ? 'bg-indigo-50 text-indigo-700 font-medium'
                : idx === highlightIndex
                  ? 'bg-gray-100 text-gray-900'
                  : 'text-gray-700 hover:bg-gray-50',
            ]"
            @click="select(option)"
            @mouseenter="highlightIndex = idx"
          >
            <span class="truncate">{{ option.label }}</span>
            <svg
              v-if="option.value === modelValue"
              class="w-4 h-4 text-indigo-600 flex-shrink-0"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fill-rule="evenodd"
                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                clip-rule="evenodd"
              />
            </svg>
          </li>
          <li v-if="filteredOptions.length === 0" class="px-3 py-4 text-sm text-gray-400 text-center">
            Sin resultados
          </li>
        </ul>
      </div>
    </Transition>
  </div>
</template>
