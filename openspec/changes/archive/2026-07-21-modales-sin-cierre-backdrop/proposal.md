## Why

En los modales de diálogo/formulario/confirmación, un clic accidental en el backdrop los
cierra y se pierde lo capturado (o dispara una acción no intencionada, p. ej. un cambio de
estatus). Deben cerrarse solo con ✕/Cancelar. Los bottom-sheets y selectores conservan el
cierre por clic afuera, que es la UX estándar de un picker.

## What Changes

- Quitar `@click.self` / el `@click` del backdrop en los **modales de diálogo** de la app
  (formularios, confirmaciones, editores) — 16 componentes en common, admin y applicant. Se
  cierran solo con ✕/Cancelar (o su acción explícita).
- **Conservados intactos** (siguen cerrando al tocar afuera): bottom-sheets y selectores
  (`AppBottomSheet`, `OnboardingSheet`, `LoanExtensionSheet`, sheets de los step renderers
  banco/estado/número/INE, y las hojas informativas de `WelcomeConsentView`).
- **No tocados**: `TenantSelectorModal` (picker forzoso sin cierre) y `CounterOfferModal`
  (ya evitaba el backdrop-close a propósito).

## Non-goals

- No crear un componente `BaseModal` compartido (fix puntual archivo por archivo).
- No cambiar el comportamiento de bottom-sheets ni selectores.

## Capabilities

### New Capabilities
- `modales-sin-cierre-backdrop`: los modales de diálogo de la aplicación no se cierran al
  hacer clic en el backdrop; solo con ✕/Cancelar. Los bottom-sheets/selectores no cambian.

### Modified Capabilities
<!-- Ninguna. -->

## Impact

- **Frontend, 16 componentes:** `AddBankAccountModal`, `common/AppConfirmModal`,
  `admin/ConfirmModal`, `application-detail/{StatusChangeModal, IneVerificationModal,
  BankAccountsSection, AssignAnalystModal, ReferenceVerifyModal}`,
  `notification-templates/SendTestModal`, `admin/views/panel/{AdminDecisionEngine,
  AdminUsers, AdminWebhooks, AdminTenants}`, `loans/LoanPayModal`, `dashboard/DashboardView`,
  `onboarding/DynamicOnboardingView` (solo sus overlays de confirmación, no el sheet de pasos).
- Cada modal conserva un cierre explícito (✕/Cancelar). Sin backend.
