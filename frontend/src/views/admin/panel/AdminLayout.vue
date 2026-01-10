<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore, useTenantStore } from '@/stores'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const tenantStore = useTenantStore()

// Cargar configuración del tenant al montar el layout
onMounted(async () => {
  await tenantStore.loadConfig()
})

const showUserMenu = ref(false)

const currentUser = computed(() => authStore.user)
const tenantName = computed(() => tenantStore.name || 'LendusFind')
const permissions = computed(() => authStore.permissions)

// Role labels for display
const roleLabels: Record<string, string> = {
  'SUPERVISOR': 'Supervisor',
  'ANALYST': 'Analista',
  'ADMIN': 'Administrador',
  'SUPER_ADMIN': 'Super Admin'
}

const userRole = computed(() => roleLabels[currentUser.value?.role || ''] || 'Staff')

// Navigation items filtered by permissions
const navItems = computed(() => {
  const items = [
    // Dashboard - all staff can see
    {
      path: '/admin',
      label: 'Dashboard',
      icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
      show: true
    },
    // Solicitudes - all staff (agents only see assigned, handled by backend)
    {
      path: '/admin/solicitudes',
      label: 'Solicitudes',
      icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
      show: true
    },
    // Productos - admin only (canManageProducts)
    {
      path: '/admin/productos',
      label: 'Productos',
      icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
      show: permissions.value?.canManageProducts ?? false
    },
    // Usuarios - admin only (canManageUsers)
    {
      path: '/admin/usuarios',
      label: 'Usuarios',
      icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
      show: permissions.value?.canManageUsers ?? false
    },
    // Reportes - analyst+ (canViewReports)
    {
      path: '/admin/reportes',
      label: 'Reportes',
      icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
      show: permissions.value?.canViewReports ?? false
    },
    // Configuración - admin only, hidden if user has Tenants access (to avoid redundancy)
    {
      path: '/admin/configuracion',
      label: 'Configuración',
      icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
      show: (permissions.value?.canManageProducts ?? false) && !(permissions.value?.canConfigureTenant ?? false)
    },
    // Tenants - super_admin only (canConfigureTenant) - replaces Configuración for SUPER_ADMINs
    {
      path: '/admin/tenants',
      label: 'Tenants',
      icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
      show: permissions.value?.canConfigureTenant ?? false
    }
  ]

  return items.filter(item => item.show)
})

const isActive = (path: string) => {
  if (path === '/admin') {
    return route.path === '/admin'
  }
  return route.path.startsWith(path)
}

const handleLogout = async () => {
  await authStore.logout()
  router.push('/')
}
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Top Navigation -->
    <nav class="bg-gray-900 text-white shadow-lg">
      <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">
          <!-- Logo & Brand -->
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-primary-500 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </div>
            <div>
              <p class="font-bold text-lg">{{ tenantName }}</p>
              <p class="text-xs text-gray-400">Mesa de Control</p>
            </div>
          </div>

          <!-- Navigation Links -->
          <div class="hidden md:flex items-center gap-1">
            <router-link
              v-for="item in navItems"
              :key="item.path"
              :to="item.path"
              :class="[
                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                isActive(item.path)
                  ? 'bg-gray-800 text-white'
                  : 'text-gray-300 hover:bg-gray-800 hover:text-white'
              ]"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
              </svg>
              {{ item.label }}
            </router-link>
          </div>

          <!-- Right side: Notifications & User Menu -->
          <div class="flex items-center gap-4">
            <!-- Notifications -->
            <button class="relative p-2 text-gray-400 hover:text-white transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
              </svg>
              <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
            </button>

            <!-- User Menu -->
            <div class="relative">
              <button
                class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-800 transition-colors"
                @click="showUserMenu = !showUserMenu"
              >
                <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center">
                  <span class="text-sm font-medium text-white">
                    {{ currentUser?.email?.charAt(0).toUpperCase() || 'A' }}
                  </span>
                </div>
                <div class="hidden sm:block text-left">
                  <span class="block text-sm text-gray-300">
                    {{ currentUser?.email || 'Admin' }}
                  </span>
                  <span class="block text-xs text-gray-500">
                    {{ userRole }}
                  </span>
                </div>
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <!-- Dropdown -->
              <div
                v-if="showUserMenu"
                class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-50"
              >
                <router-link
                  to="/admin/configuracion"
                  class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                  @click="showUserMenu = false"
                >
                  Configuración
                </router-link>
                <hr class="my-1">
                <button
                  class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100"
                  @click="handleLogout"
                >
                  Cerrar sesión
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Mobile Navigation -->
      <div class="md:hidden border-t border-gray-800 px-2 py-2">
        <div class="flex gap-1 overflow-x-auto">
          <router-link
            v-for="item in navItems"
            :key="item.path"
            :to="item.path"
            :class="[
              'flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors',
              isActive(item.path)
                ? 'bg-gray-800 text-white'
                : 'text-gray-400 hover:bg-gray-800 hover:text-white'
            ]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
            </svg>
            {{ item.label }}
          </router-link>
        </div>
      </div>
    </nav>

    <!-- Click outside to close menu -->
    <div
      v-if="showUserMenu"
      class="fixed inset-0 z-40"
      @click="showUserMenu = false"
    />

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-6">
      <router-view />
    </main>
  </div>
</template>
