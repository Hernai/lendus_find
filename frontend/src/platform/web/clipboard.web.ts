import type { PlatformClipboard } from '../types'

/**
 * Implementación web de PlatformClipboard usando Clipboard API.
 *
 * Si la API no está disponible (contextos no seguros), usa el fallback de
 * `document.execCommand('copy')`.
 */
export const clipboardWeb: PlatformClipboard = {
  async copy(text: string): Promise<void> {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
      await navigator.clipboard.writeText(text)
      return
    }
    // Fallback no-secure-context.
    const textarea = document.createElement('textarea')
    textarea.value = text
    textarea.setAttribute('readonly', '')
    textarea.style.position = 'fixed'
    textarea.style.opacity = '0'
    document.body.appendChild(textarea)
    textarea.select()
    try {
      document.execCommand('copy')
    } finally {
      document.body.removeChild(textarea)
    }
  },

  async read(): Promise<string | null> {
    if (typeof navigator !== 'undefined' && navigator.clipboard?.readText) {
      try {
        return await navigator.clipboard.readText()
      } catch {
        return null
      }
    }
    return null
  },
}
