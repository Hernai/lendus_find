import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { platform } from '@/platform'

/**
 * Composable para gestionar el prompt de instalación de PWA.
 *
 * - En navegadores Chromium captura `beforeinstallprompt` y lo expone vía
 *   `promptInstall()`.
 * - En Safari iOS no existe el evento, así que detectamos el UA y
 *   `canShowIosInstructions` se encarga de orientar al usuario para
 *   "Agregar a inicio".
 * - Si la app ya corre standalone (instalada) `isInstalled` queda en true.
 */

interface BeforeInstallPromptEvent extends Event {
  readonly platforms: readonly string[]
  prompt(): Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

export function useInstallPrompt() {
  const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null)
  const isInstalled = ref<boolean>(false)

  function isStandalone(): boolean {
    if (typeof window === 'undefined') return false
    if (window.matchMedia('(display-mode: standalone)').matches) return true
    // iOS Safari expone `navigator.standalone`.
    const nav = window.navigator as Navigator & { standalone?: boolean }
    return nav.standalone === true
  }

  const isIos = computed(() => {
    if (platform.device.isNative()) return false
    if (typeof navigator === 'undefined') return false
    return /iPad|iPhone|iPod/.test(navigator.userAgent)
  })

  const canInstall = computed(() => deferredPrompt.value !== null)
  const canShowIosInstructions = computed(() => isIos.value && !isInstalled.value)

  function handleBeforeInstallPrompt(e: Event) {
    e.preventDefault()
    deferredPrompt.value = e as BeforeInstallPromptEvent
  }

  function handleAppInstalled() {
    deferredPrompt.value = null
    isInstalled.value = true
  }

  async function promptInstall(): Promise<'accepted' | 'dismissed' | 'unsupported'> {
    if (!deferredPrompt.value) return 'unsupported'
    await deferredPrompt.value.prompt()
    const choice = await deferredPrompt.value.userChoice
    deferredPrompt.value = null
    return choice.outcome
  }

  onMounted(() => {
    isInstalled.value = isStandalone()
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
    window.addEventListener('appinstalled', handleAppInstalled)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
    window.removeEventListener('appinstalled', handleAppInstalled)
  })

  return {
    canInstall,
    canShowIosInstructions,
    isInstalled,
    isIos,
    promptInstall,
  }
}
