import type { Router } from 'vue-router'
import type { PlatformNavigator } from '../types'

/**
 * Implementación web del navegador de plataforma.
 *
 * Por defecto opera con `window.location` (comportamiento histórico).
 * Si se inyecta un Router de vue-router vía `bindRouter()`, las navegaciones
 * internas usan el router (sin recargar la app).
 */

let boundRouter: Router | null = null

export function bindRouter(router: Router): void {
  boundRouter = router
}

export const navigatorWeb: PlatformNavigator = {
  currentPath(): string {
    if (boundRouter && boundRouter.currentRoute.value) {
      return boundRouter.currentRoute.value.fullPath
    }
    return window.location.pathname + window.location.search + window.location.hash
  },

  async navigate(path: string, options?: { replace?: boolean }): Promise<void> {
    if (boundRouter) {
      if (options?.replace) {
        await boundRouter.replace(path)
      } else {
        await boundRouter.push(path)
      }
      return
    }
    if (options?.replace) {
      window.location.replace(path)
    } else {
      window.location.href = path
    }
  },

  back(): void {
    if (boundRouter) {
      boundRouter.back()
      return
    }
    window.history.back()
  },

  reload(): void {
    window.location.reload()
  },
}
