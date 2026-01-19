<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { v2 } from '@/services/v2'
import { useAuthStore, useTenantStore } from '@/stores'
import { logger } from '@/utils/logger'

const log = logger.child('TenantSwitcher')

interface Tenant {
  id: string
  name: string
  slug: string
  is_active: boolean
}

const authStore = useAuthStore()
const tenantStore = useTenantStore()

const isOpen = ref(false)
const isLoading = ref(false)
const tenants = ref<Tenant[]>([])

const isSuperAdmin = computed(() => authStore.isSuperAdmin)
const selectedTenantId = computed(() => authStore.selectedTenantId)
const currentTenantName = computed(() => tenantStore.name || 'Tenant')

const selectedTenant = computed(() => {
  if (!selectedTenantId.value) return null
  return tenants.value.find(t => t.id === selectedTenantId.value)
})

const displayName = computed(() => {
  if (selectedTenant.value) {
    return selectedTenant.value.name
  }
  return currentTenantName.value
})

const loadTenants = async (): Promise<void> => {
  if (!isSuperAdmin.value) return

  isLoading.value = true
  try {
    // Limit to reasonable number of tenants for dropdown
    const response = await v2.staff.tenant.list({ active: true, per_page: 50 })
    tenants.value = response.data || []
  } catch (error) {
    log.error('Failed to load tenants', { error })
    tenants.value = []
  } finally {
    isLoading.value = false
  }
}

const selectTenant = async (tenant: Tenant | null): Promise<void> => {
  try {
    if (tenant) {
      authStore.setSelectedTenant(tenant.id)
      // Reload tenant config for the selected tenant
      await tenantStore.loadConfig()
    } else {
      authStore.clearSelectedTenant()
      await tenantStore.loadConfig()
    }
    isOpen.value = false

    // Reload the current page to apply new tenant context
    window.location.reload()
  } catch (error) {
    log.error('Failed to switch tenant', { error })
    // Still close dropdown and reload to reset state
    isOpen.value = false
    window.location.reload()
  }
}

const closeDropdown = () => {
  isOpen.value = false
}

// Load tenants when component mounts (if super admin)
onMounted(() => {
  if (isSuperAdmin.value) {
    loadTenants()
  }
})

// Watch for auth changes
watch(isSuperAdmin, (newVal) => {
  if (newVal) {
    loadTenants()
  }
})
</script>

<template>
  <div v-if="isSuperAdmin" class="relative">
    <!-- Trigger Button (compact) -->
    <button
      class="flex items-center gap-1.5 px-2 py-1 rounded-md bg-gray-800 hover:bg-gray-700 transition-colors text-xs"
      @click="isOpen = !isOpen"
    >
      <svg class="w-3.5 h-3.5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
      </svg>
      <span class="text-gray-200 max-w-[100px] truncate hidden sm:inline">{{ displayName }}</span>
      <svg
        class="w-3 h-3 text-gray-400 transition-transform"
        :class="{ 'rotate-180': isOpen }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <!-- Dropdown -->
    <div
      v-if="isOpen"
      class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg py-2 z-50"
    >
      <div class="px-3 py-2 border-b">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Cambiar Tenant</p>
      </div>

      <div v-if="isLoading" class="px-3 py-4 text-center text-gray-500 text-sm">
        Cargando...
      </div>

      <div v-else class="max-h-64 overflow-y-auto">
        <!-- Option to clear selection (use current tenant) -->
        <button
          v-if="selectedTenantId"
          class="w-full flex items-center gap-3 px-3 py-2 text-sm text-left hover:bg-gray-100 transition-colors text-amber-600"
          @click="selectTenant(null)"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span>Usar tenant actual</span>
        </button>

        <!-- Tenant list -->
        <button
          v-for="tenant in tenants"
          :key="tenant.id"
          class="w-full flex items-center gap-3 px-3 py-2 text-sm text-left hover:bg-gray-100 transition-colors"
          :class="{
            'bg-primary-50 text-primary-700': selectedTenantId === tenant.id,
            'text-gray-700': selectedTenantId !== tenant.id
          }"
          @click="selectTenant(tenant)"
        >
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold"
            :class="selectedTenantId === tenant.id ? 'bg-primary-600' : 'bg-gray-400'"
          >
            {{ tenant.name.charAt(0).toUpperCase() }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-medium truncate">{{ tenant.name }}</p>
            <p class="text-xs text-gray-500 truncate">{{ tenant.slug }}</p>
          </div>
          <svg
            v-if="selectedTenantId === tenant.id"
            class="w-5 h-5 text-primary-600 flex-shrink-0"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
          </svg>
        </button>

        <div v-if="tenants.length === 0 && !isLoading" class="px-3 py-4 text-center text-gray-500 text-sm">
          No hay tenants disponibles
        </div>
      </div>
    </div>

    <!-- Backdrop -->
    <div
      v-if="isOpen"
      class="fixed inset-0 z-40"
      @click="closeDropdown"
    />
  </div>
</template>
