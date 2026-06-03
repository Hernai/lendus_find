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

export type { TenantConfig, AuthMethod, TenantLandingComponent } from './_types'
