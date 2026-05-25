<script setup lang="ts">
import { ref, computed } from 'vue'
import { useInstallPrompt } from '@/composables/useInstallPrompt'
import { platform } from '@/platform'
import { storage } from '@/utils/storage'

/**
 * Banner que ofrece instalar la app como PWA.
 *
 * - Si el navegador soporta `beforeinstallprompt`, dispara el prompt nativo.
 * - En iOS Safari muestra instrucciones de "Agregar a inicio".
 * - Si el usuario cierra el banner, se persiste el dismiss por 7 días.
 * - Nunca se muestra en builds nativas (Capacitor).
 */

const DISMISS_KEY = 'install_pwa_banner_dismissed_at'
const DISMISS_TTL_MS = 7 * 24 * 60 * 60 * 1000

const { canInstall, canShowIosInstructions, promptInstall, isInstalled } = useInstallPrompt()

const dismissedAt = ref<number | null>(storage.get<number>(DISMISS_KEY))
const showIosInstructions = ref(false)

const wasDismissedRecently = computed(() => {
  if (!dismissedAt.value) return false
  return Date.now() - dismissedAt.value < DISMISS_TTL_MS
})

const isNative = computed(() => platform.device.isNative())

const visible = computed(() => {
  if (isNative.value) return false
  if (isInstalled.value) return false
  if (wasDismissedRecently.value) return false
  return canInstall.value || canShowIosInstructions.value
})

function dismiss() {
  dismissedAt.value = Date.now()
  storage.set(DISMISS_KEY, dismissedAt.value, DISMISS_TTL_MS)
}

async function onInstall() {
  if (canShowIosInstructions.value && !canInstall.value) {
    showIosInstructions.value = true
    return
  }
  const result = await promptInstall()
  if (result === 'dismissed' || result === 'unsupported') {
    dismiss()
  }
}
</script>

<template>
  <div
    v-if="visible"
    class="fixed bottom-4 inset-x-4 z-40 md:left-auto md:right-4 md:max-w-sm bg-white border border-gray-200 shadow-lg rounded-xl p-4"
    role="dialog"
    aria-live="polite"
  >
    <div class="flex items-start gap-3">
      <div class="shrink-0 w-10 h-10 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center font-bold">
        ⬇
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-gray-900">Instala la app</p>
        <p class="text-xs text-gray-600 mt-0.5">
          Accede más rápido desde tu pantalla de inicio.
        </p>
        <div v-if="showIosInstructions" class="mt-2 text-xs text-gray-700 space-y-1">
          <p>En Safari, toca el botón <strong>Compartir</strong> y elige <strong>«Agregar a inicio»</strong>.</p>
        </div>
        <div v-else class="mt-3 flex gap-2">
          <button
            type="button"
            class="text-xs px-3 py-1.5 rounded-md bg-primary-600 text-white hover:bg-primary-700"
            @click="onInstall"
          >
            Instalar
          </button>
          <button
            type="button"
            class="text-xs px-3 py-1.5 rounded-md text-gray-600 hover:text-gray-900"
            @click="dismiss"
          >
            Después
          </button>
        </div>
      </div>
      <button
        type="button"
        class="text-gray-400 hover:text-gray-600 -mt-1"
        aria-label="Cerrar"
        @click="dismiss"
      >
        ✕
      </button>
    </div>
  </div>
</template>
