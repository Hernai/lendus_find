import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { v2 } from '@/services/v2'
import { detectTenantSlug } from '@/utils/tenant'
import { logger } from '@/utils/logger'
import { initializeFormatters } from '@/utils/formatters'
import type { Tenant, Product } from '@/types'

const log = logger.child('TenantStore')

/** Option for select/dropdown components */
interface Option {
  value: string
  label: string
}

/** All enum options from backend (camelCase keys) */
interface EnumOptions {
  // Profile enums
  gender: Option[]
  maritalStatus: Option[]
  educationLevel: Option[]
  housingType: Option[]
  employmentType: Option[]
  bankAccountType: Option[]
  // Reference enums
  referenceType: Option[]
  relationship: Option[]
  relationshipFamily: Option[]
  relationshipNonFamily: Option[]
  // Document and ID enums
  documentType: Option[]
  idType: Option[]
  // Application enums
  loanPurpose: Option[]
  paymentFrequency: Option[]
  applicationStatus: Option[]
  // Product enums
  productType: Option[]
  // Admin enums
  userType: Option[]
  rejectionReason: Option[]
  documentRejectionReason: Option[]
}

// TenantConfigResponse removed - now using V2 types from v2.config.getConfig

// Helper to convert hex to HSL
function hexToHSL(hex: string): { h: number; s: number; l: number } {
  const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
  if (!result || !result[1] || !result[2] || !result[3]) return { h: 0, s: 0, l: 0 }

  const r = parseInt(result[1], 16) / 255
  const g = parseInt(result[2], 16) / 255
  const b = parseInt(result[3], 16) / 255

  const max = Math.max(r, g, b)
  const min = Math.min(r, g, b)
  let h = 0
  let s = 0
  const l = (max + min) / 2

  if (max !== min) {
    const d = max - min
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min)
    switch (max) {
      case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break
      case g: h = ((b - r) / d + 2) / 6; break
      case b: h = ((r - g) / d + 4) / 6; break
    }
  }

  return { h: h * 360, s: s * 100, l: l * 100 }
}

// Helper to convert HSL to RGB triplet string (for Tailwind)
function hslToRgbTriplet(h: number, s: number, l: number): string {
  s /= 100
  l /= 100
  const a = s * Math.min(l, 1 - l)
  const f = (n: number) => {
    const k = (n + h / 30) % 12
    const color = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1)
    return Math.round(255 * color)
  }
  return `${f(0)} ${f(8)} ${f(4)}`
}

// Generate a dark tinted color from primary (for footer/dark sections)
function generateDarkTinted(baseColor: string): string {
  const { h } = hexToHSL(baseColor)
  // Use the primary hue with very low saturation and lightness
  // This creates a dark color with a subtle tint of the primary
  return hslToRgbTriplet(h, 20, 11) // 20% saturation, 11% lightness
}

// Generate a full color palette from a base color (returns RGB triplets for Tailwind)
// This algorithm preserves the original color's lightness for shade 600
// and scales other shades relative to it
function generateColorPalette(baseColor: string): Record<string, string> {
  const { h, s, l } = hexToHSL(baseColor)

  // Use the original lightness for shade 600, and scale others relative to it
  // This preserves dark colors as dark and light colors as light
  const baseLightness = l

  // Define lightness offsets from the base (600)
  // Lighter shades go up, darker shades go down
  const lightnessScale: Record<string, number> = {
    50: Math.min(97, baseLightness + 55),   // Very light
    100: Math.min(94, baseLightness + 48),  // Light
    200: Math.min(87, baseLightness + 38),  // Light-medium
    300: Math.min(76, baseLightness + 28),  // Medium-light
    400: Math.min(63, baseLightness + 18),  // Medium
    500: Math.min(53, baseLightness + 8),   // Slightly lighter than base
    600: baseLightness,                      // Base color (original)
    700: Math.max(15, baseLightness - 6),   // Slightly darker
    800: Math.max(10, baseLightness - 12),  // Darker
    900: Math.max(5, baseLightness - 18),   // Very dark
  }

  const palette: Record<string, string> = {}
  for (const [shade, lightness] of Object.entries(lightnessScale)) {
    // Adjust saturation: lighter shades have less saturation, darker can have more
    let adjustedSat = s
    if (shade === '50' || shade === '100') {
      adjustedSat = s * 0.7  // Much less saturated for very light shades
    } else if (shade === '200' || shade === '300') {
      adjustedSat = s * 0.85  // Slightly less saturated
    } else if (shade === '800' || shade === '900') {
      adjustedSat = Math.min(100, s * 1.1)  // Slightly more saturated for dark
    }
    palette[shade] = hslToRgbTriplet(h, adjustedSat, lightness)
  }

  return palette
}

/** Default empty options */
const defaultOptions: EnumOptions = {
  // Profile enums
  gender: [],
  maritalStatus: [],
  educationLevel: [],
  housingType: [],
  employmentType: [],
  bankAccountType: [],
  // Reference enums
  referenceType: [],
  relationship: [],
  relationshipFamily: [],
  relationshipNonFamily: [],
  // Document and ID enums
  documentType: [],
  idType: [],
  // Application enums
  loanPurpose: [],
  paymentFrequency: [],
  applicationStatus: [],
  // Product enums
  productType: [],
  // Admin enums
  userType: [],
  rejectionReason: [],
  documentRejectionReason: [],
}

export const useTenantStore = defineStore('tenant', () => {
  // State
  const tenant = ref<Tenant | null>(null)
  const products = ref<Product[]>([])
  const options = ref<EnumOptions>(defaultOptions)
  const isLoading = ref(false)
  const isLoaded = ref(false)
  const loadFailed = ref(false)
  const error = ref<string | null>(null)

  // Cache control for concurrent requests
  const configLoadPromise = ref<Promise<void> | null>(null)

  // Getters
  const hasTenant = computed(() => isLoaded.value && tenant.value !== null && !loadFailed.value)
  const noTenantDetected = computed(() => isLoaded.value && (tenant.value === null || loadFailed.value))
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
    // Check if there's a tenant to load
    const tenantSlug = detectTenantSlug()

    // If already loaded with the same tenant, skip
    if (isLoaded.value && tenant.value?.slug === tenantSlug) {
      return
    }

    // If already loading, wait for existing promise
    if (configLoadPromise.value) {
      log.debug('Config already loading, waiting for existing request')
      return configLoadPromise.value
    }

    // If tenant changed, reset first
    if (isLoaded.value && tenantSlug && tenant.value?.slug !== tenantSlug) {
      log.debug('URL tenant changed', { from: tenant.value?.slug, to: tenantSlug })
      reset()
    }

    // No tenant in URL - fail gracefully (user should go to /find)
    if (!tenantSlug) {
      log.debug(' No tenant detected')
      loadFailed.value = true
      isLoaded.value = true
      return
    }

    const loadPromise = (async () => {
      isLoading.value = true
      error.value = null
      loadFailed.value = false

      try {
        log.debug(' Loading config for tenant:', tenantSlug)
        const response = await v2.config.getConfig()

        if (!response.success || !response.data) {
          throw new Error(response.message || 'Error al cargar configuración')
        }

        tenant.value = response.data.tenant as unknown as Tenant
        products.value = response.data.products as unknown as Product[]
        options.value = response.data.options ?? defaultOptions

        // Initialize formatters with enum options from backend
        if (response.data.options) {
          initializeFormatters(response.data.options)
        }

        isLoaded.value = true
        loadFailed.value = false
        log.debug(' Config loaded:', {
          tenantName: tenant.value?.name,
          primaryColor: tenant.value?.branding?.primary_color,
          optionsLoaded: Object.keys(response.data.options ?? {}).length
        })
      } catch (e) {
        error.value = 'Error al cargar la configuración'
        loadFailed.value = true
        isLoaded.value = true
        log.error(' Failed to load tenant config:', e)
      } finally {
        isLoading.value = false
        configLoadPromise.value = null
      }
    })()

    configLoadPromise.value = loadPromise
    return loadPromise
  }

  const applyTheme = (isAdminRoute = false) => {
    const root = document.documentElement

    log.debug(' applyTheme called:', { isAdminRoute, hasBranding: !!branding.value })

    // Admin routes always use default theme
    if (isAdminRoute) {
      log.debug(' Admin route - using default theme')
      resetTheme()
      return
    }

    // If no branding, keep defaults
    if (!branding.value) {
      log.debug(' No branding configured - keeping defaults')
      return
    }

    const b = branding.value
    log.debug(' Applying branding:', { primary: b.primary_color, secondary: b.secondary_color })

    // Generate color palette from primary color (RGB triplets for Tailwind)
    if (b.primary_color) {
      const palette = generateColorPalette(b.primary_color)
      log.debug(' Generated palette (RGB):', palette)
      for (const [shade, rgbTriplet] of Object.entries(palette)) {
        root.style.setProperty(`--primary-${shade}-rgb`, rgbTriplet)
      }
      root.style.setProperty('--tenant-primary', b.primary_color)

      // Generate dark tinted color for footer/dark sections
      const darkTinted = generateDarkTinted(b.primary_color)
      root.style.setProperty('--tenant-dark-rgb', darkTinted)
      log.debug(' Dark tinted color:', darkTinted)
    }

    // Generate color palette from secondary color (RGB triplets for Tailwind)
    if (b.secondary_color) {
      const secondaryPalette = generateColorPalette(b.secondary_color)
      log.debug(' Generated secondary palette (RGB):', secondaryPalette)
      for (const [shade, rgbTriplet] of Object.entries(secondaryPalette)) {
        root.style.setProperty(`--secondary-${shade}-rgb`, rgbTriplet)
      }
      root.style.setProperty('--tenant-secondary', b.secondary_color)
    }

    if (b.accent_color) {
      root.style.setProperty('--tenant-accent', b.accent_color)
    }

    // Apply text color
    if (b.text_color) {
      root.style.setProperty('--tenant-text-color', b.text_color)
      document.body.style.color = b.text_color
    }

    if (b.border_radius) {
      root.style.setProperty('--tenant-radius', b.border_radius)
    }

    // Apply font family
    if (b.font_family) {
      loadFont(b.font_family)
      root.style.setProperty('--tenant-font-family', b.font_family)
      document.body.style.fontFamily = b.font_family
    }

    // Apply heading font if different
    if (b.heading_font_family) {
      loadFont(b.heading_font_family)
      root.style.setProperty('--tenant-heading-font', b.heading_font_family)
    }

    // Apply button style
    if (b.button_style) {
      const borderRadiusMap: Record<string, string> = {
        'rounded': '0.75rem',
        'pill': '9999px',
        'square': '0'
      }
      root.style.setProperty('--tenant-button-radius', borderRadiusMap[b.button_style] || '0.75rem')
    }

    // Update favicon
    const favicon = document.querySelector<HTMLLinkElement>("link[rel~='icon']")
    if (favicon && b.favicon_url) {
      favicon.href = b.favicon_url
    }

    // Update title
    document.title = name.value || 'Solicitud de Crédito'
  }

  // Load Google Font dynamically
  const loadFont = (fontFamily: string) => {
    // Extract font name (before comma)
    const fontName = fontFamily.split(',')[0]?.trim().replace(/['"]/g, '')
    if (!fontName || fontName.toLowerCase() === 'sans-serif' || fontName.toLowerCase() === 'serif') {
      return // System font, no need to load
    }

    // Check if already loaded
    const existingLink = document.querySelector(`link[data-font="${fontName}"]`)
    if (existingLink) return

    // Create Google Fonts link
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = `https://fonts.googleapis.com/css2?family=${fontName.replace(/\s+/g, '+')}:wght@300;400;500;600;700;800&display=swap`
    link.setAttribute('data-font', fontName)
    document.head.appendChild(link)
  }

  // Reset to default theme (for admin)
  const resetTheme = () => {
    const root = document.documentElement
    // Default indigo palette (RGB triplets for Tailwind)
    const primaryDefaults: Record<string, string> = {
      '50': '240 244 255',
      '100': '224 231 255',
      '200': '199 210 254',
      '300': '165 180 252',
      '400': '129 140 248',
      '500': '99 102 241',
      '600': '79 70 229',
      '700': '67 56 202',
      '800': '55 48 163',
      '900': '49 46 129',
    }
    for (const [shade, rgbTriplet] of Object.entries(primaryDefaults)) {
      root.style.setProperty(`--primary-${shade}-rgb`, rgbTriplet)
    }
    // Default emerald palette for secondary (RGB triplets for Tailwind)
    const secondaryDefaults: Record<string, string> = {
      '50': '236 253 245',
      '100': '209 250 229',
      '200': '167 243 208',
      '300': '110 231 183',
      '400': '52 211 153',
      '500': '16 185 129',
      '600': '5 150 105',
      '700': '4 120 87',
      '800': '6 95 70',
      '900': '6 78 59',
    }
    for (const [shade, rgbTriplet] of Object.entries(secondaryDefaults)) {
      root.style.setProperty(`--secondary-${shade}-rgb`, rgbTriplet)
    }
    root.style.setProperty('--tenant-primary', '#4f46e5')
    root.style.setProperty('--tenant-secondary', '#10b981')
    root.style.setProperty('--tenant-accent', '#f59e0b')
    root.style.setProperty('--tenant-dark-rgb', '23 25 35') // Indigo-tinted dark
    root.style.setProperty('--tenant-text-color', '#1f2937')

    // Reset font and button settings
    root.style.setProperty('--tenant-font-family', "'Inter', sans-serif")
    root.style.setProperty('--tenant-heading-font', "'Inter', sans-serif")
    root.style.setProperty('--tenant-button-radius', '0.75rem')
    root.style.setProperty('--tenant-radius', '12px')
    document.body.style.fontFamily = "'Inter', sans-serif"
    document.body.style.color = '#1f2937'
  }

  const reset = () => {
    tenant.value = null
    products.value = []
    options.value = defaultOptions
    isLoaded.value = false
    loadFailed.value = false
    error.value = null
    configLoadPromise.value = null
  }

  return {
    // State
    tenant,
    products,
    options,
    isLoading,
    isLoaded,
    loadFailed,
    error,
    // Getters
    hasTenant,
    noTenantDetected,
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
    resetTheme,
    reset
  }
})
