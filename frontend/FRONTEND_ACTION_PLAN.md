# Plan de Acción Frontend - LendusFind

**Fecha**: 2026-01-15
**Principio**: Sin cambios visuales ni de comportamiento

---

## Restricciones

- ✅ Refactorización interna solamente
- ✅ Mismo comportamiento exacto
- ✅ Misma apariencia visual
- ❌ NO cambiar estructura de componentes visibles
- ❌ NO cambiar props/eventos públicos
- ❌ NO cambiar rutas

---

## Fase 1: Composables Reutilizables (Sin cambios visuales)

### 1.1 Crear `useAsyncState`

```typescript
// src/composables/useAsyncState.ts
export function useAsyncState() {
  const isLoading = ref(false)
  const isSaving = ref(false)
  const error = ref<string | null>(null)

  const clearError = () => { error.value = null }

  return { isLoading, isSaving, error, clearError }
}
```

**Impacto**: Centraliza estado async, reduce duplicación ~50 líneas

### 1.2 Crear `useAsyncAction`

```typescript
// src/composables/useAsyncAction.ts
export function useAsyncAction<T>(
  action: () => Promise<T>,
  options?: {
    onSuccess?: (data: T) => void
    onError?: (error: Error) => void
  }
) {
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const execute = async (): Promise<T | null> => {
    isLoading.value = true
    error.value = null
    try {
      const result = await action()
      options?.onSuccess?.(result)
      return result
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      options?.onError?.(e as Error)
      return null
    } finally {
      isLoading.value = false
    }
  }

  return { execute, isLoading, error }
}
```

**Impacto**: Elimina try-catch-finally repetido, ~150 líneas

### 1.3 Crear `useValidation`

```typescript
// src/composables/useValidation.ts
export function useValidation() {
  const isValidId = (id: unknown): id is string => {
    return typeof id === 'string' &&
           id !== 'null' &&
           id !== 'undefined' &&
           id.length > 0
  }

  const isValidUuid = (id: unknown): id is string => {
    if (!isValidId(id)) return false
    const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i
    return uuidRegex.test(id)
  }

  return { isValidId, isValidUuid }
}
```

**Impacto**: Centraliza validación de IDs, ~30 líneas

---

## Fase 2: Tipos Centralizados (Sin cambios visuales)

### 2.1 Crear `types/admin.ts`

Extraer interfaces duplicadas de AdminApplicationDetail.vue:

```typescript
// src/types/admin.ts
export interface AdminDocument {
  id: string
  uuid: string
  type: string
  status: string
  // ... resto de campos
}

export interface AdminReference {
  id: string
  uuid: string
  type: string
  // ... resto de campos
}

export interface AdminBankAccount {
  id: string
  uuid: string
  bank_name: string
  // ... resto de campos
}
```

**Impacto**: Tipos reutilizables, mejor IntelliSense

---

## Fase 3: Optimización de Stores (Sin cambios visuales)

### 3.1 Refactorizar uso de composables en stores

Actualizar stores para usar los nuevos composables **sin cambiar la API pública del store**.

Ejemplo en `applicant.ts`:

```typescript
// ANTES
const loadApplicant = async () => {
  isLoading.value = true
  try {
    const response = await api.get('/applicant')
    applicant.value = response.data.data
  } catch (error) {
    console.error('Error:', error)
  } finally {
    isLoading.value = false
  }
}

// DESPUÉS (mismo comportamiento)
const { execute: loadApplicant, isLoading } = useAsyncAction(
  () => api.get('/applicant').then(r => {
    applicant.value = r.data.data
    return r.data.data
  })
)
```

**Impacto**: Reduce duplicación, mantiene API idéntica

---

## Fase 4: Lazy Loading (Sin cambios visuales)

### 4.1 Actualizar router para vistas admin

```typescript
// src/router/index.ts
// ANTES
import AdminApplicationDetail from '@/views/admin/panel/AdminApplicationDetail.vue'

// DESPUÉS
const AdminApplicationDetail = () => import('@/views/admin/panel/AdminApplicationDetail.vue')
```

**Impacto**: Reduce bundle inicial ~100kb, misma funcionalidad

---

## Orden de Ejecución

| Fase | Tarea | Archivos | Riesgo |
|------|-------|----------|--------|
| 1.1 | Crear useAsyncState | +1 nuevo | Bajo |
| 1.2 | Crear useAsyncAction | +1 nuevo | Bajo |
| 1.3 | Crear useValidation | +1 nuevo | Bajo |
| 2.1 | Crear types/admin.ts | +1 nuevo | Bajo |
| 3.1 | Refactorizar applicant.ts | 1 modificado | Medio |
| 3.2 | Refactorizar application.ts | 1 modificado | Medio |
| 4.1 | Lazy loading en router | 1 modificado | Bajo |

---

## Verificación

Después de cada fase:
1. `npm run type-check` - Sin errores TypeScript
2. `npm run build` - Build exitoso
3. Verificación visual manual - Sin cambios en UI

---

## Archivos NO Modificados (Preservar comportamiento)

- ❌ Componentes visuales (templates)
- ❌ Estilos CSS/Tailwind
- ❌ Props y eventos públicos
- ❌ Rutas y navegación
- ❌ Lógica de negocio visible al usuario
