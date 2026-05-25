import type { PlatformBrowser } from '../types'

/**
 * Implementación web de PlatformBrowser.
 *
 * `external:true` no cambia nada en web (todo abre con `window.open` en nueva
 * pestaña). En native, los adapters nativos usarán `@capacitor/browser` para
 * el in-app browser y `@capacitor/app` para `openUrl` externo.
 */
export const browserWeb: PlatformBrowser = {
  async open(url: string, _opts?: { external?: boolean }): Promise<void> {
    window.open(url, '_blank', 'noopener,noreferrer')
  },

  async openEmail(to: string, subject?: string, body?: string): Promise<void> {
    const params = new URLSearchParams()
    if (subject) params.set('subject', subject)
    if (body) params.set('body', body)
    const query = params.toString()
    const href = `mailto:${encodeURIComponent(to)}${query ? '?' + query : ''}`
    window.location.href = href
  },

  async openWhatsApp(phone: string, text?: string): Promise<void> {
    const digits = phone.replace(/[^\d]/g, '')
    const base = `https://wa.me/${digits}`
    const url = text ? `${base}?text=${encodeURIComponent(text)}` : base
    window.open(url, '_blank', 'noopener,noreferrer')
  },

  async openTel(phone: string): Promise<void> {
    window.location.href = `tel:${phone.replace(/[^\d+]/g, '')}`
  },
}
