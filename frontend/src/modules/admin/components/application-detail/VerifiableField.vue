<script setup lang="ts">
import { computed } from 'vue'

interface FieldVerification {
  status: 'pending' | 'verified' | 'rejected'
  method?: string
  method_label?: string
  rejection_reason?: string
}

const props = defineProps<{
  label: string
  value: string | number | null | undefined
  fieldKey: string
  verification?: FieldVerification | null
  isLocked?: boolean
  isVerifying?: boolean
  canVerify?: boolean
}>()

const emit = defineEmits<{
  (e: 'verify', action: 'verify' | 'unverify'): void
  (e: 'reject'): void
  (e: 'unreject'): void
}>()

const displayValue = computed(() => props.value ?? '—')
const hasValue = computed(() => props.value !== null && props.value !== undefined && props.value !== '')

const isVerified = computed(() => props.verification?.status === 'verified')
const isRejected = computed(() => props.verification?.status === 'rejected')
const isPending = computed(() => props.verification?.status === 'pending')

const dotClass = computed(() => {
  if (isRejected.value) return 'bg-red-500'
  if (isVerified.value) return 'bg-green-500'
  if (isPending.value) return 'bg-yellow-500'
  if (hasValue.value) return 'bg-blue-500'
  return 'bg-gray-300'
})
</script>

<template>
  <div class="group relative">
    <div class="flex items-center gap-1.5 mb-0.5">
      <span
        class="w-2 h-2 rounded-full flex-shrink-0 transition-colors"
        :class="dotClass"
      ></span>
      <span class="text-xs text-gray-500">{{ label }}</span>

      <!-- Lock icon for KYC-verified fields -->
      <svg
        v-if="isLocked"
        class="w-3 h-3 text-gray-400"
        fill="currentColor"
        viewBox="0 0 20 20"
        title="Verificado - No modificable"
      >
        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
      </svg>

      <!-- Action buttons (show on hover, only if not locked) -->
      <div
        v-if="hasValue && !isLocked && canVerify"
        class="opacity-0 group-hover:opacity-100 transition-opacity ml-auto flex items-center gap-0.5"
      >
        <!-- Verify button -->
        <button
          v-if="!isVerified && !isRejected"
          class="p-0.5 rounded hover:bg-green-100 text-gray-400 hover:text-green-600"
          :disabled="isVerifying"
          title="Verificar dato"
          @click="emit('verify', 'verify')"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </button>

        <!-- Unverify button -->
        <button
          v-if="isVerified"
          class="p-0.5 rounded hover:bg-gray-100 text-green-600"
          :disabled="isVerifying"
          title="Quitar verificación"
          @click="emit('verify', 'unverify')"
        >
          <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
        </button>

        <!-- Reject button -->
        <button
          v-if="!isRejected"
          class="p-0.5 rounded hover:bg-red-100 text-gray-400 hover:text-red-600"
          :disabled="isVerifying"
          title="Rechazar dato"
          @click="emit('reject')"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </button>

        <!-- Unreject button -->
        <button
          v-if="isRejected"
          class="p-0.5 rounded hover:bg-gray-100 text-red-600"
          :disabled="isVerifying"
          title="Quitar rechazo"
          @click="emit('unreject')"
        >
          <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>

      <!-- Method label for locked fields -->
      <div v-if="isLocked" class="ml-auto">
        <span class="text-[10px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
          {{ verification?.method_label || 'KYC' }}
        </span>
      </div>
    </div>

    <p class="font-medium text-gray-900 truncate">{{ displayValue }}</p>

    <p v-if="isRejected && verification?.rejection_reason" class="text-xs text-red-600 mt-0.5">
      {{ verification.rejection_reason }}
    </p>

    <p v-if="isLocked" class="text-[10px] text-gray-500 mt-0.5">
      Verificado automáticamente - No modificable
    </p>
  </div>
</template>
