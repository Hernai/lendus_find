<script setup lang="ts">
import { useTenantStore } from '@/stores'
import { computed } from 'vue'

const tenantStore = useTenantStore()

const logoUrl = computed(() => tenantStore.branding?.logo_url || '/logo.svg')
const tenantName = computed(() => tenantStore.name || 'LendusFind')
</script>

<template>
  <header class="bg-white border-b border-gray-100 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
      <!-- Logo -->
      <router-link to="/" class="flex items-center gap-2">
        <div class="h-10 w-10 bg-gradient-to-r from-primary-600 to-primary-700 rounded-lg flex items-center justify-center">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <span class="font-bold text-gray-900">{{ tenantName }}</span>
      </router-link>

      <!-- Navigation -->
      <nav class="hidden md:flex items-center gap-8">
        <router-link
          to="/"
          class="text-gray-600 hover:text-primary-600 font-medium transition-colors"
        >
          Inicio
        </router-link>
        <router-link
          to="/simulador"
          class="text-gray-600 hover:text-primary-600 font-medium transition-colors"
        >
          Simulador
        </router-link>
      </nav>

      <!-- CTA Button -->
      <div class="flex items-center gap-4">
        <router-link
          to="/auth"
          class="hidden sm:block text-primary-600 font-medium hover:text-primary-700"
        >
          Iniciar Sesión
        </router-link>
        <router-link
          to="/simulador"
          class="px-5 py-2.5 bg-primary-600 text-white rounded-xl font-medium hover:bg-primary-700 transition-colors shadow-lg"
        >
          Solicitar Crédito
        </router-link>
      </div>
    </div>
  </header>
</template>
