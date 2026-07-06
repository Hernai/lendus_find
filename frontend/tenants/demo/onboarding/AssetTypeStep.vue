<script setup lang="ts">
import { computed, watch } from 'vue'
import { useTenantStore } from '@/stores/tenant'
import { useApplicationStore } from '@/stores/application'

/**
 * Paso custom (tenant demo, arrendamiento): el aplicante elige UN tipo de activo
 * del catálogo que el admin configuró en el producto (`rules.lease.asset_types`)
 * y captura una descripción + valor estimado del bien. El resultado va a
 * `applications.metadata.lease` (incluye snapshot de la modalidad del producto).
 */

interface LeaseValue {
  asset_type: string | null
  modality: string | null
  asset_description: string
  asset_estimated_value: number | null
}

const props = defineProps<{
  step: { id: string; label?: string }
  modelValue: LeaseValue | null
  formData?: Record<string, unknown>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: LeaseValue]
  'update:valid': [valid: boolean]
}>()

const tenantStore = useTenantStore()
const applicationStore = useApplicationStore()

interface Option { value: string; label: string }

// Config de arrendamiento del producto seleccionado (la fija el admin).
const lease = computed(
  () => (applicationStore.selectedProduct?.rules as Record<string, unknown> | undefined)?.lease as
    { modality?: string; asset_types?: string[] } | undefined,
)

// Catálogo de activos permitidos por ESTE producto, con label del enum AssetType.
const allowedAssets = computed<Option[]>(() => {
  const allowed = lease.value?.asset_types ?? []
  const catalog = (tenantStore.options.assetType ?? []) as Option[]
  const byValue = new Map(catalog.map((o) => [o.value, o.label]))
  return allowed.map((v) => ({ value: v, label: byValue.get(v) ?? v }))
})

const modality = computed(() => lease.value?.modality ?? null)

const current = computed<LeaseValue>(() => ({
  asset_type: props.modelValue?.asset_type ?? null,
  modality: props.modelValue?.modality ?? modality.value,
  asset_description: props.modelValue?.asset_description ?? '',
  asset_estimated_value: props.modelValue?.asset_estimated_value ?? null,
}))

const isValid = computed(() => !!current.value.asset_type)
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

function update(patch: Partial<LeaseValue>) {
  emit('update:modelValue', { ...current.value, modality: modality.value, ...patch })
}

function pickAsset(value: string) {
  update({ asset_type: value })
}

function assetIcon(value: string): string {
  if (value === 'SOLAR_PANELS') return 'sun'
  if (value === 'VEHICLE') return 'car'
  return 'cog'
}
</script>

<template>
  <div class="step-asset">
    <h2 v-if="step.label" class="step-section-title">{{ step.label }}</h2>

    <ul class="options-list">
      <li v-for="opt in allowedAssets" :key="opt.value">
        <button
          type="button"
          class="option-row"
          :class="{ 'option-row--active': current.asset_type === opt.value }"
          @click="pickAsset(opt.value)"
        >
          <span class="option-icon" aria-hidden="true">
            <svg v-if="assetIcon(opt.value) === 'sun'" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.8" />
              <path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <svg v-else-if="assetIcon(opt.value) === 'car'" viewBox="0 0 24 24" fill="none">
              <path d="M4 16v-3l2-5h12l2 5v3" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <path d="M4 16h16v2H4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <circle cx="7.5" cy="18.5" r="1.5" stroke="currentColor" stroke-width="1.6" />
              <circle cx="16.5" cy="18.5" r="1.5" stroke="currentColor" stroke-width="1.6" />
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8" />
              <path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9L17 7M7 17l-2.1 2.1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </span>
          <span class="option-label">{{ opt.label }}</span>
          <span class="option-radio" :class="{ 'option-radio--active': current.asset_type === opt.value }">
            <svg v-if="current.asset_type === opt.value" viewBox="0 0 24 24" fill="none">
              <path d="M5 12l5 5L20 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
        </button>
      </li>
      <li v-if="allowedAssets.length === 0" class="empty-hint">
        Este producto aún no tiene tipos de activo configurados.
      </li>
    </ul>

    <label class="field">
      <span class="field-label">Descripción del bien (opcional)</span>
      <input
        class="field-input"
        type="text"
        :value="current.asset_description"
        placeholder="Ej. Panel solar 450W, camioneta 2024…"
        @input="update({ asset_description: ($event.target as HTMLInputElement).value })"
      />
    </label>

    <label class="field">
      <span class="field-label">Valor estimado del bien (opcional)</span>
      <input
        class="field-input"
        type="number"
        inputmode="numeric"
        :value="current.asset_estimated_value ?? ''"
        placeholder="$"
        @input="update({ asset_estimated_value: Number(($event.target as HTMLInputElement).value) || null })"
      />
    </label>
  </div>
</template>

<style scoped>
.step-asset { display: flex; flex-direction: column; gap: 12px; }
.step-section-title { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; }
.options-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.option-row {
  width: 100%; display: flex; align-items: center; gap: 12px; padding: 14px 16px;
  background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; cursor: pointer;
  transition: border-color 140ms ease, background 140ms ease; min-height: 56px;
}
.option-row--active { border-color: var(--tenant-primary, #5B21B6); background: rgb(var(--surface-soft-rgb, 243 242 250) / 1); }
.option-icon { width: 24px; height: 24px; color: var(--tenant-primary, #5B21B6); display: grid; place-items: center; flex-shrink: 0; }
.option-icon svg { width: 100%; height: 100%; }
.option-label { flex: 1; text-align: left; font-size: 14.5px; font-weight: 600; color: #0f172a; }
.option-row--active .option-label { color: var(--tenant-primary, #5B21B6); }
.option-radio { width: 22px; height: 22px; border-radius: 999px; border: 2px solid #cbd5e1; display: grid; place-items: center; flex-shrink: 0; }
.option-radio--active { background: var(--tenant-primary, #5B21B6); border-color: var(--tenant-primary, #5B21B6); }
.option-radio svg { width: 12px; height: 12px; }
.empty-hint { font-size: 13px; color: #64748b; padding: 8px 2px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field-label { font-size: 13px; font-weight: 600; color: #334155; }
.field-input {
  border: 1.5px solid #e5e7eb; border-radius: 12px; padding: 12px 14px; font-size: 14.5px;
  color: #0f172a; background: #fff;
}
.field-input:focus { outline: none; border-color: var(--tenant-primary, #5B21B6); }
</style>
