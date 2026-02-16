---
name: frontend-conventions
description: Convenciones Vue.js 3, TypeScript y Tailwind CSS para LendusFind. Usar al crear o modificar componentes, stores, services, composables o types.
---

# Frontend Conventions

## Cuándo aplica
Seguir estas convenciones al crear o modificar archivos en `frontend/src/`: componentes Vue, stores Pinia, servicios API, composables, tipos o estilos.

## Vue Component Conventions

```vue
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useToast } from '@/composables/useToast'
import type { V2Application } from '@/types/v2'

// Props con defaults
interface Props {
  applicationId: string
  showActions?: boolean
}
const props = withDefaults(defineProps<Props>(), {
  showActions: true,
})

// Emits tipados
const emit = defineEmits<{
  approved: [application: V2Application]
  rejected: [application: V2Application]
}>()

// Composables
const toast = useToast()

// State
const loading = ref(false)
const application = ref<V2Application | null>(null)

// Computed
const canApprove = computed(() => application.value?.status === 'SUBMITTED')

// Methods
async function approve() {
  loading.value = true
  try {
    // ...
    emit('approved', application.value!)
    toast.success('Solicitud aprobada')
  } catch (error) {
    toast.error('Error al aprobar la solicitud')
  } finally {
    loading.value = false
  }
}

// Lifecycle
onMounted(async () => {
  await loadApplication()
})
</script>

<template>
  <!-- Template aquí -->
</template>
```

Orden: imports → props → emits → composables → state → computed → methods → lifecycle

## Service Conventions

```typescript
/**
 * V2 Staff Application Service
 *
 * Handles application management for staff users.
 * All endpoints are under /api/v2/staff/applications
 */

import { api } from '../api'
import type { V2ApiResponse } from '@/types/v2'

const BASE_PATH = '/v2/staff/applications'

// =====================================================
// Types
// =====================================================

export interface V2ApplicationPayload {
  product_id: string
  amount: number
  term_months: number
}

// =====================================================
// API Functions
// =====================================================

export async function list(params?: Record<string, unknown>): Promise<V2ApiResponse<{ applications: V2Application[] }>> {
  const response = await api.get<V2ApiResponse<{ applications: V2Application[] }>>(BASE_PATH, { params })
  return response.data
}

export async function create(payload: V2ApplicationPayload): Promise<V2ApiResponse<{ application: V2Application }>> {
  const response = await api.post<V2ApiResponse<{ application: V2Application }>>(BASE_PATH, payload)
  return response.data
}

export default { list, create }
```

Patrones:
- **Named exports** para cada función + **default export** objeto
- `BASE_PATH` constante al inicio
- Return type siempre `Promise<V2ApiResponse<T>>`
- Section headers: `// =====================================================`
- Naming por rol: `*.applicant.service.ts`, `*.staff.service.ts`, `*.public.service.ts`
- Types definidos inline en el archivo de servicio

## Pinia Store Conventions

```typescript
import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { logger } from '@/utils/logger'

const log = logger.child('StoreName')

export const useApplicationStore = defineStore('applications', () => {
  // State
  const applications = ref<V2Application[]>([])
  const loading = ref(false)

  // Getters (computed)
  const pendingCount = computed(() =>
    applications.value.filter(a => a.status === 'SUBMITTED').length
  )

  // Actions
  async function fetchAll() {
    loading.value = true
    try {
      const response = await applicationService.list()
      if (response.success) {
        applications.value = response.data.applications
      }
    } finally {
      loading.value = false
    }
  }

  function reset() {
    applications.value = []
  }

  return { applications, loading, pendingCount, fetchAll, reset }
})
```

- **Composition API**: `defineStore('name', () => { ... })`
- `logger.child('StoreName')` para logging
- Explicit `return {}` listando todo lo expuesto

## Composable Conventions

```typescript
import { ref, type Ref } from 'vue'
import { logger } from '@/utils/logger'

const log = logger.child('AsyncAction')

export function useAsyncAction<TArgs extends unknown[], TResult>(
  action: (...args: TArgs) => Promise<TResult>,
  options?: AsyncActionOptions<TResult>
): AsyncActionResult<TArgs, TResult> {
  const isLoading: Ref<boolean> = ref(false)
  const error: Ref<string | null> = ref(null)

  const execute = async (...args: TArgs): Promise<TResult | null> => {
    isLoading.value = true
    error.value = null
    try {
      const result = await action(...args)
      options?.onSuccess?.(result)
      return result
    } catch (e) {
      const errorObj = e instanceof Error ? e : new Error(String(e))
      error.value = errorObj.message
      options?.onError?.(errorObj)
      if (options?.rethrow) throw errorObj
      return null
    } finally {
      isLoading.value = false
    }
  }

  return { execute, isLoading, error, clearError: () => { error.value = null }, reset: () => { isLoading.value = false; error.value = null } }
}
```

- Prefijo `use`: `useToast()`, `useModal()`, `useAsyncAction()`
- Return `ref`s + funciones
- También existe `useAsyncForm<TLoad, TSave>()` para formularios con load + save

## Type Conventions

```typescript
// Base response (src/types/v2/index.ts)
export interface V2ApiResponse<T = unknown> {
  success: boolean
  data?: T
  message?: string
  error?: string
  errors?: Record<string, string[]>
}

// Error utilities (src/types/api.ts)
export function isAxiosError(error: unknown): error is AxiosErrorResponse { ... }
export function getErrorMessage(error: unknown, defaultMessage = 'Error desconocido'): string { ... }
export function getValidationErrors(error: unknown): Record<string, string> { ... }
export function isValidationError(error: unknown): boolean { ... }  // HTTP 422
export function isAuthError(error: unknown): boolean { ... }       // HTTP 401
export function isForbiddenError(error: unknown): boolean { ... }  // HTTP 403
export function isNotFoundError(error: unknown): boolean { ... }   // HTTP 404
```

- Payload types: `V2*Payload`, response types: `V2*`
- `V2ApiResponse<T>` — todos los campos excepto `success` son opcionales

## Router Conventions

```typescript
{
  path: '/admin/solicitudes',
  name: 'admin-applications',
  component: () => import('@/views/admin/panel/AdminApplications.vue'),
  meta: { requiresAuth: true, requiresStaff: true, title: 'Solicitudes' },
}
```

- Lazy loading: `() => import('...')`
- Route names kebab-case: `admin-applications`
- Meta flags: `requiresAuth`, `requiresStaff`, `public`

## CSS / Tailwind Conventions
- **Mobile-first**: base → `md:` → `lg:`
- Color system: `primary-600` (mapeado a branding del tenant via CSS variables)
- Botones: `bg-primary-600 hover:bg-primary-700 text-white rounded-lg px-4 py-2`
- Focus: `focus:ring-2 focus:ring-primary-500 focus:ring-offset-2`
- Disabled: `disabled:opacity-50 disabled:cursor-not-allowed`
- Transiciones: `transition-colors duration-200`

## Error Handling

```typescript
async function save() {
  loading.value = true
  try {
    const response = await service.create(form)
    if (response.success) {
      toast.success(response.message)
    }
  } catch (error) {
    const message = getErrorMessage(error)
    toast.error(message)
    if (isAxiosError(error) && error.response?.data?.errors) {
      formErrors.value = error.response.data.errors
    }
  } finally {
    loading.value = false
  }
}
```

## Errores comunes a evitar
1. **Olvidar `<script setup lang="ts">`** — Siempre TypeScript
2. **Usar Options API** — Solo Composition API con `<script setup>`
3. **No tipar refs** — Siempre `ref<Type>()`
4. **Usar `isApiError()`** — El nombre correcto es `isAxiosError()` en `src/types/api.ts`
5. **Asumir `data` no es opcional** — En `V2ApiResponse<T>`, `data` es `T | undefined`
