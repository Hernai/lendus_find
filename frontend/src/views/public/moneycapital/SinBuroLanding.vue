<script setup lang="ts">
import { ref } from 'vue'
import { useTenantStore } from '@/stores'

const tenantStore = useTenantStore()

const phone = ref('')
const name = ref('')
const submitting = ref(false)

const submit = async () => {
  if (!phone.value || phone.value.length < 10) return
  submitting.value = true
  try {
    // En móvil web podemos redirigir directo al flujo de OTP con el teléfono.
    window.location.href = `/auth/phone?phone=${encodeURIComponent(phone.value)}&utm=sin-buro`
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-white">
    <!-- Hero -->
    <section class="bg-gradient-to-br from-primary-700 to-primary-900 text-white px-6 py-16 sm:py-24">
      <div class="max-w-3xl mx-auto text-center space-y-4">
        <p class="text-sm uppercase tracking-wider opacity-90">{{ tenantStore.tenant?.name || 'MoneyCapital' }}</p>
        <h1 class="text-3xl sm:text-5xl font-extrabold leading-tight">
          Sin buró tradicional. Sin trabas.
        </h1>
        <p class="text-base sm:text-lg opacity-90 max-w-2xl mx-auto">
          Préstamos personales de $300 a $15,000 MXN aprobados por tu historial de pagos digital,
          no por el buró. 100% en línea, sin papeleo.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center pt-4">
          <a
            href="https://play.google.com/store/apps/details?id=mx.moneycapital.app"
            class="inline-flex items-center justify-center px-6 py-3 bg-white text-primary-700 rounded-xl font-semibold hover:bg-gray-100"
          >
            <span>Descarga en Google Play</span>
          </a>
          <a
            href="https://apps.apple.com/mx/app/moneycapital"
            class="inline-flex items-center justify-center px-6 py-3 bg-black/40 backdrop-blur text-white rounded-xl font-semibold border border-white/30 hover:bg-black/60"
          >
            <span>Descarga en App Store</span>
          </a>
        </div>
      </div>
    </section>

    <!-- Form rápido -->
    <section class="px-6 py-12 max-w-md mx-auto">
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 -mt-20 relative z-10 space-y-4">
        <h2 class="text-xl font-bold text-gray-900 text-center">Solicítalo hoy</h2>
        <p class="text-sm text-gray-500 text-center">
          Te llamamos para terminar tu solicitud en menos de 10 minutos.
        </p>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tu nombre</label>
          <input
            v-model="name"
            type="text"
            class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-500"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tu teléfono</label>
          <input
            v-model="phone"
            type="tel"
            inputmode="numeric"
            maxlength="10"
            class="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-500"
          />
        </div>
        <button
          type="button"
          class="w-full py-3 bg-primary-600 text-white rounded-xl font-semibold hover:bg-primary-700 disabled:opacity-50"
          :disabled="submitting || phone.length < 10"
          @click="submit"
        >
          Solicitar ahora
        </button>
      </div>

      <!-- Beneficios -->
      <div class="mt-10 grid gap-4">
        <div class="flex gap-3 items-start">
          <div class="w-10 h-10 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center flex-shrink-0">✓</div>
          <div>
            <p class="font-semibold text-gray-900">Sin buró tradicional</p>
            <p class="text-sm text-gray-600">Aprobamos tu solicitud por tu historial digital, no por buró.</p>
          </div>
        </div>
        <div class="flex gap-3 items-start">
          <div class="w-10 h-10 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center flex-shrink-0">⏱</div>
          <div>
            <p class="font-semibold text-gray-900">Dispersión en minutos</p>
            <p class="text-sm text-gray-600">Recibe tu dinero en tu cuenta o tarjeta tras aprobar la oferta.</p>
          </div>
        </div>
        <div class="flex gap-3 items-start">
          <div class="w-10 h-10 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center flex-shrink-0">🔒</div>
          <div>
            <p class="font-semibold text-gray-900">100% en línea</p>
            <p class="text-sm text-gray-600">KYC con INE y selfie. Tu información viaja cifrada.</p>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
