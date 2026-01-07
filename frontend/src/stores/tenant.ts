import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/services/api'
import type { Tenant, Product } from '@/types'

interface TenantConfigResponse {
  tenant: Tenant
  products: Product[]
}

export const useTenantStore = defineStore('tenant', () => {
  // State
  const tenant = ref<Tenant | null>(null)
  const products = ref<Product[]>([])
  const isLoading = ref(false)
  const isLoaded = ref(false)
  const error = ref<string | null>(null)

  // Getters
  const branding = computed(() => tenant.value?.branding ?? null)
  const name = computed(() => tenant.value?.name ?? '')
  const slug = computed(() => tenant.value?.slug ?? '')
  const isActive = computed(() => tenant.value?.is_active ?? false)
  const settings = computed(() => tenant.value?.settings ?? null)

  const activeProducts = computed(() =>
    products.value.filter(p => p.is_active)
  )

  const getProductById = computed(() => (id: string) =>
    products.value.find(p => p.id === id)
  )

  // Actions
  const loadConfig = async () => {
    if (isLoaded.value) return

    isLoading.value = true
    error.value = null

    try {
      const response = await api.get<TenantConfigResponse>('/config')
      tenant.value = response.data.tenant
      products.value = response.data.products
      isLoaded.value = true
    } catch (e) {
      error.value = 'Error al cargar la configuración'
      console.error('Failed to load tenant config:', e)
    } finally {
      isLoading.value = false
    }
  }

  const applyTheme = () => {
    if (!branding.value) return

    const root = document.documentElement
    const b = branding.value

    // Set CSS custom properties
    root.style.setProperty('--tenant-primary', b.primary_color)
    root.style.setProperty('--tenant-secondary', b.secondary_color)
    root.style.setProperty('--tenant-accent', b.accent_color)
    root.style.setProperty('--tenant-radius', b.border_radius)

    // Update favicon
    const favicon = document.querySelector<HTMLLinkElement>("link[rel~='icon']")
    if (favicon && b.favicon_url) {
      favicon.href = b.favicon_url
    }

    // Update title
    document.title = name.value || 'Solicitud de Crédito'
  }

  const reset = () => {
    tenant.value = null
    products.value = []
    isLoaded.value = false
    error.value = null
  }

  return {
    // State
    tenant,
    products,
    isLoading,
    isLoaded,
    error,
    // Getters
    branding,
    name,
    slug,
    isActive,
    settings,
    activeProducts,
    getProductById,
    // Actions
    loadConfig,
    applyTheme,
    reset
  }
})
