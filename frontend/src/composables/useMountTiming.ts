import { onMounted, onBeforeMount, onUnmounted } from 'vue'
import { perf } from '@/utils/perf'

/**
 * Mide el tiempo de montaje de un componente Vue: cuánto tarda desde que
 * `onBeforeMount` se dispara hasta que `onMounted` termina (incluye el
 * primer fetch si está en `await` en el setup).
 *
 * Uso:
 *   import { useMountTiming } from '@/composables/useMountTiming'
 *   useMountTiming('AdminApplicationsList')
 *
 * Las mediciones se ven en `PerformanceWidget` (Ctrl+Shift+P).
 */
export function useMountTiming(component: string): void {
  let start = 0
  onBeforeMount(() => {
    start = performance.now()
  })
  onMounted(() => {
    const duration = performance.now() - start
    perf.recordMount(component, duration)
  })
  onUnmounted(() => {
    /* noop — registro al unmount queda opcional */
  })
}
