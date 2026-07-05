<script setup lang="ts">
import { storage, STORAGE_KEYS } from '@/utils/storage'

interface Tenant {
  id: string
  slug: string
  name: string
}

const props = defineProps<{
  tenants: Tenant[]
}>()

const emit = defineEmits<{
  selected: [tenant: Tenant]
}>()

const choose = (tenant: Tenant) => {
  storage.set(STORAGE_KEYS.CURRENT_TENANT_SLUG, tenant.slug)
  storage.set(STORAGE_KEYS.SELECTED_TENANT_ID, tenant.id)
  emit('selected', tenant)
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 px-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6">
      <h2 class="text-xl font-bold text-slate-900 mb-1">Selecciona un tenant</h2>
      <p class="text-sm text-slate-500 mb-5">
        Como super administrador puedes acceder a cualquiera. Podrás cambiar
        después desde el panel.
      </p>

      <div class="space-y-2 max-h-80 overflow-y-auto">
        <button
          v-for="t in props.tenants"
          :key="t.id"
          type="button"
          class="w-full flex items-center justify-between p-3 bg-slate-50 hover:bg-primary-50 border-2 border-transparent hover:border-primary-500 rounded-xl transition text-left"
          @click="choose(t)"
        >
          <div>
            <p class="font-semibold text-slate-900">{{ t.name }}</p>
            <p class="text-xs text-slate-500">{{ t.slug }}</p>
          </div>
          <svg class="w-5 h-5 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>
