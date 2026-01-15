<script setup lang="ts">
import { useTenantStore } from '@/stores'
import { computed } from 'vue'

const tenantStore = useTenantStore()

const logoUrl = computed(() => tenantStore.branding?.logo_url || 'https://lendus.com.mx/img/logos/find.png')
const tenantName = computed(() => tenantStore.name || 'LendusFind')
</script>

<template>
  <header class="bg-white border-b border-gray-100 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
      <!-- Logo -->
      <router-link to="/" class="flex items-center gap-2">
        <img
          :src="logoUrl"
          :alt="tenantName"
          class="h-10 max-w-[180px] object-contain"
        />
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
          class="text-primary-600 font-medium hover:text-primary-700"
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
