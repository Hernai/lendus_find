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
  // Navigate to next step (KYC verification)
  log.info('User continuing to next step after simulation')
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
  <div class="space-y-6">
    <!-- Step 1: Product Selection -->
    <template v-if="step === 'select'">
      <div class="text-center mb-8">
        <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">
          ¿Qué tipo de financiamiento necesitas?
        </h2>
        <p class="text-gray-600">
          Selecciona el producto que mejor se adapte a tus necesidades
        </p>
      </div>

      <!-- Product Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <button
          v-for="product in products"
          :key="product.id"
          class="bg-white rounded-2xl p-6 shadow-sm border-2 border-transparent hover:border-primary-500 hover:shadow-md transition-all text-left group"
          @click="selectProduct(product)"
        >
          <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-xl bg-primary-100 flex items-center justify-center flex-shrink-0 group-hover:bg-primary-200 transition-colors">
              <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getProductIcon(product.icon || 'user')" />
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="font-semibold text-gray-900 mb-1">{{ product.name }}</h3>
              <p class="text-sm text-gray-500 mb-3">{{ product.description }}</p>
              <div class="flex flex-wrap gap-2 text-xs">
                <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full">
                  {{ formatMoney(product.rules?.min_amount ?? 0) }} - {{ formatMoney(product.rules?.max_amount ?? 0) }}
                </span>
                <span class="bg-green-100 text-green-700 px-2 py-1 rounded-full">
                  {{ product.rules?.annual_rate ?? 0 }}% anual
                </span>
              </div>
            </div>
          </div>
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

      <!-- Simulator Card -->
      <SimulatorCard
        :product="selectedProduct"
        :in-onboarding="true"
        @continue="handleContinue"
      />

      <!-- Additional Info -->
      <div class="mt-8 bg-white rounded-2xl p-6 shadow-sm">
        <h3 class="font-semibold text-gray-900 mb-4">Requisitos</h3>
        <ul class="space-y-3">
          <li class="flex items-start gap-3">
            <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            <span class="text-gray-600">Ser mayor de {{ selectedProduct?.rules?.min_age || 18 }} años</span>
          </li>
          <li class="flex items-start gap-3">
            <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            <span class="text-gray-600">INE/IFE vigente</span>
          </li>
          <li v-for="doc in (selectedProduct?.required_docs ?? []).filter(d => typeof d === 'object' && d.required)" :key="typeof doc === 'object' ? doc.type : doc" class="flex items-start gap-3">
            <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            <span class="text-gray-600">{{ typeof doc === 'object' ? doc.description : doc }}</span>
          </li>
          <li class="flex items-start gap-3">
            <svg class="w-5 h-5 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            <span class="text-gray-600">Ingresos mínimos de {{ formatMoney(selectedProduct?.rules?.min_income || 8000) }} mensuales</span>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>
