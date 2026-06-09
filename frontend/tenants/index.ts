import type { TenantConfig } from './_types'
import demo from './demo.tenant'
import moneycapital from './moneycapital.tenant'
import finatea from './finatea.tenant'

/**
 * Mapa de tenants disponibles indexado por slug. Se importa estáticamente
 * desde el router y otras capas que necesiten la config front-side
 * (landingComponent, auth.methods) sin esperar al backend.
 *
 * Para agregar un tenant nuevo: crea `<slug>.tenant.ts` y agrégalo aquí.
 */
export const tenants: Record<string, TenantConfig> = {
  [demo.slug]: demo,
  [moneycapital.slug]: moneycapital,
  [finatea.slug]: finatea,
}

export const tenantSlugs = Object.keys(tenants)

export function getTenantConfig(slug: string | null | undefined): TenantConfig | null {
  if (!slug) return null
  return tenants[slug] ?? null
}

export type {
  TenantConfig,
  AuthMethod,
  TenantLandingComponent,
  TenantCustomizations,
} from './_types'

/**
 * Devuelve el set de personalizaciones hard-coded del tenant. Las llaves
 * pobladas indican qué pedazos del frontend NO respetan el branding del
 * admin (son componentes Vue dedicados, requieren release para cambiar).
 * Las llaves ausentes/null significan que ese pedazo usa el flujo
 * estándar y respeta el admin.
 *
 * Útil para el admin: mostrar al SOFOM qué cosas son configurables y
 * cuáles requieren release del dev team.
 */
export function getTenantCustomizations(slug: string | null | undefined): {
  landing: string | null
  authFlow: string | null
  simulator: string | null
  dashboard: string | null
} {
  const c = getTenantConfig(slug)?.custom
  return {
    landing: c?.landing ?? null,
    authFlow: c?.authFlow ?? null,
    simulator: c?.simulator ?? null,
    dashboard: c?.dashboard ?? null,
  }
}
