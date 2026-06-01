# Plan de Refactorización Frontend

## Resumen Ejecutivo

**Puntuación actual: 4.7/10**

El frontend tiene problemas estructurales que afectan mantenibilidad, testing y escalabilidad. Este plan prioriza cambios por impacto/esfuerzo.

---

## Fase 1: Quick Wins (Impacto Alto, Esfuerzo Bajo)

### 1.1 Eliminar Servicios V1 Legacy
**Archivos a eliminar:**
- `services/admin.service.ts` (510 líneas) - Ya migrado a V2
- `services/application.service.ts` - Ya migrado a V2
- `services/applicant.service.ts` - Ya migrado a V2

**Acción:** Buscar imports de estos archivos y migrar a `v2.*`

### 1.2 Estandarizar Manejo de Errores en Stores
**Problema:** Todos los stores repiten `isLoading/try-catch/finally`
**Solución:** Usar `useAsyncAction` que ya existe

```typescript
// ANTES (stores/application.ts línea 70-90)
const loadApplications = async () => {
  isLoading.value = true
  try {
    const response = await api.get('/applications')
    applications.value = response.data.data
  } catch (error) {
    // manual handling
  } finally {
    isLoading.value = false
  }
}

// DESPUÉS
const { execute: loadApplications, isLoading, error } = useAsyncAction(
  async () => {
    const response = await v2.applicant.application.list()
    applications.value = response.data
  }
)
```

**Stores a refactorizar:**
- [ ] `stores/auth.ts`
- [ ] `stores/application.ts`
- [ ] `stores/applicant.ts`
- [ ] `stores/onboarding.ts`
- [ ] `stores/kyc.ts`

### 1.3 Estandarizar Logging
**Problema:** Mezcla de `console.log`, `console.error`, `logger.child()`
**Solución:** Todo usa `logger` centralizado

```typescript
// ANTES
console.log('[Step1] Applicant ID:', applicantId)
console.error('Failed to load:', e)

// DESPUÉS
import { logger } from '@/utils/logger'
const log = logger.child('Step1')
log.debug('Applicant ID:', applicantId)
log.error('Failed to load:', e)
```

---

## Fase 2: Refactor Steps de Onboarding (DRY)

### 2.1 Migrar Step1-3 a useStepForm
**Problema:** Step1-3 tienen 631-894 líneas vs Step4 con 108 líneas

| Step | Líneas Actual | Líneas Objetivo |
|------|---------------|-----------------|
| Step1PersonalData | 631 | ~150 |
| Step2Identification | 894 | ~200 |
| Step3Address | 892 | ~180 |

**Patrón a seguir (Step4Employment):**
```typescript
const { form, errors, submitError, handleSubmit, isSaving, init } = useStepForm({
  stepKey: 'employment',
  initialForm: { ... },
  validate: (form) => { ... },
  submit: async (form) => { ... }
})
```

### 2.2 Extraer Sub-componentes de Step2
**Step2Identification tiene demasiadas responsabilidades:**
- Formulario de datos personales
- Validación CURP (compleja)
- Validación RFC (compleja)
- Captura de INE

**Dividir en:**
- `Step2PersonalInfo.vue` (~100 líneas)
- `Step2CurpValidation.vue` (~150 líneas)
- `Step2RfcValidation.vue` (~100 líneas)
- `Step2IneCapture.vue` (ya existe como componente)

---

## Fase 3: Dividir Componentes Grandes (SRP)

### 3.1 AdminApplicationDetail.vue (3954 líneas → 6-7 componentes)

**Responsabilidades actuales (68 refs!):**
1. Header + Status
2. Datos del solicitante
3. Galería de documentos
4. Referencias
5. Cuentas bancarias
6. Notas
7. Timeline/Historial
8. WebSocket real-time

**Nueva estructura:**
```
/views/admin/panel/AdminApplicationDetail.vue (~300 líneas - orquestador)
/components/admin/application-detail/
  ├── ApplicationHeader.vue (ya existe)
  ├── ApplicantInfo.vue (nuevo)
  ├── LoanDetails.vue (nuevo)
  ├── DocumentsSection.vue (nuevo - wrapper de AdminDocumentGallery)
  ├── ReferencesSection.vue (ya existe)
  ├── BankAccountsSection.vue (ya existe)
  ├── NotesSection.vue (nuevo)
  ├── TimelineSection.vue (nuevo)
  └── index.ts
```

### 3.2 Stores Grandes

**auth.ts (972 líneas)** dividir en:
- `stores/auth/session.ts` - Login, logout, tokens
- `stores/auth/permissions.ts` - Permisos y roles
- `stores/auth/index.ts` - Re-exporta todo

**kyc.ts (1547 líneas)** dividir en:
- `stores/kyc/validation.ts` - Validaciones CURP/RFC/INE
- `stores/kyc/capture.ts` - Captura de documentos
- `stores/kyc/state.ts` - Estado general
- `stores/kyc/index.ts`

---

## Fase 4: Consistencia de Tipos

### 4.1 Consolidar Tipos Duplicados
**Problema:** `Application` definido en 3 lugares

**Solución:**
```typescript
// types/v2/index.ts - ÚNICO lugar
export interface V2Application { ... }

// En componentes, usar alias si necesario
import type { V2Application as Application } from '@/types/v2'
```

### 4.2 Eliminar Tipos Locales Redundantes
Buscar `interface Application {` en archivos `.vue` y reemplazar por imports de `@/types/v2`

---

## Fase 5: Testing y DI

### 5.1 Hacer Servicios Inyectables
```typescript
// ANTES (hard dependency)
import { v2 } from '@/services/v2'

// DESPUÉS (inyectable via provide/inject)
const applicationService = inject('applicationService', v2.staff.application)
```

### 5.2 Crear Mocks para Testing
```
/tests/mocks/
  ├── services/
  │   └── v2.mock.ts
  └── stores/
      └── auth.mock.ts
```

---

## Orden de Ejecución

| Prioridad | Tarea | Impacto | Esfuerzo | Archivos |
|-----------|-------|---------|----------|----------|
| 1 | Eliminar servicios V1 | Alto | Bajo | 3 archivos |
| 2 | Estandarizar logging | Medio | Bajo | ~20 archivos |
| 3 | useAsyncAction en stores | Alto | Medio | 5 stores |
| 4 | Migrar Step1 a useStepForm | Alto | Medio | 1 archivo |
| 5 | Migrar Step2 a useStepForm | Alto | Medio | 1 archivo |
| 6 | Migrar Step3 a useStepForm | Alto | Medio | 1 archivo |
| 7 | Dividir AdminApplicationDetail | Alto | Alto | 1→7 archivos |
| 8 | Dividir auth.ts store | Medio | Medio | 1→3 archivos |
| 9 | Dividir kyc.ts store | Medio | Alto | 1→4 archivos |
| 10 | Consolidar tipos | Medio | Bajo | ~10 archivos |

---

## Métricas de Éxito

| Métrica | Antes | Objetivo |
|---------|-------|----------|
| Líneas en AdminApplicationDetail | 3954 | <500 |
| Líneas en Step1-3 (promedio) | 806 | <200 |
| Stores usando useAsyncAction | 0/5 | 5/5 |
| Archivos con console.log | ~15 | 0 |
| Servicios V1 activos | 3 | 0 |

---

## Notas

- **No romper funcionalidad**: Cada cambio debe pasar TypeScript + build
- **Commits atómicos**: Un commit por sub-tarea
- **Tests**: Verificar que la app funciona después de cada fase
