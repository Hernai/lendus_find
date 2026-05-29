<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useTenantStore } from '@/stores/tenant'

/**
 * Step `address`: domicilio completo (calle, número, colonia, ciudad, estado,
 * CP, antigüedad). Coincide con el Step3Address legacy de LendusFind.
 */

interface AddressData {
  postal_code: string
  state: string
  municipality: string
  city: string
  neighborhood: string
  street: string
  ext_number: string
  int_number: string
  housing_type: 'OWN' | 'RENT' | 'FAMILY' | 'OTHER' | ''
  years_at_address: number
  months_at_address: number
}

const props = defineProps<{
  step: { id: string; type: string; label: string }
  modelValue: AddressData | null
  /** Datos acumulados del onboarding (para prellenar desde el step `location`). */
  formData?: Record<string, unknown>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: AddressData]
}>()

const tenantStore = useTenantStore()

// El step `location` (state_city) ya capturó estado y ciudad; los usamos como
// prellenado del domicilio para no pedirlos dos veces.
const prefillState = String((props.formData?.state as string | undefined) ?? '')
const prefillCity = String((props.formData?.city as string | undefined) ?? '')

const form = ref<AddressData>({
  postal_code: props.modelValue?.postal_code ?? '',
  state: props.modelValue?.state ?? prefillState ?? '',
  municipality: props.modelValue?.municipality ?? '',
  city: props.modelValue?.city ?? prefillCity ?? '',
  neighborhood: props.modelValue?.neighborhood ?? '',
  street: props.modelValue?.street ?? '',
  ext_number: props.modelValue?.ext_number ?? '',
  int_number: props.modelValue?.int_number ?? '',
  housing_type: props.modelValue?.housing_type ?? '',
  years_at_address: props.modelValue?.years_at_address ?? 0,
  months_at_address: props.modelValue?.months_at_address ?? 0,
})

const mexicanStates = computed(() => {
  const list = (tenantStore.options as Record<string, Array<{ value: string; label: string }>>)?.mexicanState ?? []
  return list
})

const housingOptions = [
  { value: 'OWN', label: 'Propia' },
  { value: 'RENT', label: 'Rentada' },
  { value: 'FAMILY', label: 'Familiar' },
  { value: 'OTHER', label: 'Otra' },
]

const isCpValid = computed(() => /^\d{5}$/.test(form.value.postal_code))

const isComplete = computed(() => {
  const f = form.value
  return (
    isCpValid.value &&
    !!f.state &&
    f.municipality.trim().length >= 2 &&
    f.neighborhood.trim().length >= 2 &&
    f.street.trim().length >= 2 &&
    f.ext_number.trim().length >= 1 &&
    !!f.housing_type &&
    (f.years_at_address > 0 || f.months_at_address > 0)
  )
})

onMounted(async () => {
  if (mexicanStates.value.length === 0 && !tenantStore.isLoaded) {
    try { await tenantStore.loadConfig() } catch { /* noop */ }
  }
})

watch(form, () => {
  if (isComplete.value) {
    emit('update:modelValue', { ...form.value })
  }
}, { deep: true })
</script>

<template>
  <div class="step-address">
    <p class="step-hint">
      Ingresa los datos de tu domicilio actual. Debe coincidir con tu
      comprobante de domicilio.
    </p>

    <div class="grid-2">
      <div class="field" :class="{ 'field--ok': isCpValid, 'field--err': form.postal_code.length === 5 && !isCpValid }">
        <label>Código postal</label>
        <input v-model="form.postal_code" type="text" inputmode="numeric" maxlength="5" placeholder="06700" />
      </div>
      <div class="field">
        <label>Estado</label>
        <select v-model="form.state">
          <option value="" disabled>Selecciona</option>
          <option v-for="s in mexicanStates" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
      </div>
    </div>

    <div class="grid-2">
      <div class="field">
        <label>Municipio / Alcaldía</label>
        <input v-model="form.municipality" type="text" placeholder="Ej. Cuauhtémoc" />
      </div>
      <div class="field">
        <label>Ciudad (opcional)</label>
        <input v-model="form.city" type="text" placeholder="Ej. CDMX" />
      </div>
    </div>

    <div class="field">
      <label>Colonia</label>
      <input v-model="form.neighborhood" type="text" placeholder="Ej. Roma Norte" />
    </div>

    <div class="field">
      <label>Calle</label>
      <input v-model="form.street" type="text" placeholder="Ej. Av. Reforma" />
    </div>

    <div class="grid-2">
      <div class="field">
        <label>Núm. ext.</label>
        <input v-model="form.ext_number" type="text" inputmode="numeric" placeholder="123" />
      </div>
      <div class="field">
        <label>Núm. int. (opcional)</label>
        <input v-model="form.int_number" type="text" placeholder="A-1" />
      </div>
    </div>

    <div class="field">
      <label>Tipo de vivienda</label>
      <div class="seg-row seg-row--4">
        <button
          v-for="opt in housingOptions"
          :key="opt.value"
          type="button"
          class="seg-btn"
          :class="{ 'seg-btn--active': form.housing_type === opt.value }"
          @click="form.housing_type = opt.value as AddressData['housing_type']"
        >{{ opt.label }}</button>
      </div>
    </div>

    <div class="field">
      <label>Antigüedad en el domicilio</label>
      <div class="grid-2">
        <div class="field-inline">
          <input v-model.number="form.years_at_address" type="number" min="0" max="80" />
          <span>Años</span>
        </div>
        <div class="field-inline">
          <input v-model.number="form.months_at_address" type="number" min="0" max="11" />
          <span>Meses</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.step-address { display: flex; flex-direction: column; gap: 14px; box-sizing: border-box; }
.step-address * { box-sizing: border-box; }
.step-hint { font-size: 13.5px; color: #475569; margin: 0; line-height: 1.5; }
.grid-2 {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 12px;
}
.field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.field label { font-size: 12.5px; color: #475569; font-weight: 600; }
.field input,
.field select {
  width: 100%; min-width: 0;
  padding: 12px 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  font-size: 14px; color: #0f172a;
  background: #ffffff;
  font-family: inherit;
  outline: none;
}
.field input:focus, .field select:focus { border-color: var(--tenant-primary, #5B21B6); }
.field--ok input { border-color: #16a34a; }
.field--err input { border-color: #ef4444; }

.field-inline {
  display: flex; align-items: center; gap: 6px;
}
.field-inline input { flex: 1; }
.field-inline span { font-size: 12.5px; color: #64748b; }

.seg-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 6px;
}
.seg-row--4 {
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr);
}
.seg-btn {
  padding: 11px 4px;
  background: #ffffff;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  font-size: 12.5px; font-weight: 600;
  color: #475569;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  min-width: 0;
  white-space: nowrap;
}
.seg-btn--active {
  background: var(--tenant-primary, #5B21B6);
  border-color: var(--tenant-primary, #5B21B6);
  color: #ffffff;
}
</style>
