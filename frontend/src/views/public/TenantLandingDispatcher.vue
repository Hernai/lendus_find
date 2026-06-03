<script setup lang="ts">
import { computed, defineAsyncComponent, type Component } from 'vue'
import { useRoute } from 'vue-router'
import { getTenantConfig, type TenantLandingComponent } from '@tenants'
import { detectTenantSlug } from '@/utils/tenant'
import LandingView from './LandingView.vue'

/**
 * Mapa de identificadores de landing → import dinámico del componente Vue.
 * El tenant declara su `landingComponent` en `tenants/<slug>.tenant.ts`; este
 * dispatcher lo resuelve a runtime. Si el tenant no declara ninguno (o el
 * identificador no está en el mapa), cae a la landing genérica.
 */
const LANDING_COMPONENTS: Record<TenantLandingComponent, Component> = {
  GenericLanding: LandingView,
  DemoLanding: defineAsyncComponent(() => import('./demo/DemoLanding.vue')),
  MoneyCapitalLanding: defineAsyncComponent(
    () => import('./moneycapital/MoneyCapitalLanding.vue'),
  ),
  FinateaLanding: defineAsyncComponent(() => import('./finatea/FinateaLanding.vue')),
}

const route = useRoute()

/**
 * Resuelve el slug del tenant en este orden:
 * 1. `route.params.tenant` (cuando la ruta es `/:tenant`, path-based)
 * 2. `detectTenantSlug()` — usa subdominio (`moneycapital.lendus.app`)
 *    o path fallback. Útil cuando el dispatcher se monta en `/` sin params.
 */
const tenantSlug = computed(() => {
  const fromPath = route.params.tenant as string | undefined
  if (fromPath) return fromPath
  return detectTenantSlug() || null
})

const resolvedComponent = computed<Component>(() => {
  const config = getTenantConfig(tenantSlug.value)
  const key = config?.landingComponent
  if (key && LANDING_COMPONENTS[key]) {
    return LANDING_COMPONENTS[key]
  }
  return LandingView
})
</script>

<template>
  <component :is="resolvedComponent" />
</template>
