/**
 * Carga EAGER los registros de pasos custom de cada tenant para el onboarding.
 *
 * Cada tenant con pantallas o variantes propias crea
 * `tenants/<slug>/onboarding.register.ts`, que llama
 * `registerTenantSteps('<slug>', { ... })` (ver
 * `src/components/onboarding/stepRegistry.ts`). Este barrel los descubre e importa
 * automáticamente con `import.meta.glob` (eager) — NO hay que editar este archivo al
 * agregar un tenant nuevo; basta con crear su `onboarding.register.ts`.
 *
 * Se importa UNA sola vez al boot (`src/router/index.ts`, con un side-effect import)
 * para que los `registerTenantSteps` corran ANTES de que el onboarding resuelva
 * componentes. Es el mismo patrón de inversión de dependencia que `registerStaffAuth`
 * del módulo admin: el core no conoce tenants; los tenants se inyectan acá.
 *
 * Hoy no hay ningún `onboarding.register.ts` (ningún tenant tiene aún variantes o
 * pasos custom) → el glob no matchea nada y el registro queda vacío (se usan los
 * renderers base). Cuando exista el primero, se cargará solo.
 */
export const tenantOnboardingRegistrations = import.meta.glob(
  './*/onboarding.register.ts',
  { eager: true },
)
