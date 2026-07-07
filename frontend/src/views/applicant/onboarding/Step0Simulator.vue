<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApplicationStore, useTenantStore, useOnboardingStore } from '@/stores'
import SimulatorCard from '@/components/simulator/SimulatorCard.vue'
import { AppButton } from '@/components/common'
import { formatMoney } from '@/utils/formatters'
import type { Product } from '@/types'
import { logger } from '@/utils/logger'

const log = logger.child('Step0Simulator')
const router = useRouter()
const applicationStore = useApplicationStore()
const tenantStore = useTenantStore()
const onboardingStore = useOnboardingStore()

const step = ref<'select' | 'simulate'>('select')
const selectedProduct = ref<Product | null>(null)

const products = computed(() => tenantStore.activeProducts)

// Categorización de requisitos para presentación agrupada.
// El backend manda required_docs en dos shapes:
//   - Legacy plano: [{type, required, description}, ...]
//   - Nuevo segmentado: {nationals: [...], foreigners: [...]}
// Y los tipos de documento se clasifican en 3 categorías visuales:
//   - ID: INE, pasaporte, residencia (depende de nacionalidad)
//   - Comprobantes: domicilio, ingresos, etc.
//   - Biométricos: selfie, face match
type RequiredDoc = { type?: string; description?: string; required?: boolean }

const ID_DOC_TYPES = new Set(['INE_FRONT', 'INE_BACK', 'PASSPORT', 'RESIDENCE_CARD', 'DRIVER_LICENSE', 'VISA'])
const BIOMETRIC_DOC_TYPES = new Set(['SELFIE', 'FACE_MATCH', 'BIOMETRIC', 'LIVENESS'])

// Toggle Mexicano (default) | Extranjero. Solo se muestra si el producto
// tiene shape segmentado con docs en ambas listas.
const isForeigner = ref(false)

function extractSegment(raw: unknown, segment: 'nationals' | 'foreigners'): RequiredDoc[] {
  if (!raw) return []
  if (Array.isArray(raw)) {
    // Shape plano: los tratamos como "nationals" por defecto.
    return segment === 'nationals'
      ? raw.filter((d): d is RequiredDoc => typeof d === 'object' && d !== null)
      : []
  }
  if (typeof raw === 'object') {
    const seg = (raw as Record<string, unknown>)[segment]
    return Array.isArray(seg)
      ? seg.filter((d: unknown): d is RequiredDoc => typeof d === 'object' && d !== null)
      : []
  }
  return []
}

const idDocsNationals = computed(() => extractSegment(selectedProduct.value?.required_docs, 'nationals'))
const idDocsForeigners = computed(() => extractSegment(selectedProduct.value?.required_docs, 'foreigners'))

// Solo mostramos el toggle si hay docs distintos para extranjeros.
const hasNationalitySplit = computed(() => idDocsForeigners.value.length > 0)

const activeDocs = computed(() =>
  isForeigner.value && hasNationalitySplit.value ? idDocsForeigners.value : idDocsNationals.value
)

const idDocs = computed(() =>
  activeDocs.value.filter(d => d.required && d.type && ID_DOC_TYPES.has(d.type))
)
const otherDocs = computed(() =>
  activeDocs.value.filter(d => d.required && d.type && !ID_DOC_TYPES.has(d.type) && !BIOMETRIC_DOC_TYPES.has(d.type))
)
const biometricDocs = computed(() =>
  activeDocs.value.filter(d => d.required && d.type && BIOMETRIC_DOC_TYPES.has(d.type))
)

const eligibilityItems = computed<string[]>(() => {
  const items: string[] = []
  const product = selectedProduct.value
  if (!product) return items

  const elig = (product.eligibility_rules ?? {}) as { min_age?: number; max_age?: number }
  const minAge = elig.min_age ?? product.rules?.min_age
  const maxAge = elig.max_age ?? product.rules?.max_age
  if (minAge && maxAge) items.push(`Edad entre ${minAge} y ${maxAge} años`)
  else if (minAge) items.push(`Mayor de ${minAge} años`)

  const minIncome = product.rules?.min_income
  if (minIncome) items.push(`Ingresos mínimos de ${formatMoney(minIncome)} mensuales`)

  return items
})

// --- Arrendamiento: selección del activo a arrendar (solo type ARRENDAMIENTO) ---
const isLease = computed(() => selectedProduct.value?.type === 'ARRENDAMIENTO')

// Catálogo de activos permitidos por el producto (lo fija el admin en rules.lease),
// cruzado con el enum AssetType para los labels en español.
const assetOptions = computed<{ value: string; label: string }[]>(() => {
  const allowed = selectedProduct.value?.rules?.lease?.asset_types ?? []
  const catalog = tenantStore.options.assetType ?? []
  return allowed.map((v) => catalog.find((o) => o.value === v) ?? { value: v, label: v })
})

// Activo elegido, retenido en el store (+storage) hasta Step1.
const selectedAsset = computed<string>({
  get: () => applicationStore.selectedAssetType ?? '',
  set: (v: string) => applicationStore.setSelectedAssetType(v || null),
})

// Paneles solares capturan "capacidad"; el resto (vehículo/maquinaria) marca/modelo/año.
const isSolarAsset = computed(() => selectedAsset.value === 'SOLAR_PANELS')

// Detalle del bien, buffereado en el store (leaseDraft) hasta crear la solicitud.
const leaseBrand = computed<string>({
  get: () => applicationStore.leaseDraft.asset_brand ?? '',
  set: (v) => applicationStore.setLeaseDraft({ asset_brand: v }),
})
const leaseModel = computed<string>({
  get: () => applicationStore.leaseDraft.asset_model ?? '',
  set: (v) => applicationStore.setLeaseDraft({ asset_model: v }),
})
const leaseYear = computed<string>({
  get: () => (applicationStore.leaseDraft.asset_year ?? '').toString(),
  set: (v) => applicationStore.setLeaseDraft({ asset_year: v ? Number(v) : null }),
})
const leaseCapacity = computed<string>({
  get: () => applicationStore.leaseDraft.asset_capacity ?? '',
  set: (v) => applicationStore.setLeaseDraft({ asset_capacity: v }),
})

const selectProduct = (product: Product) => {
  log.info('Product selected', { product: product.name })
  selectedProduct.value = product
  applicationStore.setSelectedProduct(product)
  step.value = 'simulate'
}

const goBack = () => {
  step.value = 'select'
  selectedProduct.value = null
  applicationStore.setSelectedProduct(null)
}

const handleContinue = () => {
  // User completed simulation and wants to continue with application
  log.info('User continuing to next step after simulation')
  // Arrendamiento usa el flujo DINÁMICO (selección de bien, persona/empresa,
  // datos de empresa). El crédito sigue en el flujo legacy (verificación KYC).
  if (selectedProduct.value?.type === 'ARRENDAMIENTO') {
    const slug = tenantStore.slug || 'demo'
    router.push({ name: 'tenant-onboarding-dynamic', params: { tenant: slug } })
    return
  }
  router.push('/solicitud/verificacion')
}

const getProductIcon = (icon: string) => {
  const icons: Record<string, string> = {
    user: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    briefcase: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    building: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    truck: 'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2zm4-3a2 2 0 100-4 2 2 0 000 4z',
    document: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    cash: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
  }
  return icons[icon] || icons.user
}

onMounted(async () => {
  // Load tenant config if not already loaded
  if (!tenantStore.isLoaded) {
    await tenantStore.loadConfig()
  }

  // If product was pre-selected, go directly to simulator
  if (applicationStore.selectedProduct) {
    selectedProduct.value = applicationStore.selectedProduct
    step.value = 'simulate'
  }
})
</script>

<template>
  <div class="max-w-md mx-auto px-4 py-6 space-y-6">
    <!-- Step 1: Product Selection -->
    <template v-if="step === 'select'">
      <div class="text-center mb-6 md:mb-8">
        <h2 class="text-xl md:text-3xl font-bold text-gray-900 mb-1 md:mb-2">
          ¿Qué tipo de financiamiento necesitas?
        </h2>
        <p class="text-sm md:text-base text-gray-600">
          Selecciona el producto que mejor se adapte a tus necesidades
        </p>
      </div>

      <!-- Product List (lista compacta, ancho = formulario) -->
      <div class="space-y-3">
        <button
          v-for="product in products"
          :key="product.id"
          class="w-full bg-white rounded-xl px-4 py-3 shadow-sm border-2 border-transparent hover:border-primary-500 hover:shadow-md transition-all text-left group flex items-center gap-3"
          @click="selectProduct(product)"
        >
          <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0 group-hover:bg-primary-200 transition-colors">
            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getProductIcon(product.icon || 'user')" />
            </svg>
          </div>
          <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-gray-900 text-sm mb-0.5 truncate">{{ product.name }}</h3>
            <p class="text-xs text-gray-500 mb-1.5 line-clamp-1">{{ product.description }}</p>
            <div class="flex flex-wrap gap-1.5 text-[11px]">
              <span class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded-full">
                {{ formatMoney(product.rules?.min_amount ?? 0) }} - {{ formatMoney(product.rules?.max_amount ?? 0) }}
              </span>
              <span class="bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">
                {{ product.rules?.annual_rate ?? 0 }}% anual
              </span>
            </div>
          </div>
          <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </template>

    <!-- Step 2: Simulator -->
    <template v-else>
      <!-- Back button & Selected product -->
      <div class="flex items-center gap-4 mb-8 mt-4">
        <button
          class="p-2 hover:bg-gray-200 rounded-lg transition-colors"
          @click="goBack"
        >
          <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        <div v-if="selectedProduct" class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center">
            <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getProductIcon(selectedProduct.icon || 'user')" />
            </svg>
          </div>
          <div>
            <p class="font-semibold text-gray-900">{{ selectedProduct.name }}</p>
            <p class="text-sm text-gray-500">{{ selectedProduct.description }}</p>
          </div>
        </div>
      </div>

      <!-- Arrendamiento: datos del bien a arrendar (solo productos ARRENDAMIENTO).
           Se captura ANTES de simular la renta; el valor va en la tarjeta de abajo. -->
      <div v-if="isLease && assetOptions.length" class="mb-6 p-4 rounded-xl border border-primary-100 bg-primary-50/40">
        <label class="block text-sm font-medium text-gray-700 mb-2">¿Qué deseas arrendar?</label>
        <select
          v-model="selectedAsset"
          class="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-colors"
        >
          <option value="" disabled>Selecciona el bien…</option>
          <option v-for="opt in assetOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>

        <!-- Detalle del bien: marca/modelo/año (vehículo/maquinaria) o capacidad (solar) -->
        <div v-if="selectedAsset" class="mt-4 grid grid-cols-2 gap-3">
          <div :class="isSolarAsset ? 'col-span-2' : 'col-span-1'">
            <label class="block text-xs font-medium text-gray-600 mb-1">Marca</label>
            <input
              v-model="leaseBrand"
              type="text"
              :placeholder="isSolarAsset ? 'Ej. Jinko, LONGi…' : 'Ej. Toyota, Caterpillar…'"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
          </div>
          <div v-if="!isSolarAsset">
            <label class="block text-xs font-medium text-gray-600 mb-1">Modelo</label>
            <input
              v-model="leaseModel"
              type="text"
              placeholder="Ej. Hilux, 320D…"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
          </div>
          <div v-if="!isSolarAsset">
            <label class="block text-xs font-medium text-gray-600 mb-1">Año</label>
            <input
              v-model="leaseYear"
              type="number"
              inputmode="numeric"
              placeholder="Ej. 2024"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
          </div>
          <div v-else class="col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">Capacidad (kW)</label>
            <input
              v-model="leaseCapacity"
              type="text"
              placeholder="Ej. 5 kW"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            />
          </div>
        </div>

        <p class="mt-2 text-xs text-gray-500">Con estos datos simulamos tu renta. El anticipo lo eliges abajo.</p>
      </div>

      <!-- Simulator Card -->
      <SimulatorCard
        :product="selectedProduct"
        :in-onboarding="true"
        @continue="handleContinue"
      />

      <!-- Requisitos (agrupados por categoría) -->
      <div class="mt-6 bg-white rounded-2xl p-5 shadow-sm space-y-5">
        <h3 class="font-semibold text-gray-900">Requisitos</h3>

        <!-- Elegibilidad: edad e ingresos mínimos del producto -->
        <section v-if="eligibilityItems.length">
          <h4 class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Elegibilidad</h4>
          <ul class="space-y-2">
            <li v-for="item in eligibilityItems" :key="item" class="flex items-start gap-2 text-sm text-gray-700">
              <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <span>{{ item }}</span>
            </li>
          </ul>
        </section>

        <!-- Identificación (con toggle Mexicano/Extranjero cuando aplique) -->
        <section v-if="idDocs.length">
          <div class="flex items-center justify-between mb-2 gap-2">
            <h4 class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Identificación</h4>
            <div v-if="hasNationalitySplit" class="flex bg-gray-100 rounded-full p-0.5 text-xs">
              <button
                type="button"
                class="px-2.5 py-1 rounded-full transition-colors"
                :class="!isForeigner ? 'bg-white shadow-sm font-medium text-gray-900' : 'text-gray-500'"
                @click="isForeigner = false"
              >Mexicano</button>
              <button
                type="button"
                class="px-2.5 py-1 rounded-full transition-colors"
                :class="isForeigner ? 'bg-white shadow-sm font-medium text-gray-900' : 'text-gray-500'"
                @click="isForeigner = true"
              >Extranjero</button>
            </div>
          </div>
          <ul class="space-y-2">
            <li v-for="doc in idDocs" :key="doc.type" class="flex items-start gap-2 text-sm text-gray-700">
              <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <span>{{ doc.description }}</span>
            </li>
          </ul>
        </section>

        <!-- Comprobantes y otros documentos -->
        <section v-if="otherDocs.length">
          <h4 class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Documentos</h4>
          <ul class="space-y-2">
            <li v-for="doc in otherDocs" :key="doc.type" class="flex items-start gap-2 text-sm text-gray-700">
              <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <span>{{ doc.description }}</span>
            </li>
          </ul>
        </section>

        <!-- Validación biométrica -->
        <section v-if="biometricDocs.length">
          <h4 class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Validación biométrica</h4>
          <ul class="space-y-2">
            <li v-for="doc in biometricDocs" :key="doc.type" class="flex items-start gap-2 text-sm text-gray-700">
              <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
              <span>{{ doc.description }}</span>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </div>
</template>
