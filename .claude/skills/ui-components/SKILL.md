# Skill: UI Components — Componentes Reutilizables

Catálogo completo de componentes UI. **Siempre revisar antes de crear componentes nuevos.**

El proyecto tiene 3 capas de componentes:

| Capa | Ruta | Público | Descripción |
|------|------|---------|-------------|
| **Common** | `@/components/common/` | User + Admin | Primitivos compartidos (inputs, botones, modales) |
| **Applicant** | `@/components/` + `kyc/` + `simulator/` + `layout/` | Solo user | UI del solicitante (cuentas, documentos, KYC, simulador) |
| **Admin** | `@/components/admin/` | Solo admin | Panel de administración (tablas, galerías, editores) |

---

## 1. Common — Primitivos compartidos (`@/components/common`)

Importar desde barrel: `import { AppButton, AppInput, ... } from '@/components/common'`

### Formularios

| Componente | Descripción | Props clave |
|------------|-------------|-------------|
| **AppInput** | Input con validación, prefix/suffix, checkmark | `v-model`, `label`, `error`, `type`, `uppercase`, `prefix` |
| **AppSelect** | Select con BottomSheet en móvil | `v-model`, `options: {value,label}[]`, `label`, `placeholder` |
| **SearchableSelect** | Select2 con búsqueda (admin) | `v-model` (null=vacío), `options: {value,label}[]`, `placeholder` |
| **AppRadioGroup** | Radio buttons horizontal/vertical | `v-model`, `options: {value,label,description?}[]`, `inline` |
| **AppDatePicker** | Datepicker nativo + rueda en móvil | `v-model` (YYYY-MM-DD), `min`, `max` |
| **AppOtpInput** | Input OTP de N dígitos | `v-model`, `length`, emit `complete` |
| **AppSlider** | Slider con gradiente y valor formateado | `v-model`, `min`, `max`, `step`, `formatValue` |
| **AppSignaturePad** | Canvas para firma (touch + mouse) | `v-model` (PNG dataURL) |

**AppSelect vs SearchableSelect**:
- `AppSelect` → formularios mobile-first (onboarding, perfil). Abre BottomSheet en móvil.
- `SearchableSelect` → filtros de admin con muchas opciones. Tiene búsqueda, teclado, botón limpiar (X). Emite `null` al limpiar.

### UI General

| Componente | Descripción | Props clave |
|------------|-------------|-------------|
| **AppButton** | Botón con variantes y loading | `variant` (primary/secondary/outline/ghost/danger), `size`, `loading` |
| **AppProgressBar** | Barra "Paso X de Y" | `current`, `total`, `height` (sm/md/lg) |
| **AppStatusBadge** | Badge de status con 20+ estados | `status` (DRAFT, APPROVED, REJECTED...), `size` (xs/sm/md) |
| **LockedField** | Campo readonly verificado (KYC) | `value`, `verified`, `format` (curp/rfc/phone/date) |
| **ToastContainer** | Contenedor global de toasts | Montar una vez en App.vue, usar con `useToast()` |

### Modales y Overlays

| Componente | Descripción | Props clave |
|------------|-------------|-------------|
| **AppConfirmModal** | Modal confirmación (user-facing) | `show`, `title`, `variant`, `selectOptions[]`, `commentLabel` |
| **AppBottomSheet** | Panel slide-up (móvil) | `v-model` (visible), `title`. Swipe-to-close. |

---

## 2. Applicant — Componentes del solicitante

Componentes específicos para el flujo del usuario/solicitante. NO se usan en admin.

### Cuentas bancarias (`@/components/`)

| Componente | Descripción | Props clave | Emits |
|------------|-------------|-------------|-------|
| **AddBankAccountModal** | Modal agregar cuenta con validación CLABE | — | `close`, `saved` |
| **BankAccountCard** | Tarjeta de cuenta con acciones | `account: V2ProfileBankAccount` | `set-primary`, `delete` |

### Documentos (`@/components/`)

| Componente | Descripción | Props clave | Emits |
|------------|-------------|-------------|-------|
| **DocumentPreview** | Vista previa con lazy load | `document`, `showStatus?`, `canReplace?` | `replace` |
| **ImageViewer** | Modal fullscreen de imagen | `src`, `isVerified?`, `canChange?` | `close`, `change` |

### KYC (`@/components/kyc/`)

| Componente | Descripción | Props clave | Emits |
|------------|-------------|-------------|-------|
| **IneCapture** | Captura INE frente/reverso (webcam o upload) | `side: 'front'\|'back'`, `active?` | `captured`, `retake` |
| **SelfieCapture** | Selfie con face match (cámara frontal) | `active?`, `isValidated?` | `captured`, `retake` |

### Simulador (`@/components/simulator/`)

| Componente | Descripción | Props clave | Emits |
|------------|-------------|-------------|-------|
| **SimulatorCard** | Simulador crédito con sliders y cálculo CAT | `compact?`, `product?`, `inOnboarding?` | `continue` |

### Layout (`@/components/layout/`)

| Componente | Descripción | Props |
|------------|-------------|-------|
| **AppHeader** | Header público con logo + menú + CTA | Sin props (usa stores) |
| **AppFooter** | Footer con branding dinámico del tenant | Sin props (usa stores) |

---

## 3. Admin — Componentes del panel (`@/components/admin`)

Importar desde barrel: `import { AdminDataTable, ConfirmModal } from '@/components/admin'`

### Core

| Componente | Descripción | Props clave |
|------------|-------------|-------------|
| **ConfirmModal** | Modal confirmación admin (icon + select + textarea) | `show`, `title`, `icon`, `iconColor`, `confirmColor`, `selectOptions[]` |
| **AdminDataTable** | Tabla con paginación, selección, celdas custom | `items`, `columns: TableColumn[]`, `clickable`, `selectable`, `stickyHeader` |
| **AdminDocumentGallery** | Galería docs con aprobación/rechazo | `applicationId`, `documents[]`, `canReview?` |
| **TenantBrandingEditor** | Editor branding con preview live | `v-model: Branding`, `tenant` |
| **TenantSwitcher** | Dropdown cambio de tenant (super admin) | Sin props (usa stores) |

**AppConfirmModal vs ConfirmModal**:
- `AppConfirmModal` (common) → flujos de applicant, usa `variant` para estilo
- `ConfirmModal` (admin) → panel admin, tiene `icon`, `iconColor`, `confirmColor`, `selectOptions`, `commentLabel`

### Detalle de solicitud (`@/components/admin/application-detail/`)

9 subcomponentes para la vista de detalle:

| Componente | Descripción |
|------------|-------------|
| **ApplicationHeader** | Header con folio, status, acciones |
| **LoanSummaryCards** | Tarjetas resumen del crédito |
| **ApplicantDataSection** | Datos personales del solicitante |
| **BankAccountsSection** | Cuentas bancarias registradas |
| **ReferencesSection** | Referencias personales |
| **CompletenessIndicator** | Indicador de completitud |
| **TimelineSection** | Línea de tiempo de eventos |
| **NotesSection** | Notas/comentarios del analista |
| **ApiLogsSection** | Logs de llamadas API |
| **TabNavigation** | Navegación por pestañas |
| **VerifiableField** | Campo con badge de verificación |

### Notificaciones (`@/components/admin/notification-templates/`)

| Componente | Descripción | Props clave |
|------------|-------------|-------------|
| **HtmlEditor** | Editor HTML con 3 vistas (visual/código/preview) | `v-model`, `htmlBody`, `availableVariables`, `channel` |
| **NotificationPreview** | Preview en 4 canales (email/sms/wa/in-app) | `body`, `htmlBody?`, `channel`, `variables` |
| **SendTestModal** | Modal envío de prueba | `show`, `template: NotificationTemplate` |

### Tipos admin (`@/components/admin/types.ts`)

```typescript
interface TableColumn<T = unknown> {
  key: string
  label: string
  width?: string           // Clase Tailwind ('w-32')
  align?: 'left' | 'center' | 'right'
  hideOnMobile?: boolean
  format?: (row: T) => string
  sortable?: boolean
}
```

---

## Composables UI

### useToast

```typescript
const toast = useToast()
toast.success('Guardado')      // verde, 3s
toast.error('Error al guardar') // rojo, 5s
toast.warning('Atención')      // amarillo
toast.info('Información')      // azul
```

### useModal / useConfirmModal

```typescript
// Modal CRUD completo
const modal = useModal<Product>({
  onOpen: (data) => { /* pre-fill */ },
  onSuccess: () => loadData(),
})
modal.openCreate()         // isEditMode = false
modal.openEdit(product)    // isEditMode = true, editingData = product
modal.startSubmit()        // isSubmitting = true
modal.handleSuccess()      // cierra + onSuccess

// Confirmación simple (delete, etc.)
const confirm = useConfirmModal({
  onConfirm: async () => { await api.delete(id) },
})
confirm.open()
```

---

## Reglas

1. **Barrel imports**: `import { X } from '@/components/common'` o `'@/components/admin'`
2. **No duplicar**: Revisar este catálogo antes de crear componentes
3. **Mobile-first (applicant)** → `AppSelect`, `AppBottomSheet`, `AppConfirmModal`
4. **Desktop-first (admin)** → `SearchableSelect`, `ConfirmModal`, `AdminDataTable`
5. **Nuevos componentes common** → agregar export en `@/components/common/index.ts`
6. **Nuevos componentes admin** → agregar export en `@/components/admin/index.ts`
