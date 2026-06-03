<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTenantStore } from '@/stores'
import { getTenantBasePath } from '@/utils/tenant'
import AppHeader from '@/components/layout/AppHeader.vue'
import AppFooter from '@/components/layout/AppFooter.vue'
import { AppButton } from '@/components/common'

const router = useRouter()
const tenantStore = useTenantStore()

// '/:tenant' (path-based) o '' (subdomain) — evita prefix redundante.
const goToAuth = () => {
  router.push(`${getTenantBasePath()}/auth`)
}

const goToSimulator = () => {
  router.push(`${getTenantBasePath()}/simulador`)
}

onMounted(async () => {
  await tenantStore.loadConfig()
  tenantStore.applyTheme()
})
</script>

<template>
  <div class="min-h-screen flex flex-col bg-gradient-to-b from-teal-50 to-white">
    <AppHeader />

    <main class="flex-1">
      <!-- Hero -->
      <section class="px-6 py-16 max-w-5xl mx-auto text-center">
        <h1 class="text-4xl md:text-5xl font-bold text-teal-900 mb-4">
          Finatea — Crédito al instante
        </h1>
        <p class="text-lg md:text-xl text-gray-700 mb-8 max-w-2xl mx-auto">
          Solicita por WhatsApp, recibe respuesta en minutos y firma 100%
          digital. Sin papeleo, sin filas.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <AppButton variant="primary" size="lg" @click="goToAuth">
            Solicitar por WhatsApp
          </AppButton>
          <AppButton variant="secondary" size="lg" @click="goToSimulator">
            Simular mi crédito
          </AppButton>
        </div>
      </section>

      <!-- Beneficios -->
      <section class="px-6 py-12 bg-white">
        <div class="max-w-5xl mx-auto grid md:grid-cols-3 gap-8">
          <div class="text-center">
            <div class="text-5xl mb-3">💬</div>
            <h3 class="text-xl font-semibold mb-2">Por WhatsApp</h3>
            <p class="text-gray-600">Inicia tu solicitud en el chat que ya conoces.</p>
          </div>
          <div class="text-center">
            <div class="text-5xl mb-3">⚡</div>
            <h3 class="text-xl font-semibold mb-2">Respuesta inmediata</h3>
            <p class="text-gray-600">Pre-aprobación en menos de 3 minutos.</p>
          </div>
          <div class="text-center">
            <div class="text-5xl mb-3">🔒</div>
            <h3 class="text-xl font-semibold mb-2">Seguro y regulado</h3>
            <p class="text-gray-600">Tus datos protegidos bajo estándares de la CNBV.</p>
          </div>
        </div>
      </section>

      <!-- CTA final -->
      <section class="px-6 py-16 text-center">
        <h2 class="text-2xl md:text-3xl font-bold text-teal-900 mb-4">
          Empieza ahora
        </h2>
        <p class="text-gray-700 mb-6">
          Tu crédito a un mensaje de distancia.
        </p>
        <AppButton variant="primary" size="lg" @click="goToAuth">
          Comenzar
        </AppButton>
      </section>
    </main>

    <AppFooter />
  </div>
</template>
