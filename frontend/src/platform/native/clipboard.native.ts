import { Clipboard } from '@capacitor/clipboard'
import type { PlatformClipboard } from '../types'

export const clipboardNative: PlatformClipboard = {
  async copy(text: string): Promise<void> {
    await Clipboard.write({ string: text })
  },

  async read(): Promise<string | null> {
    try {
      const { value } = await Clipboard.read()
      return value || null
    } catch {
      return null
    }
  },
}
