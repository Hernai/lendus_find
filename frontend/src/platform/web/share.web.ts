import type { PlatformShare } from '../types'

/**
 * Implementación web de PlatformShare usando navigator.share.
 *
 * Fallback: copia al portapapeles si el navegador no soporta Web Share API.
 */
export const shareWeb: PlatformShare = {
  isSupported(): boolean {
    return typeof navigator !== 'undefined' && typeof navigator.share === 'function'
  },

  async share(payload: { title?: string; text?: string; url?: string }): Promise<void> {
    if (this.isSupported()) {
      await navigator.share(payload)
      return
    }
    // Fallback: copiar URL o texto al portapapeles.
    const fallback = payload.url ?? payload.text ?? payload.title ?? ''
    if (fallback && typeof navigator !== 'undefined' && navigator.clipboard) {
      await navigator.clipboard.writeText(fallback)
    }
  },
}
