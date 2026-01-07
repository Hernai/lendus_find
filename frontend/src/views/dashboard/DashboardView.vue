<script setup lang="ts">
import { useAuthStore } from '@/stores'
import { AppButton } from '@/components/common'

const authStore = useAuthStore()

const handleLogout = () => {
  authStore.logout()
  window.location.href = '/'
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-primary-600 px-4 pt-6 pb-12">
      <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between mb-4">
          <div class="w-24 h-8 bg-white/20 rounded flex items-center justify-center">
            <span class="text-white text-xs font-medium">LENDUS</span>
          </div>
          <button
            class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center"
            @click="handleLogout"
          >
            <span class="text-white font-medium">
              {{ authStore.user?.phone?.slice(-2) || 'US' }}
            </span>
          </button>
        </div>
        <p class="text-primary-100">Hola 👋</p>
        <h1 class="text-white text-xl font-bold">Tu dashboard</h1>
      </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 -mt-8">
      <!-- Status Card -->
      <div class="bg-white rounded-2xl shadow-lg p-6 mb-4">
        <h2 class="font-semibold text-gray-900 mb-4">Bienvenido</h2>
        <p class="text-gray-600 mb-6">
          Aquí podrás ver el estado de tus solicitudes de crédito.
        </p>

        <router-link to="/solicitud">
          <AppButton variant="primary" full-width>
            Nueva Solicitud
          </AppButton>
        </router-link>
      </div>

      <!-- Empty state -->
      <div class="bg-white rounded-2xl shadow-sm p-8 text-center">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <h3 class="font-medium text-gray-900 mb-2">Sin solicitudes</h3>
        <p class="text-gray-500 text-sm">
          Aún no tienes solicitudes de crédito activas.
        </p>
      </div>
    </main>
  </div>
</template>
