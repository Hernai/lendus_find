<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { logger } from '@/utils/logger'

const log = logger.child('TenantBrandingEditor')

export interface Branding {
  primary_color: string
  secondary_color: string
  accent_color: string
  background_color: string
  text_color: string
  logo_url: string | null
  logo_dark_url: string | null
  favicon_url: string | null
  login_background_url?: string | null
  font_family: string
  heading_font_family?: string | null
  border_radius: string
  button_style: string
  custom_css?: string | null
}

export interface TenantPreviewInfo {
  name: string
  slug: string
}

const props = defineProps<{
  modelValue: Branding
  tenant: TenantPreviewInfo
  showPreviewToggle?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: Branding): void
  (e: 'logo-upload', field: string, file: File): void
}>()

// Local reactive copy of branding
const branding = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

// Update a specific field
const updateField = (key: keyof Branding, value: string | null) => {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

// Type-safe color accessor for template bindings
type ColorKey = 'primary_color' | 'secondary_color' | 'accent_color' | 'background_color' | 'text_color'
const getColorValue = (key: ColorKey): string => {
  return branding.value[key] || '#000000'
}

// Preview state
const previewMode = ref<'desktop' | 'mobile'>('desktop')
const showPreview = ref(true)

// Collapsible sections
const brandingSections = ref({
  logos: true,
  colors: true,
  typography: false
})

// Logo upload refs
const logoUploadRefs = ref<Record<string, HTMLInputElement | null>>({})
const uploadingLogo = ref<string | null>(null)
const uploadError = ref<string | null>(null)

// Clear error after a few seconds with tracked timeout
let uploadErrorTimeoutId: ReturnType<typeof setTimeout> | null = null
const showUploadError = (message: string) => {
  if (uploadErrorTimeoutId) {
    clearTimeout(uploadErrorTimeoutId)
  }
  uploadError.value = message
  uploadErrorTimeoutId = setTimeout(() => {
    uploadError.value = null
  }, 5000)
}
onBeforeUnmount(() => {
  if (uploadErrorTimeoutId) {
    clearTimeout(uploadErrorTimeoutId)
  }
})

// Suggested fonts
const suggestedFonts = [
  'Inter, sans-serif',
  'Roboto, sans-serif',
  'Open Sans, sans-serif',
  'Montserrat, sans-serif',
  'Poppins, sans-serif',
  'Lato, sans-serif',
  'Source Sans Pro, sans-serif',
  'Nunito, sans-serif'
]

// Suggested icons
const suggestedIcons = [
  { name: 'Building', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>' },
  { name: 'Bank', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M4 10h3v7H4zM10.5 10h3v7h-3zM2 19h20v3H2zM17 10h3v7h-3zM12 1L2 6v2h20V6z"/></svg>' },
  { name: 'Wallet', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>' },
  { name: 'Credit Card', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>' },
  { name: 'Money', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>' },
  { name: 'Chart', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/></svg>' },
  { name: 'Shield', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>' },
  { name: 'Star', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>' },
  { name: 'Bolt', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M11 21h-1l1-7H7.5c-.58 0-.57-.32-.38-.66.19-.34.05-.08.07-.12C8.48 10.94 10.42 7.54 13 3h1l-1 7h3.5c.49 0 .56.33.47.51l-.07.15C12.96 17.55 11 21 11 21z"/></svg>' },
  { name: 'Globe', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>' },
  { name: 'Users', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>' },
  { name: 'Lock', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>' },
  { name: 'Heart', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>' },
  { name: 'Diamond', svg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5L2 9l10 12L22 9l-3-6zM9.62 8l1.5-3h1.76l1.5 3H9.62zM11 10v6.68L5.44 10H11zm2 0h5.56L13 16.68V10zm6.26-2h-2.65l-1.5-3h2.65l1.5 3zM6.24 5h2.65l-1.5 3H4.74l1.5-3z"/></svg>' }
]

// Logo upload methods
const triggerLogoUpload = (field: string) => {
  logoUploadRefs.value[field]?.click()
}

const compressImage = (file: File, maxWidth: number, quality: number): Promise<string> => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = (e) => {
      const img = new Image()
      img.onload = () => {
        const canvas = document.createElement('canvas')
        let width = img.width
        let height = img.height

        // Resize if needed
        if (width > maxWidth) {
          height = (height * maxWidth) / width
          width = maxWidth
        }

        canvas.width = width
        canvas.height = height

        const ctx = canvas.getContext('2d')
        if (!ctx) {
          reject(new Error('Could not get canvas context'))
          return
        }

        ctx.drawImage(img, 0, 0, width, height)

        // Export as JPEG for better compression (unless it's an SVG or has transparency)
        const isSvgOrPng = file.type === 'image/svg+xml' || file.type === 'image/png'
        const outputType = isSvgOrPng ? 'image/png' : 'image/jpeg'
        const dataUrl = canvas.toDataURL(outputType, quality)
        resolve(dataUrl)
      }
      img.onerror = () => reject(new Error('Failed to load image'))
      img.src = e.target?.result as string
    }
    reader.onerror = () => reject(new Error('Failed to read file'))
    reader.readAsDataURL(file)
  })
}

const handleLogoUpload = async (event: Event, field: string) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  uploadError.value = null

  if (file.size > 5 * 1024 * 1024) {
    showUploadError('El archivo es muy grande. Máximo 5MB.')
    input.value = ''
    return
  }

  uploadingLogo.value = field

  try {
    // Determine max size based on field type (favicons should be very small)
    const maxWidth = field === 'favicon_url' ? 64 : 400
    const quality = field === 'favicon_url' ? 0.8 : 0.85

    // Compress image before converting to base64
    const compressedDataUrl = await compressImage(file, maxWidth, quality)

    // Check if compressed size is still too large (max ~400KB for safety)
    const maxBase64Length = 400000
    if (compressedDataUrl.length > maxBase64Length) {
      showUploadError(`La imagen comprimida sigue siendo muy grande (${Math.round(compressedDataUrl.length / 1000)}KB). Intenta con una imagen más pequeña.`)
      return
    }

    updateField(field as keyof Branding, compressedDataUrl)
  } catch (error) {
    log.error('Error compressing image', { error })
    showUploadError('Error al procesar la imagen. Intenta con otro formato (PNG, JPG).')
  } finally {
    uploadingLogo.value = null
  }

  // Emit event for parent to handle actual upload
  emit('logo-upload', field, file)

  // Reset input
  input.value = ''
}

const selectSuggestedIcon = (iconSvg: string, primaryColor: string) => {
  // Create a colored SVG data URL
  const coloredSvg = iconSvg.replace('currentColor', primaryColor)
  const svgDataUrl = `data:image/svg+xml;base64,${btoa(coloredSvg)}`
  updateField('logo_url', svgDataUrl)
  updateField('favicon_url', svgDataUrl)
}
</script>

<template>
  <div class="flex h-full">
    <!-- Hidden file inputs -->
    <input type="file" :ref="(el) => logoUploadRefs['logo_url'] = el as HTMLInputElement" @change="handleLogoUpload($event, 'logo_url')" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="hidden" />
    <input type="file" :ref="(el) => logoUploadRefs['logo_dark_url'] = el as HTMLInputElement" @change="handleLogoUpload($event, 'logo_dark_url')" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="hidden" />
    <input type="file" :ref="(el) => logoUploadRefs['favicon_url'] = el as HTMLInputElement" @change="handleLogoUpload($event, 'favicon_url')" accept="image/png,image/x-icon,image/svg+xml" class="hidden" />

    <!-- Left Panel: Controls -->
    <div class="w-[380px] flex-shrink-0 border-r border-gray-100 p-6 space-y-6 bg-white overflow-y-auto">

      <!-- Section: Logotipos -->
      <div>
        <button
          @click="brandingSections.logos = !brandingSections.logos"
          class="w-full flex items-center justify-between gap-2 p-2 -m-2 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <div class="flex items-center gap-2">
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center"
              :style="{ backgroundColor: branding.primary_color + '15' }"
            >
              <svg class="w-4 h-4" :style="{ color: branding.primary_color }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <div class="text-left">
              <h3 class="text-sm font-semibold text-gray-900">Logotipos</h3>
              <p class="text-xs text-gray-500">Identidad visual de la marca</p>
            </div>
          </div>
          <svg
            class="w-5 h-5 text-gray-400 transition-transform"
            :class="{ 'rotate-180': brandingSections.logos }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <div v-show="brandingSections.logos" class="space-y-3 mt-4">
          <!-- Error Message -->
          <div
            v-if="uploadError"
            class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm flex items-start gap-2"
          >
            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ uploadError }}</span>
          </div>

          <!-- Logo Principal -->
          <div class="flex items-center gap-3">
            <div
              class="w-16 h-16 border-2 border-dashed border-gray-200 rounded-xl flex items-center justify-center bg-gray-50 overflow-hidden cursor-pointer hover:border-primary-400 hover:bg-primary-50/30 transition-all group"
              @click="triggerLogoUpload('logo_url')"
            >
              <img v-if="branding.logo_url" :src="branding.logo_url" class="max-h-12 max-w-12 object-contain" alt="Logo" />
              <svg v-else class="w-6 h-6 text-gray-300 group-hover:text-primary-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </div>
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-700">Logo Principal</p>
              <p class="text-xs text-gray-400">PNG, SVG o JPG (max 5MB)</p>
            </div>
          </div>

          <!-- Logo Dark -->
          <div class="flex items-center gap-3">
            <div
              class="w-16 h-16 border-2 border-dashed border-gray-600 rounded-xl flex items-center justify-center bg-gray-800 overflow-hidden cursor-pointer hover:border-primary-400 transition-all group"
              @click="triggerLogoUpload('logo_dark_url')"
            >
              <img v-if="branding.logo_dark_url" :src="branding.logo_dark_url" class="max-h-12 max-w-12 object-contain" alt="Logo Dark" />
              <svg v-else class="w-6 h-6 text-gray-500 group-hover:text-primary-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </div>
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-700">Logo Modo Oscuro</p>
              <p class="text-xs text-gray-400">Para fondos oscuros</p>
            </div>
          </div>

          <!-- Favicon -->
          <div class="flex items-center gap-3">
            <div
              class="w-16 h-16 border-2 border-dashed border-gray-200 rounded-xl flex items-center justify-center bg-gray-50 overflow-hidden cursor-pointer hover:border-primary-400 hover:bg-primary-50/30 transition-all group"
              @click="triggerLogoUpload('favicon_url')"
            >
              <img v-if="branding.favicon_url" :src="branding.favicon_url" class="max-h-8 max-w-8 object-contain" alt="Favicon" />
              <svg v-else class="w-5 h-5 text-gray-300 group-hover:text-primary-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </div>
            <div class="flex-1">
              <p class="text-sm font-medium text-gray-700">Favicon</p>
              <p class="text-xs text-gray-400">Icono del navegador</p>
            </div>
          </div>

          <!-- Quick Icons -->
          <div class="pt-2 border-t border-gray-100">
            <p class="text-xs font-medium text-gray-500 mb-2">Iconos rápidos</p>
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="icon in suggestedIcons"
                :key="icon.name"
                @click="selectSuggestedIcon(icon.svg, branding.primary_color)"
                class="p-2 bg-gray-50 border border-gray-200 rounded-lg hover:bg-primary-50 hover:border-primary-300 hover:scale-105 transition-all"
                :title="icon.name"
              >
                <div class="w-5 h-5" :style="{ color: branding.primary_color }" v-html="icon.svg"></div>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Divider -->
      <div class="border-t border-gray-100"></div>

      <!-- Section: Paleta de Colores -->
      <div>
        <button
          @click="brandingSections.colors = !brandingSections.colors"
          class="w-full flex items-center justify-between gap-2 p-2 -m-2 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <div class="flex items-center gap-2">
            <div
              class="w-8 h-8 rounded-lg flex items-center justify-center"
              :style="{ background: `linear-gradient(135deg, ${branding.primary_color} 0%, ${branding.secondary_color} 100%)` }"
            >
              <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
              </svg>
            </div>
            <div class="text-left">
              <h3 class="text-sm font-semibold text-gray-900">Paleta de Colores</h3>
              <p class="text-xs text-gray-500">Colores de la marca</p>
            </div>
          </div>
          <svg
            class="w-5 h-5 text-gray-400 transition-transform"
            :class="{ 'rotate-180': brandingSections.colors }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <div v-show="brandingSections.colors" class="space-y-3 mt-4">
          <div v-for="color in [
            { key: 'primary_color' as ColorKey, label: 'Color Primario', desc: 'Botones y acentos principales' },
            { key: 'secondary_color' as ColorKey, label: 'Color Secundario', desc: 'Elementos complementarios' },
            { key: 'accent_color' as ColorKey, label: 'Color de Acento', desc: 'Badges y notificaciones' }
          ]" :key="color.key" class="flex items-center gap-3">
            <div class="relative">
              <div
                class="w-10 h-10 rounded-lg shadow-sm border border-gray-200 cursor-pointer hover:scale-105 transition-transform"
                :style="{ backgroundColor: getColorValue(color.key) }"
              ></div>
              <input
                type="color"
                :value="getColorValue(color.key)"
                class="absolute inset-0 w-full h-full cursor-pointer opacity-0"
                @input="updateField(color.key, ($event.target as HTMLInputElement).value)"
              />
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-700">{{ color.label }}</p>
              <p class="text-xs text-gray-400 truncate">{{ color.desc }}</p>
            </div>
            <span class="text-xs font-mono text-gray-400">{{ getColorValue(color.key) }}</span>
          </div>

          <!-- Background & Text Colors -->
          <div class="pt-2 border-t border-gray-100">
            <p class="text-xs font-medium text-gray-500 mb-2">Fondo y texto</p>
            <div class="flex gap-3">
              <div v-for="color in [
                { key: 'background_color' as ColorKey, label: 'Fondo' },
                { key: 'text_color' as ColorKey, label: 'Texto' }
              ]" :key="color.key" class="flex-1">
                <div class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg">
                  <div class="relative">
                    <div
                      class="w-6 h-6 rounded border border-gray-300 cursor-pointer"
                      :style="{ backgroundColor: getColorValue(color.key) }"
                    ></div>
                    <input
                      type="color"
                      :value="getColorValue(color.key)"
                      class="absolute inset-0 w-full h-full cursor-pointer opacity-0"
                      @input="updateField(color.key, ($event.target as HTMLInputElement).value)"
                    />
                  </div>
                  <span class="text-xs text-gray-600">{{ color.label }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Divider -->
      <div class="border-t border-gray-100"></div>

      <!-- Section: Tipografia y Estilo -->
      <div>
        <button
          @click="brandingSections.typography = !brandingSections.typography"
          class="w-full flex items-center justify-between gap-2 p-2 -m-2 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
              <span class="text-sm font-bold text-gray-600">Aa</span>
            </div>
            <div class="text-left">
              <h3 class="text-sm font-semibold text-gray-900">Tipografía y Estilo</h3>
              <p class="text-xs text-gray-500">Fuentes y bordes</p>
            </div>
          </div>
          <svg
            class="w-5 h-5 text-gray-400 transition-transform"
            :class="{ 'rotate-180': brandingSections.typography }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <div v-show="brandingSections.typography" class="space-y-3 mt-4">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Fuente Principal</label>
            <select
              :value="branding.font_family"
              @change="updateField('font_family', ($event.target as HTMLSelectElement).value)"
              class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-2 focus:ring-primary-100 transition-all cursor-pointer"
              :style="{ fontFamily: branding.font_family }"
            >
              <option v-for="font in suggestedFonts" :key="font" :value="font" :style="{ fontFamily: font }">
                {{ font.split(',')[0] }}
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1.5">Bordes</label>
              <select
                :value="branding.border_radius"
                @change="updateField('border_radius', ($event.target as HTMLSelectElement).value)"
                class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-2 focus:ring-primary-100 transition-all cursor-pointer"
              >
                <option value="0">Cuadrado</option>
                <option value="4px">Sutil (4px)</option>
                <option value="8px">Medio (8px)</option>
                <option value="12px">Redondo (12px)</option>
                <option value="9999px">Pill</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1.5">Botones</label>
              <select
                :value="branding.button_style"
                @change="updateField('button_style', ($event.target as HTMLSelectElement).value)"
                class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-primary-400 focus:ring-2 focus:ring-primary-100 transition-all cursor-pointer"
              >
                <option value="rounded">Redondo</option>
                <option value="pill">Pill</option>
                <option value="square">Cuadrado</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Panel: Live Preview -->
    <div class="flex-1 bg-gray-100 p-6 overflow-y-auto" :class="{ 'hidden': !showPreview }">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-sm font-semibold text-gray-700">Vista Previa en Vivo</h3>
          <p class="text-xs text-gray-500">Los cambios se reflejan instantáneamente</p>
        </div>
        <div class="flex items-center gap-2">
          <!-- Device Toggle -->
          <div class="flex items-center gap-1 bg-white rounded-lg p-1 shadow-sm">
            <button
              @click="previewMode = 'desktop'"
              :class="[
                'flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded transition-all',
                previewMode === 'desktop' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:bg-gray-100'
              ]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              Desktop
            </button>
            <button
              @click="previewMode = 'mobile'"
              :class="[
                'flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded transition-all',
                previewMode === 'mobile' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:bg-gray-100'
              ]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
              Mobile
            </button>
          </div>
          <!-- Hide Preview Button -->
          <button
            v-if="showPreviewToggle"
            @click="showPreview = false"
            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-white rounded-lg transition-colors"
            title="Ocultar vista previa"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Desktop Preview -->
      <div v-if="previewMode === 'desktop'" class="bg-white rounded-xl shadow-xl overflow-hidden border border-gray-200">
        <!-- Browser Chrome -->
        <div class="bg-gray-100 border-b border-gray-200 px-4 py-2 flex items-center gap-3">
          <div class="flex gap-1.5">
            <div class="w-3 h-3 rounded-full bg-red-400"></div>
            <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
            <div class="w-3 h-3 rounded-full bg-green-400"></div>
          </div>
          <div class="flex-1 flex items-center justify-center">
            <div class="flex items-center gap-2 bg-white rounded-lg px-3 py-1 text-xs text-gray-500 shadow-inner border border-gray-200 min-w-[280px]">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
              {{ tenant.slug }}.losapp.com
            </div>
          </div>
        </div>

        <!-- Website Content -->
        <div
          class="min-h-[400px]"
          :style="{
            backgroundColor: branding.background_color,
            fontFamily: branding.font_family
          }"
        >
          <!-- Navbar -->
          <nav class="px-6 py-4 border-b" :style="{ borderColor: branding.primary_color + '20' }">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <img
                  v-if="branding.logo_url"
                  :src="branding.logo_url"
                  class="h-8 object-contain"
                  alt="Logo"
                />
                <div
                  v-else
                  class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-bold"
                  :style="{ backgroundColor: branding.primary_color }"
                >
                  {{ tenant.name?.charAt(0) }}
                </div>
                <span
                  class="font-semibold"
                  :style="{ color: branding.text_color }"
                >
                  {{ tenant.name }}
                </span>
              </div>
              <div class="flex items-center gap-4">
                <a href="#" class="text-sm hover:opacity-80 transition-opacity" :style="{ color: branding.text_color }">Inicio</a>
                <a href="#" class="text-sm hover:opacity-80 transition-opacity" :style="{ color: branding.text_color }">Productos</a>
                <button
                  class="px-4 py-2 text-white text-sm font-medium transition-all hover:opacity-90"
                  :style="{
                    backgroundColor: branding.primary_color,
                    borderRadius: branding.border_radius
                  }"
                >
                  Iniciar Sesión
                </button>
              </div>
            </div>
          </nav>

          <!-- Hero Section -->
          <div class="px-6 py-12">
            <div class="max-w-xl">
              <span
                class="inline-block px-3 py-1 text-xs font-semibold rounded-full mb-4"
                :style="{
                  backgroundColor: branding.accent_color + '20',
                  color: branding.accent_color
                }"
              >
                Nuevo
              </span>
              <h1
                class="text-3xl font-bold mb-4"
                :style="{ color: branding.text_color }"
              >
                Solicita tu crédito en minutos
              </h1>
              <p
                class="text-base mb-6 opacity-70"
                :style="{ color: branding.text_color }"
              >
                Obtén hasta $500,000 MXN con tasas competitivas y aprobación rápida. Sin papeleo, 100% digital.
              </p>
              <div class="flex items-center gap-3">
                <button
                  class="px-6 py-3 text-white font-semibold transition-all hover:opacity-90 shadow-lg"
                  :style="{
                    backgroundColor: branding.primary_color,
                    borderRadius: branding.border_radius
                  }"
                >
                  Solicitar Ahora
                </button>
                <button
                  class="px-6 py-3 font-semibold border-2 transition-all hover:opacity-90"
                  :style="{
                    color: branding.secondary_color,
                    borderColor: branding.secondary_color,
                    borderRadius: branding.border_radius
                  }"
                >
                  Ver Simulador
                </button>
              </div>
            </div>
          </div>

          <!-- Feature Cards -->
          <div class="px-6 pb-8">
            <div class="grid grid-cols-3 gap-4">
              <div
                v-for="(feature, idx) in [
                  { icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', title: 'Tasas Bajas', desc: 'Desde 12% anual' },
                  { icon: 'M13 10V3L4 14h7v7l9-11h-7z', title: 'Aprobación Rápida', desc: 'En 24 horas' },
                  { icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', title: '100% Seguro', desc: 'Datos protegidos' }
                ]"
                :key="idx"
                class="p-4 rounded-xl border"
                :style="{
                  backgroundColor: branding.background_color,
                  borderColor: branding.primary_color + '20',
                  borderRadius: branding.border_radius
                }"
              >
                <div
                  class="w-10 h-10 rounded-lg flex items-center justify-center mb-3"
                  :style="{ backgroundColor: branding.primary_color + '15' }"
                >
                  <svg class="w-5 h-5" :style="{ color: branding.primary_color }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="feature.icon" />
                  </svg>
                </div>
                <h3 class="font-semibold text-sm mb-1" :style="{ color: branding.text_color }">
                  {{ feature.title }}
                </h3>
                <p class="text-xs opacity-60" :style="{ color: branding.text_color }">
                  {{ feature.desc }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Mobile Preview -->
      <div v-else class="flex justify-center">
        <div class="relative">
          <!-- Phone Frame -->
          <div class="w-[280px] h-[580px] bg-gray-900 rounded-[3rem] p-3 shadow-2xl">
            <!-- Phone Notch -->
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-7 bg-gray-900 rounded-b-2xl z-10"></div>

            <!-- Phone Screen -->
            <div
              class="w-full h-full rounded-[2.25rem] overflow-hidden overflow-y-auto"
              :style="{
                backgroundColor: branding.background_color,
                fontFamily: branding.font_family
              }"
            >
              <!-- Status Bar -->
              <div class="flex items-center justify-between px-6 pt-3 pb-2" :style="{ backgroundColor: branding.background_color }">
                <span class="text-xs font-medium" :style="{ color: branding.text_color }">9:41</span>
                <div class="flex items-center gap-1">
                  <svg class="w-4 h-4" :style="{ color: branding.text_color }" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12.01 21.49L23.64 7c-.45-.34-4.93-4-11.64-4C5.28 3 .81 6.66.36 7l11.63 14.49.01.01.01-.01z" />
                  </svg>
                  <svg class="w-4 h-4" :style="{ color: branding.text_color }" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17 4h-3V2h-4v2H7v18h10V4z" />
                  </svg>
                </div>
              </div>

              <!-- Mobile Navbar -->
              <div class="flex items-center justify-between px-4 py-3 border-b" :style="{ borderColor: branding.primary_color + '20' }">
                <div class="flex items-center gap-2">
                  <img
                    v-if="branding.logo_url"
                    :src="branding.logo_url"
                    class="h-6 object-contain"
                    alt="Logo"
                  />
                  <div
                    v-else
                    class="w-6 h-6 rounded-md flex items-center justify-center text-white text-xs font-bold"
                    :style="{ backgroundColor: branding.primary_color }"
                  >
                    {{ tenant.name?.charAt(0) }}
                  </div>
                  <span class="font-semibold text-sm" :style="{ color: branding.text_color }">
                    {{ tenant.name }}
                  </span>
                </div>
                <button class="p-1" :style="{ color: branding.text_color }">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                  </svg>
                </button>
              </div>

              <!-- Mobile Hero -->
              <div class="px-4 py-6">
                <span
                  class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full mb-3"
                  :style="{
                    backgroundColor: branding.accent_color + '20',
                    color: branding.accent_color
                  }"
                >
                  100% Digital
                </span>
                <h1
                  class="text-xl font-bold mb-2 leading-tight"
                  :style="{ color: branding.text_color }"
                >
                  Tu crédito aprobado en minutos
                </h1>
                <p
                  class="text-xs mb-4 opacity-70"
                  :style="{ color: branding.text_color }"
                >
                  Hasta $500,000 MXN. Sin papeleos.
                </p>
                <button
                  class="w-full py-2.5 text-white text-sm font-semibold shadow-lg"
                  :style="{
                    backgroundColor: branding.primary_color,
                    borderRadius: branding.border_radius
                  }"
                >
                  Solicitar Ahora
                </button>
              </div>

              <!-- Mobile Stats -->
              <div class="flex justify-around px-4 py-4 border-t border-b" :style="{ borderColor: branding.primary_color + '10' }">
                <div class="text-center">
                  <p class="text-sm font-bold" :style="{ color: branding.primary_color }">24hrs</p>
                  <p class="text-[10px] opacity-60" :style="{ color: branding.text_color }">Respuesta</p>
                </div>
                <div class="text-center">
                  <p class="text-sm font-bold" :style="{ color: branding.primary_color }">0%</p>
                  <p class="text-[10px] opacity-60" :style="{ color: branding.text_color }">Comisión</p>
                </div>
                <div class="text-center">
                  <p class="text-sm font-bold" :style="{ color: branding.primary_color }">48</p>
                  <p class="text-[10px] opacity-60" :style="{ color: branding.text_color }">Meses</p>
                </div>
              </div>

              <!-- Mobile Features -->
              <div class="p-4 space-y-2">
                <div
                  v-for="(feature, idx) in [
                    { icon: 'M13 10V3L4 14h7v7l9-11h-7z', title: 'Rápido', desc: 'Aprobación en 24hrs' },
                    { icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', title: 'Seguro', desc: 'Datos protegidos' }
                  ]"
                  :key="idx"
                  class="flex items-center gap-3 p-3 rounded-lg border"
                  :style="{
                    borderColor: branding.primary_color + '20',
                    borderRadius: branding.border_radius
                  }"
                >
                  <div
                    class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                    :style="{ backgroundColor: branding.primary_color + '15' }"
                  >
                    <svg class="w-4 h-4" :style="{ color: branding.primary_color }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="feature.icon" />
                    </svg>
                  </div>
                  <div>
                    <p class="text-xs font-semibold" :style="{ color: branding.text_color }">{{ feature.title }}</p>
                    <p class="text-[10px] opacity-60" :style="{ color: branding.text_color }">{{ feature.desc }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Phone Side Buttons -->
          <div class="absolute right-[-3px] top-24 w-1 h-8 bg-gray-700 rounded-l"></div>
          <div class="absolute left-[-3px] top-20 w-1 h-6 bg-gray-700 rounded-r"></div>
          <div class="absolute left-[-3px] top-32 w-1 h-12 bg-gray-700 rounded-r"></div>
        </div>
      </div>

      <!-- Color Palette Preview -->
      <div class="mt-4 flex items-center justify-center gap-2">
        <div
          class="w-8 h-8 rounded-lg shadow-sm border border-white"
          :style="{ backgroundColor: branding.primary_color }"
          title="Primario"
        ></div>
        <div
          class="w-8 h-8 rounded-lg shadow-sm border border-white"
          :style="{ backgroundColor: branding.secondary_color }"
          title="Secundario"
        ></div>
        <div
          class="w-8 h-8 rounded-lg shadow-sm border border-white"
          :style="{ backgroundColor: branding.accent_color }"
          title="Acento"
        ></div>
        <div class="w-px h-6 bg-gray-300 mx-1"></div>
        <div
          class="w-8 h-8 rounded-lg shadow-sm border border-gray-300"
          :style="{ backgroundColor: branding.background_color }"
          title="Fondo"
        ></div>
        <div
          class="w-8 h-8 rounded-lg shadow-sm border border-gray-300"
          :style="{ backgroundColor: branding.text_color }"
          title="Texto"
        ></div>
      </div>
    </div>

    <!-- Show Preview Button (when hidden) -->
    <div
      v-if="!showPreview && showPreviewToggle"
      class="w-12 bg-gray-100 flex flex-col items-center justify-center border-l border-gray-200 cursor-pointer hover:bg-gray-200 transition-colors"
      @click="showPreview = true"
      title="Mostrar vista previa"
    >
      <svg class="w-5 h-5 text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
      </svg>
      <span class="text-xs text-gray-500 [writing-mode:vertical-lr] rotate-180">Vista previa</span>
    </div>
  </div>
</template>
