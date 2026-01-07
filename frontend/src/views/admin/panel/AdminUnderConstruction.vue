<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const pageInfo = computed(() => {
  const path = route.path
  if (path.includes('productos')) {
    return {
      title: 'Productos',
      description: 'Aquí podrás gestionar los productos de crédito, configurar tasas, plazos y documentos requeridos.',
      icon: 'box'
    }
  }
  if (path.includes('usuarios')) {
    return {
      title: 'Usuarios',
      description: 'Aquí podrás administrar usuarios del sistema, roles y permisos de acceso.',
      icon: 'users'
    }
  }
  if (path.includes('reportes')) {
    return {
      title: 'Reportes',
      description: 'Aquí podrás generar reportes de solicitudes, colocación, cartera y métricas de operación.',
      icon: 'chart'
    }
  }
  return {
    title: 'En Construcción',
    description: 'Esta sección estará disponible próximamente.',
    icon: 'construction'
  }
})
</script>

<template>
  <div class="flex items-center justify-center min-h-[60vh]">
    <div class="text-center max-w-md px-6">
      <!-- Icon -->
      <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
        <!-- Box icon for Products -->
        <svg v-if="pageInfo.icon === 'box'" class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
        <!-- Users icon -->
        <svg v-else-if="pageInfo.icon === 'users'" class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <!-- Chart icon for Reports -->
        <svg v-else-if="pageInfo.icon === 'chart'" class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <!-- Default construction icon -->
        <svg v-else class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
        </svg>
      </div>

      <!-- Title -->
      <h1 class="text-2xl font-bold text-gray-900 mb-3">{{ pageInfo.title }}</h1>

      <!-- Description -->
      <p class="text-gray-500 mb-6">{{ pageInfo.description }}</p>

      <!-- Construction badge -->
      <div class="inline-flex items-center gap-2 px-4 py-2 bg-amber-50 text-amber-700 rounded-full text-sm font-medium">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        En construcción
      </div>

      <!-- Optional: Progress indicator -->
      <div class="mt-8">
        <div class="flex items-center justify-center gap-3 text-sm text-gray-400">
          <div class="w-8 h-1 bg-gray-200 rounded-full overflow-hidden">
            <div class="w-1/3 h-full bg-primary-500 rounded-full animate-pulse" />
          </div>
          <span>Próximamente</span>
        </div>
      </div>
    </div>
  </div>
</template>
