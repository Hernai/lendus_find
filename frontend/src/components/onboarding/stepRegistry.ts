import { defineAsyncComponent, type Component } from 'vue'

/**
 * Registry de renderers de paso del onboarding dinámico — variant-aware y extensible
 * por tenant. Antes vivía inline en OnboardingStepRenderer.vue (que, al ser un `.vue`,
 * no puede ser un punto de registro para otros módulos).
 *
 * Resolución (ver resolveStepComponent): variante del tenant → default del tenant →
 * variante base → default base → PendingStepRenderer.
 *
 * Extensibilidad por tenant (inversión de dependencia, mismo patrón que
 * `registerStaffAuth` en services/staffAuthGateway.ts): el core NO conoce tenants;
 * cada tenant registra sus pantallas custom vía `registerTenantSteps` desde
 * `tenants/<slug>/onboarding.register.ts`, importado eager por `tenants/registerAll.ts`.
 *
 * Los renderers se cargan async (defineAsyncComponent) para code-splitting: el bundle
 * de un onboarding pesado con KYC no se baja si el paso es trivial.
 */
export interface StepEntry {
  /** Diseño por defecto del tipo de paso. */
  default: Component
  /** Diseños alternativos, elegidos por `step.variant`. */
  variants?: Record<string, Component>
}

/** Renderers base (los 11 tipos declarativos). Envueltos en `{ default }`. */
const baseRegistry: Record<string, StepEntry> = {
  select: { default: defineAsyncComponent(() => import('@/components/onboarding/steps/SelectStepRenderer.vue')) },
  state_city: { default: defineAsyncComponent(() => import('@/components/onboarding/steps/StateCityStepRenderer.vue')) },
  number_select: { default: defineAsyncComponent(() => import('@/components/onboarding/steps/NumberSelectStepRenderer.vue')) },
  review: { default: defineAsyncComponent(() => import('@/components/onboarding/steps/ReviewStepRenderer.vue')) },
  references: { default: defineAsyncComponent(() => import('@/components/onboarding/steps/ReferencesStepRenderer.vue')) },
  bank_account: { default: defineAsyncComponent(() => import('@/components/onboarding/steps/BankAccountStepRenderer.vue')) },
  kyc_ine: { default: defineAsyncComponent(() => import('@/components/onboarding/steps/KycIneStepRenderer.vue')) },
  kyc_selfie: { default: defineAsyncComponent(() => import('@/components/onboarding/steps/KycSelfieStepRenderer.vue')) },
  review_full: { default: defineAsyncComponent(() => import('@/components/onboarding/steps/ReviewStepRenderer.vue')) },
  personal_data: { default: defineAsyncComponent(() => import('@/components/onboarding/steps/PersonalDataStepRenderer.vue')) },
  address: { default: defineAsyncComponent(() => import('@/components/onboarding/steps/AddressStepRenderer.vue')) },
}

/** Fallback para tipos/pasos no registrados (custom sin wiring, o typo en el seed). */
const PendingStepRenderer = defineAsyncComponent(
  () => import('@/components/onboarding/steps/PendingStepRenderer.vue'),
)

/** Overrides por tenant: slug → (type → StepEntry). Inyectado vía registerTenantSteps. */
const tenantRegistry: Record<string, Record<string, StepEntry>> = {}

/**
 * Registra pasos/variantes custom de un tenant. Lo llama
 * `tenants/<slug>/onboarding.register.ts` (importado eager por `tenants/registerAll.ts`).
 * Es aditivo: fusiona con lo ya registrado para ese tenant.
 */
export function registerTenantSteps(slug: string, entries: Record<string, StepEntry>): void {
  tenantRegistry[slug] = { ...tenantRegistry[slug], ...entries }
}

/**
 * Resuelve el componente-renderer para un paso.
 * Orden: variante del tenant → default del tenant → variante base → default base →
 * PendingStepRenderer.
 */
export function resolveStepComponent(
  type: string,
  variant?: string,
  slug?: string | null,
): Component {
  const tenantEntry = slug ? tenantRegistry[slug]?.[type] : undefined
  const baseEntry = baseRegistry[type]

  if (variant) {
    const variantComp = tenantEntry?.variants?.[variant] ?? baseEntry?.variants?.[variant]
    if (variantComp) return variantComp
  }

  return tenantEntry?.default ?? baseEntry?.default ?? PendingStepRenderer
}
