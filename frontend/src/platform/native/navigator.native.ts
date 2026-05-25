import type { Router } from 'vue-router'
import { App } from '@capacitor/app'
import type { PlatformNavigator } from '../types'

/**
 * Implementación native del navegador.
 *
 * Toda navegación interna pasa por vue-router (no hay `window.location` real
 * en apps Capacitor). `back()` también respeta el botón físico Android vía
 * `@capacitor/app`.
 */

let boundRouter: Router | null = null

export function bindRouter(router: Router): void {
  boundRouter = router
  // En Android, el botón hardware "back" debe usar la historia del router.
  App.addListener('backButton', ({ canGoBack }) => {
    if (canGoBack && boundRouter) {
      boundRouter.back()
    } else {
      App.exitApp()
    }
  })
}

export const navigatorNative: PlatformNavigator = {
  currentPath(): string {
    return boundRouter?.currentRoute.value.fullPath ?? '/'
  },

  async navigate(path: string, options?: { replace?: boolean }): Promise<void> {
    if (!boundRouter) {
      throw new Error('Router no vinculado — invoque bindRouter() en main.ts')
    }
    if (options?.replace) {
      await boundRouter.replace(path)
    } else {
      await boundRouter.push(path)
    }
  },

  back(): void {
    boundRouter?.back()
  },

  reload(): void {
    if (!boundRouter) return
    // En native no podemos `window.location.reload`. Forzamos remount
    // navegando a la ruta actual con replace.
    const current = boundRouter.currentRoute.value.fullPath
    boundRouter.replace('/_reload').then(() => boundRouter?.replace(current))
  },
}
