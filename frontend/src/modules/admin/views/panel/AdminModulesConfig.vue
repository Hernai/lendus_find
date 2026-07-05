<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores'
import { staff } from '@/modules/admin/services'
import {
  ADMIN_MODULE_CATEGORIES,
  OVERRIDABLE_MODULES,
  OVERRIDABLE_ROLES,
  type AdminModule,
  type OverridableRole,
} from '@/constants/admin-modules'
import { useToast } from '@/composables/useToast'

// Filas de la matriz agrupadas por sección del catálogo (Operaciones /
// Administración / Configuración). Secciones sin módulos overridables se
// omiten (ej. Configuración si todos sus módulos son superAdminOnly).
const MODULE_GROUPS = ADMIN_MODULE_CATEGORIES
  .map((cat) => ({
    ...cat,
    modules: OVERRIDABLE_MODULES.filter((m) => m.category === cat.key),
  }))
  .filter((g) => g.modules.length > 0)

const authStore = useAuthStore()
const toast = useToast()

interface TenantOption {
  id: string
  slug: string
  name: string
}

const tenants = ref<TenantOption[]>([])
const selectedTenantId = ref<string>('')
const loading = ref(false)
const saving = ref(false)

// Matriz reactiva: `matrix[role][module_key] = enabled?`
// `undefined` significa "usa el default del catálogo".
type MatrixCell = boolean | undefined
const matrix = ref<Record<OverridableRole, Record<string, MatrixCell>>>({
  ANALYST: {},
  SUPERVISOR: {},
  ADMIN: {},
})

const loadTenants = async () => {
  const res = await staff.tenant.list({ active: true, per_page: 50 })
  tenants.value = (res.data?.tenants ?? []).map((t) => ({ id: t.id, slug: t.slug, name: t.name }))
  if (!selectedTenantId.value && tenants.value.length > 0) {
    selectedTenantId.value = tenants.value[0]!.id
  }
}

const loadOverrides = async (tenantId: string) => {
  if (!tenantId) return
  loading.value = true
  matrix.value = { ANALYST: {}, SUPERVISOR: {}, ADMIN: {} }
  try {
    const res = await staff.tenant.getModules(tenantId)
    for (const o of res.data?.overrides ?? []) {
      const role = o.role as OverridableRole
      if (matrix.value[role]) {
        matrix.value[role][o.module_key] = o.enabled
      }
    }
  } finally {
    loading.value = false
  }
}

const defaultFor = (mod: AdminModule, role: OverridableRole): boolean => {
  return mod.defaultRoles.includes(role)
}

const effectiveValue = (mod: AdminModule, role: OverridableRole): boolean => {
  const cell = matrix.value[role][mod.key]
  return cell ?? defaultFor(mod, role)
}

const hasOverride = (mod: AdminModule, role: OverridableRole): boolean => {
  return matrix.value[role][mod.key] !== undefined
}

const toggle = (mod: AdminModule, role: OverridableRole) => {
  const current = effectiveValue(mod, role)
  const target = !current
  if (target === defaultFor(mod, role)) {
    // Volvió al default — borrar el override.
    delete matrix.value[role][mod.key]
  } else {
    matrix.value[role][mod.key] = target
  }
}

const resetCell = (mod: AdminModule, role: OverridableRole) => {
  delete matrix.value[role][mod.key]
}

const overridesPayload = computed(() => {
  const payload: Array<{ role: OverridableRole; module_key: string; enabled: boolean }> = []
  for (const role of OVERRIDABLE_ROLES) {
    for (const [mkey, val] of Object.entries(matrix.value[role])) {
      if (val !== undefined) {
        payload.push({ role, module_key: mkey, enabled: val })
      }
    }
  }
  return payload
})

const save = async () => {
  if (!selectedTenantId.value) return
  saving.value = true
  try {
    await staff.tenant.updateModules(selectedTenantId.value, overridesPayload.value)
    toast.success('Configuración guardada')
    // Si el super admin estaba editando el tenant que tiene activo,
    // refrescar el sidebar.
    if (authStore.user && selectedTenantId.value === authStore.selectedTenantId) {
      // Recargar para que el login response refresque module_overrides.
      // (Tradeoff: simple y robusto vs. mantener sync local manualmente.)
      location.reload()
    }
  } catch (e) {
    toast.error('No se pudo guardar la configuración')
    console.error(e)
  } finally {
    saving.value = false
  }
}

watch(selectedTenantId, (id) => {
  if (id) loadOverrides(id)
})

onMounted(async () => {
  await loadTenants()
  if (selectedTenantId.value) await loadOverrides(selectedTenantId.value)
})
</script>

<template>
  <div class="p-4 md:p-6 max-w-6xl mx-auto">
    <div class="mb-4">
      <h1 class="text-xl font-bold text-gray-900">Módulos por rol</h1>
      <p class="text-sm text-gray-500">
        Habilita o deshabilita módulos del backoffice por (tenant × rol). Las celdas
        sin marca personalizada usan el default del catálogo.
      </p>
    </div>

    <!-- Selector de tenant -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-4">
      <label class="block text-xs font-medium text-gray-700 mb-1.5">Tenant</label>
      <select
        v-model="selectedTenantId"
        class="w-full md:w-72 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500"
      >
        <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }} ({{ t.slug }})</option>
      </select>
    </div>

    <!-- Matriz -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-500 text-sm">Cargando…</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 text-xs font-medium text-gray-600 uppercase tracking-wider">
          <tr>
            <th class="px-4 py-3 text-left">Módulo</th>
            <th v-for="role in OVERRIDABLE_ROLES" :key="role" class="px-4 py-3 text-center">
              {{ role }}
            </th>
            <th class="px-4 py-3 text-center">Default</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <template v-for="group in MODULE_GROUPS" :key="group.key">
            <!-- Header de sección -->
            <tr class="bg-gray-50/70">
              <td colspan="5" class="px-4 py-2">
                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                  {{ group.label }}
                </span>
              </td>
            </tr>
            <tr v-for="mod in group.modules" :key="mod.key">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900">{{ mod.label }}</div>
              <div v-if="mod.requiresFeature" class="text-xs text-gray-500">
                requiere feature <code>{{ mod.requiresFeature }}</code>
              </div>
            </td>
            <td v-for="role in OVERRIDABLE_ROLES" :key="role" class="px-4 py-3 text-center">
              <button
                type="button"
                class="inline-flex items-center justify-center w-7 h-7 rounded-md border-2 transition"
                :class="[
                  effectiveValue(mod, role)
                    ? 'border-primary-500 bg-primary-500 text-white'
                    : 'border-gray-300 bg-white text-transparent',
                  hasOverride(mod, role) ? 'ring-2 ring-amber-300 ring-offset-1' : '',
                ]"
                :title="hasOverride(mod, role) ? 'Override activo — click para alternar, doble-click para restaurar default' : 'Click para activar override'"
                @click="toggle(mod, role)"
                @dblclick="resetCell(mod, role)"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
              </button>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500 text-center whitespace-nowrap">
              {{ mod.defaultRoles.filter(r => r !== 'SUPER_ADMIN').join(', ') || '—' }}
            </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Footer -->
    <div class="mt-4 flex items-center justify-between">
      <p class="text-xs text-gray-500">
        <span class="inline-block w-3 h-3 rounded-sm ring-2 ring-amber-300 mr-1"></span>
        Celdas con anillo ámbar tienen override activo. Doble-click restaura el default.
      </p>
      <button
        type="button"
        class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="saving || !selectedTenantId"
        @click="save"
      >
        {{ saving ? 'Guardando…' : `Guardar (${overridesPayload.length} overrides)` }}
      </button>
    </div>
  </div>
</template>
