import { Browser } from '@capacitor/browser'
import type { PlatformBrowser } from '../types'

/**
 * Implementación native de PlatformBrowser.
 *
 * - HTTP/HTTPS: usa `@capacitor/browser` (Safari View Controller / Custom Tab).
 *   Para forzar app externa (Safari/Chrome) usamos `windowName: '_system'`.
 * - mailto: / tel: / wa.me: la WebView intercepta estos esquemas y los entrega
 *   al sistema, así que basta con `window.open`.
 */
export const browserNative: PlatformBrowser = {
  async open(url: string, opts?: { external?: boolean }): Promise<void> {
    if (opts?.external) {
      // Capacitor reconoce `_system` y delega al navegador del SO.
      window.open(url, '_system')
      return
    }
    await Browser.open({ url })
  },

  async openEmail(to: string, subject?: string, body?: string): Promise<void> {
    const params = new URLSearchParams()
    if (subject) params.set('subject', subject)
    if (body) params.set('body', body)
    const query = params.toString()
    window.open(`mailto:${to}${query ? '?' + query : ''}`, '_system')
  },

  async openWhatsApp(phone: string, text?: string): Promise<void> {
    const digits = phone.replace(/[^\d]/g, '')
    const url = text
      ? `https://wa.me/${digits}?text=${encodeURIComponent(text)}`
      : `https://wa.me/${digits}`
    window.open(url, '_system')
  },

  async openTel(phone: string): Promise<void> {
    window.open(`tel:${phone.replace(/[^\d+]/g, '')}`, '_system')
  },
}
