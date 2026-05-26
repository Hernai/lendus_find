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
    window.location.href = `/auth/phone?phone=${encodeURIComponent(phone.value)}&utm=liquidez-urgente`
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-white">
    <section class="bg-gradient-to-br from-amber-500 to-primary-700 text-white px-6 py-16 sm:py-24">
      <div class="max-w-3xl mx-auto text-center space-y-4">
        <p class="text-sm uppercase tracking-wider opacity-90">{{ tenantStore.tenant?.name || 'MoneyCapital' }}</p>
        <h1 class="text-3xl sm:text-5xl font-extrabold leading-tight">
          Liquidez urgente, hoy mismo.
        </h1>
        <p class="text-base sm:text-lg opacity-90 max-w-2xl mx-auto">
          Cuando necesites dinero rápido — pago de renta, escuela o un imprevisto —
          obtén entre $300 y $15,000 directo a tu cuenta o tarjeta.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center pt-4">
          <a
            href="https://play.google.com/store/apps/details?id=mx.moneycapital.app"
            class="inline-flex items-center justify-center px-6 py-3 bg-white text-primary-700 rounded-xl font-semibold hover:bg-gray-100"
          >Google Play</a>
          <a
            href="https://apps.apple.com/mx/app/moneycapital"
            class="inline-flex items-center justify-center px-6 py-3 bg-black/40 backdrop-blur text-white rounded-xl font-semibold border border-white/30 hover:bg-black/60"
          >App Store</a>
        </div>
      </div>
    </section>

    <section class="px-6 py-12 max-w-md mx-auto">
      <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 -mt-20 relative z-10 space-y-4">
        <h2 class="text-xl font-bold text-gray-900 text-center">Pídelo ahora</h2>
        <p class="text-sm text-gray-500 text-center">
          Sólo necesitas INE, una selfie y tu cuenta o tarjeta de débito.
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
    </section>
  </div>
</template>
