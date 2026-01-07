import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Tenant, Branding, Product, TenantConfig } from '@/types'

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
      // TODO: Replace with actual API call
      // const response = await api.get<TenantConfig>('/api/config')
      // tenant.value = response.data.tenant
      // products.value = response.data.products

      // Mock data for development
      tenant.value = {
        id: 'tenant-001',
        name: 'Financiera Lendus',
        slug: 'lendus',
        branding: {
          primary_color: '#6366f1',
          secondary_color: '#10b981',
          accent_color: '#f59e0b',
          logo_url: '/logo.svg',
          favicon_url: '/favicon.ico',
          font_family: 'Inter, sans-serif',
          border_radius: '12px'
        },
        webhook_config: {
          url: 'https://api.lendus.mx/webhooks',
          secret_key: 'whsec_xxx',
          retry_count: 3,
          timeout_seconds: 30,
          events: ['application.approved', 'application.rejected']
        },
        settings: {
          otp_provider: 'twilio',
          kyc_provider: 'mati',
          max_loan_amount: 500000,
          min_loan_amount: 5000,
          currency: 'MXN',
          timezone: 'America/Mexico_City'
        },
        is_active: true,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      }

      products.value = [
        {
          id: 'prod-001',
          tenant_id: 'tenant-001',
          name: 'Crédito Personal',
          type: 'PERSONAL',
          description: 'Préstamo para gastos personales, emergencias o proyectos',
          icon: 'user',
          rules: {
            min_amount: 5000,
            max_amount: 500000,
            min_term_months: 3,
            max_term_months: 48,
            annual_rate: 45.0,
            opening_commission: 2.5,
            amortization_type: 'FRENCH',
            payment_frequencies: ['WEEKLY', 'BIWEEKLY', 'MONTHLY'],
            min_age: 18,
            max_age: 70,
            min_income: 8000
          },
          required_docs: [
            { type: 'INE_FRONT', required: true, description: 'INE/IFE vigente (frente)' },
            { type: 'INE_BACK', required: true, description: 'INE/IFE vigente (reverso)' },
            { type: 'PROOF_ADDRESS', required: true, description: 'Comprobante de domicilio (máx 3 meses)' },
            { type: 'PROOF_INCOME', required: false, description: 'Comprobante de ingresos' }
          ],
          extra_fields: [],
          is_active: true
        },
        {
          id: 'prod-002',
          tenant_id: 'tenant-001',
          name: 'Crédito Nómina',
          type: 'PAYROLL',
          description: 'Descuento directo de tu sueldo con las mejores tasas',
          icon: 'briefcase',
          rules: {
            min_amount: 10000,
            max_amount: 300000,
            min_term_months: 6,
            max_term_months: 36,
            annual_rate: 28.0,
            opening_commission: 1.5,
            amortization_type: 'FRENCH',
            payment_frequencies: ['BIWEEKLY', 'MONTHLY'],
            min_age: 21,
            max_age: 65,
            min_income: 12000
          },
          required_docs: [
            { type: 'INE_FRONT', required: true, description: 'INE/IFE vigente (frente)' },
            { type: 'INE_BACK', required: true, description: 'INE/IFE vigente (reverso)' },
            { type: 'PROOF_ADDRESS', required: true, description: 'Comprobante de domicilio (máx 3 meses)' },
            { type: 'PAYROLL_STUBS', required: true, description: 'Últimos 3 recibos de nómina' }
          ],
          extra_fields: [],
          is_active: true
        },
        {
          id: 'prod-003',
          tenant_id: 'tenant-001',
          name: 'Crédito PyME',
          type: 'SME',
          description: 'Capital de trabajo para hacer crecer tu negocio',
          icon: 'building',
          rules: {
            min_amount: 50000,
            max_amount: 2000000,
            min_term_months: 6,
            max_term_months: 60,
            annual_rate: 35.0,
            opening_commission: 3.0,
            amortization_type: 'FRENCH',
            payment_frequencies: ['MONTHLY'],
            min_age: 25,
            max_age: 70,
            min_income: 30000
          },
          required_docs: [
            { type: 'INE_FRONT', required: true, description: 'INE/IFE vigente (frente)' },
            { type: 'INE_BACK', required: true, description: 'INE/IFE vigente (reverso)' },
            { type: 'RFC_CONSTANCIA', required: true, description: 'Constancia de situación fiscal' },
            { type: 'BANK_STATEMENTS', required: true, description: 'Últimos 6 estados de cuenta' },
            { type: 'ACTA_CONSTITUTIVA', required: false, description: 'Acta constitutiva (personas morales)' }
          ],
          extra_fields: [],
          is_active: true
        },
        {
          id: 'prod-004',
          tenant_id: 'tenant-001',
          name: 'Arrendamiento',
          type: 'LEASING',
          description: 'Adquiere maquinaria, vehículos o equipo para tu empresa',
          icon: 'truck',
          rules: {
            min_amount: 100000,
            max_amount: 5000000,
            min_term_months: 12,
            max_term_months: 60,
            annual_rate: 22.0,
            opening_commission: 2.0,
            amortization_type: 'FRENCH',
            payment_frequencies: ['MONTHLY'],
            min_age: 25,
            max_age: 70,
            min_income: 50000
          },
          required_docs: [
            { type: 'INE_FRONT', required: true, description: 'INE/IFE vigente (frente)' },
            { type: 'INE_BACK', required: true, description: 'INE/IFE vigente (reverso)' },
            { type: 'RFC_CONSTANCIA', required: true, description: 'Constancia de situación fiscal' },
            { type: 'BANK_STATEMENTS', required: true, description: 'Últimos 6 estados de cuenta' },
            { type: 'COTIZACION', required: true, description: 'Cotización del bien a arrendar' }
          ],
          extra_fields: [],
          is_active: true
        },
        {
          id: 'prod-005',
          tenant_id: 'tenant-001',
          name: 'Factoraje',
          type: 'FACTORING',
          description: 'Anticipa el cobro de tus facturas por cobrar',
          icon: 'document',
          rules: {
            min_amount: 50000,
            max_amount: 10000000,
            min_term_months: 1,
            max_term_months: 6,
            annual_rate: 18.0,
            opening_commission: 1.0,
            amortization_type: 'BULLET',
            payment_frequencies: ['MONTHLY'],
            min_age: 25,
            max_age: 70,
            min_income: 100000
          },
          required_docs: [
            { type: 'INE_FRONT', required: true, description: 'INE/IFE vigente (frente)' },
            { type: 'INE_BACK', required: true, description: 'INE/IFE vigente (reverso)' },
            { type: 'RFC_CONSTANCIA', required: true, description: 'Constancia de situación fiscal' },
            { type: 'FACTURAS', required: true, description: 'Facturas a descontar (XML y PDF)' }
          ],
          extra_fields: [],
          is_active: true
        }
      ]

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
