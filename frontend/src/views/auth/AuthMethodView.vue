<script setup lang="ts">
import { useTenantStore } from '@/stores'
import { computed, onMounted } from 'vue'

const tenantStore = useTenantStore()
const tenantName = computed(() => tenantStore.name || 'LendusFind')

onMounted(async () => {
  if (!tenantStore.isLoaded) {
    await tenantStore.loadConfig()
    tenantStore.applyTheme()
  }
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <!-- Content -->
    <div class="flex-1 flex flex-col justify-center px-6 py-12">
      <div class="mx-auto w-full max-w-md">
        <!-- Logo -->
        <div class="flex justify-center mb-10">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-gradient-to-r from-primary-600 to-primary-700 rounded-xl flex items-center justify-center">
              <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <span class="text-xl font-bold text-gray-900">{{ tenantName }}</span>
          </div>
        </div>

        <!-- Title -->
        <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">
          Ingresa a tu cuenta
        </h1>
        <p class="text-gray-500 text-center mb-8">
          Elige cómo quieres verificarte
        </p>

        <!-- Auth Methods -->
        <div class="space-y-3">
          <!-- SMS Option -->
          <router-link
            to="/auth/phone"
            class="w-full p-4 bg-white border-2 border-primary-500 rounded-xl flex items-center gap-4 transition hover:bg-primary-50"
          >
            <div class="w-12 h-12 bg-primary-100 rounded-full flex items-center justify-center">
              <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
              </svg>
            </div>
            <div class="text-left flex-1">
              <p class="font-semibold text-gray-900">Celular (SMS)</p>
              <p class="text-sm text-gray-500">Código por mensaje de texto</p>
            </div>
            <svg class="w-5 h-5 text-primary-500" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
          </router-link>

          <!-- WhatsApp Option -->
          <router-link
            to="/auth/phone?method=whatsapp"
            class="w-full p-4 bg-white border-2 border-gray-200 rounded-xl flex items-center gap-4 transition hover:border-green-300 hover:bg-green-50"
          >
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
              <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
              </svg>
            </div>
            <div class="text-left flex-1">
              <p class="font-semibold text-gray-900">WhatsApp</p>
              <p class="text-sm text-gray-500">Código por WhatsApp</p>
            </div>
            <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
          </router-link>

          <!-- Email Option -->
          <router-link
            to="/auth/email"
            class="w-full p-4 bg-white border-2 border-gray-200 rounded-xl flex items-center gap-4 transition hover:border-blue-300 hover:bg-blue-50"
          >
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
              <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
            <div class="text-left flex-1">
              <p class="font-semibold text-gray-900">Correo Electrónico</p>
              <p class="text-sm text-gray-500">Código a tu email</p>
            </div>
            <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
          </router-link>
        </div>

        <!-- Back link -->
        <div class="mt-8 text-center">
          <router-link to="/" class="text-gray-500 hover:text-gray-700 text-sm">
            ← Volver al inicio
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>
