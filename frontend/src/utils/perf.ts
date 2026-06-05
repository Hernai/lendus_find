/**
 * Sistema de instrumentación de performance para el frontend.
 *
 * Captura tres tipos de mediciones en un store reactivo:
 * - `api`: cada llamada HTTP (método, URL, status, duración, TTFB).
 * - `nav`: tiempos del browser al cargar la página (TTFB, FCP, LCP).
 * - `mount`: tiempo de montaje de componentes Vue clave.
 *
 * Activación: cualquiera de los dos:
 *   - URL con `?perf=1`
 *   - `localStorage.setItem('lf:perf', '1')`
 *
 * Visualización: el componente `PerformanceWidget` lee este store y lo
 * pinta en una tabla flotante. Atajo de teclado: `Ctrl+Shift+P`.
 */

import { reactive, readonly, computed } from 'vue'

export interface ApiMetric {
  id: number
  type: 'api'
  method: string
  url: string
  status?: number
  durationMs: number
  startedAt: number
  error?: string
}

export interface NavMetric {
  id: number
  type: 'nav'
  metric: 'ttfb' | 'fcp' | 'lcp' | 'dom-ready' | 'load'
  durationMs: number
  startedAt: number
}

export interface MountMetric {
  id: number
  type: 'mount'
  component: string
  durationMs: number
  startedAt: number
}

export type PerfMetric = ApiMetric | NavMetric | MountMetric

interface PerfState {
  enabled: boolean
  visible: boolean
  metrics: PerfMetric[]
  maxItems: number
}

const STORAGE_KEY = 'lf:perf'
const STORAGE_VISIBLE = 'lf:perf:visible'

let counter = 0
const nextId = () => ++counter

function detectEnabled(): boolean {
  if (typeof window === 'undefined') return false
  const params = new URLSearchParams(window.location.search)
  if (params.get('perf') === '1') {
    window.localStorage.setItem(STORAGE_KEY, '1')
    return true
  }
  if (params.get('perf') === '0') {
    window.localStorage.removeItem(STORAGE_KEY)
    return false
  }
  return window.localStorage.getItem(STORAGE_KEY) === '1'
}

const state = reactive<PerfState>({
  enabled: detectEnabled(),
  visible: typeof window !== 'undefined' && window.localStorage.getItem(STORAGE_VISIBLE) !== '0',
  metrics: [],
  maxItems: 100,
})

function push(metric: PerfMetric): void {
  if (!state.enabled) return
  state.metrics.unshift(metric)
  if (state.metrics.length > state.maxItems) {
    state.metrics.length = state.maxItems
  }
}

export const perf = {
  state: readonly(state),

  enable(): void {
    state.enabled = true
    window.localStorage.setItem(STORAGE_KEY, '1')
  },

  disable(): void {
    state.enabled = false
    state.metrics.length = 0
    window.localStorage.removeItem(STORAGE_KEY)
  },

  toggle(): void {
    state.enabled ? this.disable() : this.enable()
  },

  toggleVisible(): void {
    state.visible = !state.visible
    window.localStorage.setItem(STORAGE_VISIBLE, state.visible ? '1' : '0')
  },

  clear(): void {
    state.metrics.length = 0
  },

  /** Registra una métrica de API (llamado por el interceptor de axios). */
  recordApi(input: Omit<ApiMetric, 'id' | 'type'>): void {
    push({ id: nextId(), type: 'api', ...input })
  },

  /** Registra el tiempo de montaje de un componente Vue. */
  recordMount(component: string, durationMs: number): void {
    push({
      id: nextId(),
      type: 'mount',
      component,
      durationMs,
      startedAt: performance.now() - durationMs,
    })
  },

  /** Registra una métrica de navegación del browser. */
  recordNav(metric: NavMetric['metric'], durationMs: number): void {
    push({
      id: nextId(),
      type: 'nav',
      metric,
      durationMs,
      startedAt: performance.now() - durationMs,
    })
  },
}

/** Lee las métricas de Navigation Timing del browser una vez al cargar. */
export function captureNavigationTiming(): void {
  if (!state.enabled || typeof window === 'undefined') return

  // PerformanceNavigationTiming (W3C estándar). El `load` event garantiza
  // que `responseStart`/`responseEnd`/`domContentLoaded` ya están seteados.
  const onLoad = () => {
    try {
      const nav = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming | undefined
      if (!nav) return

      perf.recordNav('ttfb', nav.responseStart - nav.requestStart)
      perf.recordNav('dom-ready', nav.domContentLoadedEventEnd - nav.fetchStart)
      perf.recordNav('load', nav.loadEventEnd - nav.fetchStart)
    } catch {
      /* noop */
    }
  }

  if (document.readyState === 'complete') {
    onLoad()
  } else {
    window.addEventListener('load', onLoad, { once: true })
  }

  // First Contentful Paint via PerformanceObserver.
  try {
    const fcpObserver = new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        if (entry.name === 'first-contentful-paint') {
          perf.recordNav('fcp', entry.startTime)
          fcpObserver.disconnect()
        }
      }
    })
    fcpObserver.observe({ type: 'paint', buffered: true })
  } catch {
    /* noop */
  }

  // Largest Contentful Paint.
  try {
    let lastLcp = 0
    const lcpObserver = new PerformanceObserver((list) => {
      const entries = list.getEntries()
      const last = entries[entries.length - 1]
      if (last) lastLcp = last.startTime
    })
    lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true })

    // Detener observación cuando hay interacción (LCP final).
    const stopLcp = () => {
      if (lastLcp > 0) perf.recordNav('lcp', lastLcp)
      lcpObserver.disconnect()
      window.removeEventListener('visibilitychange', onHide)
    }
    const onHide = () => {
      if (document.visibilityState === 'hidden') stopLcp()
    }
    window.addEventListener('visibilitychange', onHide)
    // Fallback: a los 10s capturamos lo que tengamos.
    setTimeout(stopLcp, 10_000)
  } catch {
    /* noop */
  }
}

/** Estadísticas agregadas, útiles para el widget. */
export const perfStats = computed(() => {
  const apis = state.metrics.filter((m): m is ApiMetric => m.type === 'api')
  if (apis.length === 0) {
    return { count: 0, avg: 0, p95: 0, errors: 0, slowest: null as ApiMetric | null }
  }
  const sorted = [...apis].sort((a, b) => a.durationMs - b.durationMs)
  const total = sorted.reduce((acc, m) => acc + m.durationMs, 0)
  const p95Idx = Math.max(0, Math.floor(sorted.length * 0.95) - 1)
  return {
    count: apis.length,
    avg: Math.round(total / apis.length),
    p95: Math.round(sorted[p95Idx]?.durationMs ?? 0),
    errors: apis.filter((m) => !!m.error || (m.status && m.status >= 400)).length,
    slowest: sorted[sorted.length - 1] ?? null,
  }
})

if (typeof window !== 'undefined') {
  // Atajo de teclado para mostrar/ocultar el widget: Ctrl+Shift+P
  window.addEventListener('keydown', (e) => {
    if (e.ctrlKey && e.shiftKey && e.key.toUpperCase() === 'P') {
      e.preventDefault()
      if (!state.enabled) perf.enable()
      else perf.toggleVisible()
    }
  })
}
