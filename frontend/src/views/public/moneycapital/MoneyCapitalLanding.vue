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
  <div class="min-h-screen flex flex-col bg-gradient-to-b from-purple-50 to-white">
    <AppHeader />

    <main class="flex-1">
      <!-- Hero -->
      <section class="px-6 py-16 max-w-5xl mx-auto text-center">
        <h1 class="text-4xl md:text-5xl font-bold text-purple-900 mb-4">
          Tu liquidez, en minutos
        </h1>
        <p class="text-lg md:text-xl text-gray-700 mb-8 max-w-2xl mx-auto">
          MoneyCapital te presta sin buró, con aprobación inmediata y sin
          papeleo eterno. Solicita 100% digital desde tu celular.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <AppButton variant="primary" size="lg" @click="goToAuth">
            Solicitar mi crédito
          </AppButton>
          <AppButton variant="secondary" size="lg" @click="goToSimulator">
            Simular cuánto pago
          </AppButton>
        </div>
      </section>

      <!-- Beneficios -->
      <section class="px-6 py-12 bg-white">
        <div class="max-w-5xl mx-auto grid md:grid-cols-3 gap-8">
          <div class="text-center">
            <div class="text-5xl mb-3">⚡</div>
            <h3 class="text-xl font-semibold mb-2">Aprobación inmediata</h3>
            <p class="text-gray-600">Respuesta en menos de 5 minutos sin filas ni sucursales.</p>
          </div>
          <div class="text-center">
            <div class="text-5xl mb-3">🛡️</div>
            <h3 class="text-xl font-semibold mb-2">Sin consultar buró</h3>
            <p class="text-gray-600">Evaluamos tu capacidad real de pago, no tu historial pasado.</p>
          </div>
          <div class="text-center">
            <div class="text-5xl mb-3">📱</div>
            <h3 class="text-xl font-semibold mb-2">100% digital</h3>
            <p class="text-gray-600">Solicita, firma y recibe el dinero desde tu celular.</p>
          </div>
        </div>
      </section>

      <!-- CTA final -->
      <section class="px-6 py-16 text-center">
        <h2 class="text-2xl md:text-3xl font-bold text-purple-900 mb-4">
          ¿Listo para empezar?
        </h2>
        <p class="text-gray-700 mb-6">
          Toma menos de 5 minutos. Sin compromiso.
        </p>
        <AppButton variant="primary" size="lg" @click="goToAuth">
          Comenzar mi solicitud
        </AppButton>
      </section>
    </main>

    <AppFooter />
  </div>
</template>
