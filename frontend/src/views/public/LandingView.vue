<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useTenantStore, useApplicationStore } from '@/stores'
import AppHeader from '@/components/layout/AppHeader.vue'
import AppFooter from '@/components/layout/AppFooter.vue'
import SimulatorCard from '@/components/simulator/SimulatorCard.vue'
import { AppButton } from '@/components/common'
import type { Product } from '@/types/tenant'

const tenantStore = useTenantStore()
const applicationStore = useApplicationStore()
const showRequirementsModal = ref(false)
const selectedProduct = ref<Product | null>(null)

const products = computed(() => tenantStore.activeProducts)

const formatMoney = (amount: number) => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount)
}

const getProductIcon = (icon: string) => {
  const icons: Record<string, string> = {
    user: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    briefcase: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    building: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    truck: 'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2zm4-3a2 2 0 100-4 2 2 0 000 4z',
    document: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
  }
  return icons[icon] || icons.user
}

const selectProduct = (product: Product) => {
  selectedProduct.value = product
  applicationStore.setSelectedProduct(product)
}

const goBackToProducts = () => {
  selectedProduct.value = null
  applicationStore.setSelectedProduct(null)
}

onMounted(async () => {
  await tenantStore.loadConfig()
  tenantStore.applyTheme()

  // Select "Crédito Personal" by default
  const personalCredit = products.value.find(p => p.type === 'PERSONAL')
  if (personalCredit) {
    selectProduct(personalCredit)
  }
})
</script>

<template>
  <div class="min-h-screen flex flex-col">
    <AppHeader />

    <main class="flex-1">
      <!-- Hero Section -->
      <section class="bg-gradient-to-br from-gray-50 to-primary-50 py-12 md:py-20">
        <div class="max-w-7xl mx-auto px-4">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">
            <!-- Left: Text Content -->
            <div>
              <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary-100 text-primary-700 mb-4">
                100% Digital
              </span>

              <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-gray-900 mb-6 leading-tight">
                Tu crédito aprobado en
                <span class="text-primary-600">minutos</span>
              </h1>

              <p class="text-xl text-gray-600 mb-8">
                Sin papeleos, sin filas, sin complicaciones.
                Solicita desde $5,000 hasta $500,000 MXN.
              </p>

              <div class="flex flex-col sm:flex-row gap-4 mb-8">
                <router-link to="/simulador" class="w-full sm:w-auto">
                  <AppButton variant="primary" size="lg" fullWidth class="sm:w-auto">
                    Comenzar Solicitud →
                  </AppButton>
                </router-link>
                <AppButton variant="outline" size="lg" fullWidth class="sm:w-auto" @click="showRequirementsModal = true">
                  Ver Requisitos
                </AppButton>
              </div>

              <!-- Stats -->
              <div class="flex items-center gap-8 pt-8 border-t border-gray-200">
                <div class="text-center">
                  <p class="text-3xl font-bold text-primary-600">24hrs</p>
                  <p class="text-sm text-gray-500">Respuesta</p>
                </div>
                <div class="text-center">
                  <p class="text-3xl font-bold text-primary-600">0%</p>
                  <p class="text-sm text-gray-500">Comisión oculta</p>
                </div>
                <div class="text-center">
                  <p class="text-3xl font-bold text-primary-600">48</p>
                  <p class="text-sm text-gray-500">Meses plazo</p>
                </div>
              </div>
            </div>

            <!-- Right: Product Selection or Simulator -->
            <div class="lg:pl-8">
              <!-- Product Selection -->
              <div v-if="!selectedProduct" class="bg-white rounded-2xl shadow-lg p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Selecciona tu tipo de crédito</h2>
                <div class="space-y-3">
                  <button
                    v-for="product in products"
                    :key="product.id"
                    class="w-full p-4 border-2 border-gray-200 rounded-xl flex items-center gap-4 transition-all hover:border-primary-500 hover:bg-primary-50 text-left"
                    @click="selectProduct(product)"
                  >
                    <div class="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center flex-shrink-0">
                      <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getProductIcon(product.icon || 'user')" />
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="font-semibold text-gray-900">{{ product.name }}</p>
                      <p class="text-sm text-gray-500">
                        {{ formatMoney(product.rules.min_amount) }} - {{ formatMoney(product.rules.max_amount) }}
                      </p>
                    </div>
                    <div class="text-right flex-shrink-0">
                      <p class="text-sm font-medium text-primary-600">{{ product.rules.annual_rate }}% anual</p>
                      <svg class="w-5 h-5 text-gray-400 ml-auto" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                      </svg>
                    </div>
                  </button>
                </div>
              </div>

              <!-- Simulator (when product is selected) -->
              <div v-else>
                <!-- Selected product header -->
                <div class="flex items-center gap-2 sm:gap-3 mb-4 bg-white rounded-xl p-3 shadow-sm">
                  <button
                    class="p-2 hover:bg-gray-100 rounded-lg transition-colors flex-shrink-0"
                    @click="goBackToProducts"
                  >
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                  </button>
                  <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
                    <div class="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0">
                      <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getProductIcon(selectedProduct.icon || 'user')" />
                      </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="font-semibold text-gray-900 truncate">{{ selectedProduct.name }}</p>
                      <p class="text-sm text-gray-500 truncate">{{ selectedProduct.description }}</p>
                    </div>
                  </div>
                </div>

                <!-- Simulator Card -->
                <SimulatorCard :product="selectedProduct" />
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Features Section -->
      <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4">
          <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">
              ¿Por qué elegirnos?
            </h2>
            <p class="text-gray-600 max-w-2xl mx-auto">
              Somos la financiera que te entiende. Proceso simple, tasas competitivas y atención personalizada.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="text-center p-6">
              <div class="w-16 h-16 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
              </div>
              <h3 class="text-xl font-semibold text-gray-900 mb-2">100% Digital</h3>
              <p class="text-gray-600">
                Solicita desde tu celular en cualquier momento. Sin visitas a sucursales.
              </p>
            </div>

            <!-- Feature 2 -->
            <div class="text-center p-6">
              <div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h3 class="text-xl font-semibold text-gray-900 mb-2">Respuesta Rápida</h3>
              <p class="text-gray-600">
                Obtén una pre-aprobación en minutos y respuesta final en 24 horas.
              </p>
            </div>

            <!-- Feature 3 -->
            <div class="text-center p-6">
              <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
              </div>
              <h3 class="text-xl font-semibold text-gray-900 mb-2">Sin Papeleos</h3>
              <p class="text-gray-600">
                Solo necesitas tu INE y un comprobante de domicilio. Todo digital.
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- CTA Section -->
      <section class="py-16 bg-primary-600">
        <div class="max-w-4xl mx-auto px-4 text-center">
          <h2 class="text-3xl font-bold text-white mb-4">
            ¿Listo para obtener tu crédito?
          </h2>
          <p class="text-primary-100 text-lg mb-8">
            Simula tu crédito ahora mismo y conoce tu cuota mensual.
          </p>
          <router-link to="/simulador">
            <AppButton variant="secondary" size="lg" class="bg-white text-primary-600 hover:bg-gray-100">
              Simular mi crédito
            </AppButton>
          </router-link>
        </div>
      </section>
    </main>

    <AppFooter />

    <!-- Requirements Modal -->
    <Teleport to="body">
      <div
        v-if="showRequirementsModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-black/50"
          @click="showRequirementsModal = false"
        />

        <!-- Modal Content -->
        <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
          <!-- Header -->
          <div class="flex items-center justify-between p-6 border-b">
            <h2 class="text-xl font-bold text-gray-900">Requisitos del Crédito</h2>
            <button
              class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
              @click="showRequirementsModal = false"
            >
              <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 space-y-6">
            <!-- Basic Requirements -->
            <div>
              <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Requisitos Personales
              </h3>
              <ul class="space-y-2 text-gray-600">
                <li class="flex items-start gap-2">
                  <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  Ser mayor de 18 y menor de 70 años
                </li>
                <li class="flex items-start gap-2">
                  <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  Nacionalidad mexicana o residencia permanente
                </li>
                <li class="flex items-start gap-2">
                  <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  Ingreso mínimo mensual de $8,000 MXN
                </li>
                <li class="flex items-start gap-2">
                  <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  Contar con CURP y RFC válidos
                </li>
              </ul>
            </div>

            <!-- Documents -->
            <div>
              <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Documentos Necesarios
              </h3>
              <ul class="space-y-2 text-gray-600">
                <li class="flex items-start gap-2">
                  <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  INE/IFE vigente (frente y reverso)
                </li>
                <li class="flex items-start gap-2">
                  <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  Comprobante de domicilio (máx. 3 meses)
                </li>
                <li class="flex items-start gap-2">
                  <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                  Comprobante de ingresos (opcional)
                </li>
              </ul>
            </div>

            <!-- Loan Info -->
            <div class="bg-gray-50 rounded-xl p-4">
              <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Características del Crédito
              </h3>
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p class="text-gray-500">Monto</p>
                  <p class="font-semibold text-gray-900">$5,000 - $500,000</p>
                </div>
                <div>
                  <p class="text-gray-500">Plazo</p>
                  <p class="font-semibold text-gray-900">3 - 48 meses</p>
                </div>
                <div>
                  <p class="text-gray-500">Tasa anual</p>
                  <p class="font-semibold text-gray-900">Desde 45% CAT</p>
                </div>
                <div>
                  <p class="text-gray-500">Frecuencia de pago</p>
                  <p class="font-semibold text-gray-900">Semanal / Quincenal / Mensual</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="p-6 border-t bg-gray-50 rounded-b-2xl">
            <router-link to="/simulador" @click="showRequirementsModal = false">
              <AppButton variant="primary" size="lg" class="w-full">
                Simular mi Crédito →
              </AppButton>
            </router-link>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
