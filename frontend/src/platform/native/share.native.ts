import { Share } from '@capacitor/share'
import type { PlatformShare } from '../types'

export const shareNative: PlatformShare = {
  isSupported(): boolean {
    return true
  },

  async share(payload: { title?: string; text?: string; url?: string }): Promise<void> {
    await Share.share({
      title: payload.title,
      text: payload.text,
      url: payload.url,
      dialogTitle: payload.title,
    })
  },
}
