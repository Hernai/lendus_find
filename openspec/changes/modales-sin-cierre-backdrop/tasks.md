## 1. Quitar backdrop-close en modales de diálogo

- [x] 1.1 Inventariar los overlays con `@click.self` (24) y clasificarlos: modal de diálogo vs bottom-sheet/selector.
- [x] 1.2 Quitar `@click.self` / `@click` de backdrop en los 16 modales de diálogo: `AddBankAccountModal`, `common/AppConfirmModal`, `admin/ConfirmModal`, `application-detail/{StatusChangeModal, IneVerificationModal, BankAccountsSection, AssignAnalystModal, ReferenceVerifyModal}`, `notification-templates/SendTestModal`, `admin/views/panel/{AdminDecisionEngine, AdminUsers, AdminWebhooks, AdminTenants}`, `loans/LoanPayModal`, `dashboard/DashboardView`, `onboarding/DynamicOnboardingView` (solo overlays de confirmación).
- [x] 1.3 Verificar que cada modal limpiado conserva ✕/Cancelar (ninguno quedó sin cierre).
- [x] 1.4 Conservar intactos sheets/selectores (`AppBottomSheet`, `OnboardingSheet`, `LoanExtensionSheet`, sheets de step renderers, `WelcomeConsentView`), `TenantSelectorModal` y `CounterOfferModal`.

## 2. Verificación

- [x] 2.1 `npm run type-check` sin errores.
- [x] 2.2 Revisar el diff: solo se removieron handlers de backdrop; ningún sheet en la lista de modificados; cierre explícito presente en cada modal.
- [ ] 2.3 PENDIENTE-SMOKE (batch): clic afuera de un modal de diálogo (p. ej. LoanPay / AddBankAccount) no lo cierra; clic afuera de un selector (banco/estado) sí lo cierra.
