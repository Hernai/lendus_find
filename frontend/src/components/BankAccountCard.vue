<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import type { BankAccount } from '@/types/applicant'

const props = defineProps<{
  account: BankAccount
}>()

const emit = defineEmits<{
  'set-primary': [accountId: string]
  'delete': [accountId: string]
}>()

const showMenu = ref(false)
const isDeleting = ref(false)
const isSettingPrimary = ref(false)
const isMobile = ref(false)

const checkMobile = () => {
  isMobile.value = window.innerWidth < 640 // sm breakpoint
}

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})

const accountTypeLabel = computed(() => {
  const map: Record<string, string> = {
    'DEBITO': 'Debito',
    'NOMINA': 'Nomina',
    'AHORRO': 'Ahorro',
    'CHEQUES': 'Cheques',
    'INVERSION': 'Inversion',
    'OTRO': 'Otro'
  }
  return map[props.account.account_type] || props.account.account_type
})

// Check if menu has any available actions
const hasMenuActions = computed(() => {
  // Can set as primary if not already primary
  const canSetPrimary = !props.account.is_primary
  // Can delete if not verified
  const canDelete = !props.account.is_verified
  return canSetPrimary || canDelete
})

const handleSetPrimary = () => {
  isSettingPrimary.value = true
  showMenu.value = false
  emit('set-primary', props.account.id)
  // Reset after animation
  setTimeout(() => { isSettingPrimary.value = false }, 500)
}

const handleDelete = () => {
  isDeleting.value = true
  showMenu.value = false
  emit('delete', props.account.id)
}
</script>

<template>
  <div class="border border-gray-200 rounded-xl p-4 relative">
    <!-- Header -->
    <div class="flex items-start justify-between mb-3">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
          </svg>
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-semibold text-gray-900 text-base">{{ account.bank_name }}</span>
            <span
              v-if="account.is_primary"
              class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"
            >
              Principal
            </span>
          </div>
          <p class="text-sm text-gray-500">{{ accountTypeLabel }}</p>
        </div>
      </div>

      <!-- Menu Button (hidden if no actions available) -->
      <div v-if="hasMenuActions" class="relative">
        <button
          class="p-2.5 -mr-2 hover:bg-gray-100 active:bg-gray-200 rounded-xl transition-colors min-w-[44px] min-h-[44px] flex items-center justify-center flex-shrink-0"
          @click="showMenu = !showMenu"
        >
          <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
          </svg>
        </button>

        <!-- Desktop Dropdown Menu -->
        <div
          v-if="showMenu && !isMobile"
          class="absolute right-0 mt-1 w-52 bg-white rounded-xl shadow-lg border border-gray-200 py-2 z-10"
        >
          <button
            v-if="!account.is_primary"
            class="w-full px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-3"
            @click="handleSetPrimary"
          >
            <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
            </svg>
            Marcar como principal
          </button>
          <button
            v-if="!account.is_verified"
            class="w-full px-4 py-2.5 text-left text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
            @click="handleDelete"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            Eliminar
          </button>
        </div>

        <!-- Click outside overlay for desktop -->
        <div
          v-if="showMenu && !isMobile"
          class="fixed inset-0 z-0"
          @click="showMenu = false"
        />
      </div>
    </div>

    <!-- Account Details -->
    <div class="space-y-2 text-sm">
      <div class="flex justify-between py-1">
        <span class="text-gray-500">CLABE</span>
        <span class="text-gray-900 font-mono text-right">{{ account.clabe }}</span>
      </div>
      <div class="flex justify-between py-1">
        <span class="text-gray-500">Titular</span>
        <span class="text-gray-900 text-right">{{ account.holder_name }}</span>
      </div>
    </div>

    <!-- Verification Badge -->
    <div class="mt-3 pt-3 border-t border-gray-100">
      <div v-if="account.is_verified" class="flex items-center gap-1.5 text-green-600 text-sm">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
          <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
        </svg>
        Cuenta verificada
      </div>
      <div v-else class="flex items-center gap-1.5 text-gray-400 text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Pendiente de verificacion
      </div>
    </div>
  </div>

  <!-- Mobile Action Sheet (bottom sheet) -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="showMenu && isMobile"
        class="fixed inset-0 z-50 bg-black/50"
        @click="showMenu = false"
      />
    </Transition>

    <Transition
      enter-active-class="transition-transform duration-200 ease-out"
      leave-active-class="transition-transform duration-200 ease-in"
      enter-from-class="translate-y-full"
      leave-to-class="translate-y-full"
    >
      <div
        v-if="showMenu && isMobile"
        class="fixed bottom-0 left-0 right-0 z-50 bg-white rounded-t-2xl pb-safe"
      >
        <!-- Handle bar -->
        <div class="flex justify-center py-3">
          <div class="w-10 h-1 bg-gray-300 rounded-full" />
        </div>

        <!-- Account info header -->
        <div class="px-6 pb-4 border-b border-gray-100">
          <p class="text-sm text-gray-500">{{ account.bank_name }}</p>
          <p class="font-mono text-sm text-gray-700">{{ account.clabe }}</p>
        </div>

        <!-- Actions -->
        <div class="p-4 space-y-2">
          <button
            v-if="!account.is_primary"
            class="w-full flex items-center gap-4 px-4 py-4 rounded-xl hover:bg-gray-50 active:bg-gray-100 transition-colors"
            :disabled="isSettingPrimary"
            @click="handleSetPrimary"
          >
            <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
              </svg>
            </div>
            <div class="text-left">
              <p class="font-medium text-gray-900">Marcar como principal</p>
              <p class="text-sm text-gray-500">Usar esta cuenta para depositos</p>
            </div>
          </button>

          <button
            v-if="!account.is_verified"
            class="w-full flex items-center gap-4 px-4 py-4 rounded-xl hover:bg-red-50 active:bg-red-100 transition-colors"
            :disabled="isDeleting"
            @click="handleDelete"
          >
            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </div>
            <div class="text-left">
              <p class="font-medium text-red-600">Eliminar cuenta</p>
              <p class="text-sm text-gray-500">Quitar de mis cuentas registradas</p>
            </div>
          </button>
        </div>

        <!-- Cancel button -->
        <div class="px-4 pb-4">
          <button
            class="w-full py-4 bg-gray-100 text-gray-700 rounded-xl font-medium text-base hover:bg-gray-200 active:bg-gray-300 transition-colors"
            @click="showMenu = false"
          >
            Cancelar
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
