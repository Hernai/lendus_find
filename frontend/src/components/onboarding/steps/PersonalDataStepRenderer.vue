<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useTenantStore } from '@/stores/tenant'

/**
 * Step `personal_data`: datos personales del solicitante.
 * Se usa cuando NO hay proveedor KYC activo (Nubarium) que los derive del INE.
 *
 * Pide: nombre, apellidos, fecha de nacimiento, género, nacionalidad, estado
 * de nacimiento y RFC.
 */

interface PersonalExtra {
  first_name: string
  last_name: string
  second_last_name: string
  birth_date: string
  gender: 'M' | 'F' | ''
  is_mexican: 'SI' | 'NO' | ''
  birth_state: string
  nationality: string
  rfc: string
}

const props = defineProps<{
  step: { id: string; type: string; label: string }
  modelValue: PersonalExtra | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: PersonalExtra]
}>()

const tenantStore = useTenantStore()

const form = ref<PersonalExtra>({
  first_name: props.modelValue?.first_name ?? '',
  last_name: props.modelValue?.last_name ?? '',
  second_last_name: props.modelValue?.second_last_name ?? '',
  birth_date: props.modelValue?.birth_date ?? '',
  gender: props.modelValue?.gender ?? '',
  is_mexican: props.modelValue?.is_mexican ?? 'SI',
  birth_state: props.modelValue?.birth_state ?? '',
  nationality: props.modelValue?.nationality ?? 'MEX',
  rfc: props.modelValue?.rfc ?? '',
})

const mexicanStates = computed(() => {
  const list = (tenantStore.options as Record<string, Array<{ value: string; label: string }>>)?.mexicanState ?? []
  return list
})

// Date picker día / mes / año (más usable en mobile que <input type="date">).
const MONTHS = [
  { value: '01', label: 'Enero' }, { value: '02', label: 'Febrero' }, { value: '03', label: 'Marzo' },
  { value: '04', label: 'Abril' }, { value: '05', label: 'Mayo' }, { value: '06', label: 'Junio' },
  { value: '07', label: 'Julio' }, { value: '08', label: 'Agosto' }, { value: '09', label: 'Septiembre' },
  { value: '10', label: 'Octubre' }, { value: '11', label: 'Noviembre' }, { value: '12', label: 'Diciembre' },
]
const currentYear = new Date().getFullYear()
const YEARS = Array.from({ length: 80 }, (_, i) => String(currentYear - 18 - i))
function parseBirth(b: string): { d: string; m: string; y: string } {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(b || '')
  return m ? { y: m[1]!, m: m[2]!, d: m[3]! } : { y: '', m: '', d: '' }
}
const birth = ref(parseBirth(form.value.birth_date))
const daysIn = computed(() => {
  if (!birth.value.m || !birth.value.y) return 31
  return new Date(Number(birth.value.y), Number(birth.value.m), 0).getDate()
})
const DAYS = computed(() => Array.from({ length: daysIn.value }, (_, i) => String(i + 1).padStart(2, '0')))
watch(birth, (b) => {
  form.value.birth_date = (b.y && b.m && b.d) ? `${b.y}-${b.m}-${b.d}` : ''
}, { deep: true })

const RFC_REGEX = /^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/
const isRfcValid = computed(() => RFC_REGEX.test(form.value.rfc.toUpperCase()))

const isComplete = computed(() => {
  const f = form.value
  if (f.first_name.trim().length < 2) return false
  if (f.last_name.trim().length < 2) return false
  if (f.birth_date.length !== 10) return false
  if (f.gender !== 'M' && f.gender !== 'F') return false
  if (f.is_mexican !== 'SI' && f.is_mexican !== 'NO') return false
  if (f.is_mexican === 'SI' && !f.birth_state) return false
  if (!isRfcValid.value) return false
  return true
})

function onRfcInput(event: Event) {
  form.value.rfc = (event.target as HTMLInputElement).value.toUpperCase().replace(/\s/g, '')
}

onMounted(async () => {
  if (mexicanStates.value.length === 0 && !tenantStore.isLoaded) {
    try { await tenantStore.loadConfig() } catch { /* noop */ }
  }
})

watch(form, () => {
  if (isComplete.value) {
    emit('update:modelValue', { ...form.value, rfc: form.value.rfc.toUpperCase() })
  }
}, { deep: true })
</script>

<template>
  <div class="step-personal">
    <p class="step-hint">
      Captura tus datos personales tal como aparecen en tu identificación.
    </p>

    <div class="field">
      <label>Nombre(s)</label>
      <input v-model="form.first_name" type="text" autocomplete="given-name" placeholder="Ej. Juan Carlos" />
    </div>
    <div class="grid-2">
      <div class="field">
        <label>Apellido paterno</label>
        <input v-model="form.last_name" type="text" autocomplete="family-name" placeholder="Ej. Pérez" />
      </div>
      <div class="field">
        <label>Apellido materno</label>
        <input v-model="form.second_last_name" type="text" placeholder="Ej. López" />
      </div>
    </div>

    <div class="field">
      <label>Fecha de nacimiento</label>
      <div class="date-row">
        <select v-model="birth.d">
          <option value="" disabled>Día</option>
          <option v-for="d in DAYS" :key="d" :value="d">{{ Number(d) }}</option>
        </select>
        <select v-model="birth.m">
          <option value="" disabled>Mes</option>
          <option v-for="mo in MONTHS" :key="mo.value" :value="mo.value">{{ mo.label }}</option>
        </select>
        <select v-model="birth.y">
          <option value="" disabled>Año</option>
          <option v-for="y in YEARS" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
    </div>

    <div class="field">
      <label>Género</label>
      <div class="seg-row">
        <button
          type="button"
          class="seg-btn"
          :class="{ 'seg-btn--active': form.gender === 'M' }"
          @click="form.gender = 'M'"
        >Hombre</button>
        <button
          type="button"
          class="seg-btn"
          :class="{ 'seg-btn--active': form.gender === 'F' }"
          @click="form.gender = 'F'"
        >Mujer</button>
      </div>
    </div>

    <div class="field">
      <label>Nacionalidad</label>
      <div class="seg-row">
        <button
          type="button"
          class="seg-btn"
          :class="{ 'seg-btn--active': form.is_mexican === 'SI' }"
          @click="form.is_mexican = 'SI'; form.nationality = 'MEX'"
        >Mexicano/a</button>
        <button
          type="button"
          class="seg-btn"
          :class="{ 'seg-btn--active': form.is_mexican === 'NO' }"
          @click="form.is_mexican = 'NO'"
        >Extranjero/a</button>
      </div>
    </div>

    <div v-if="form.is_mexican === 'SI'" class="field">
      <label>Estado de nacimiento</label>
      <select v-model="form.birth_state">
        <option value="" disabled>Selecciona tu estado</option>
        <option v-for="s in mexicanStates" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
    </div>
    <div v-else class="field">
      <label>País de nacimiento</label>
      <input v-model="form.nationality" type="text" placeholder="Ej. Colombia" />
    </div>

    <div class="field" :class="{ 'field--ok': isRfcValid, 'field--err': form.rfc.length >= 12 && !isRfcValid }">
      <label>RFC</label>
      <input :value="form.rfc" type="text" maxlength="13" placeholder="13 caracteres con homoclave" @input="onRfcInput($event)" />
      <p v-if="form.rfc.length >= 12 && !isRfcValid" class="err">RFC inválido</p>
    </div>
  </div>
</template>

<style scoped>
.step-personal { display: flex; flex-direction: column; gap: 14px; box-sizing: border-box; }
.step-personal * { box-sizing: border-box; }
.step-hint { font-size: 13.5px; color: #475569; margin: 0; line-height: 1.5; }
.grid-2 { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 12px; }
.date-row { display: grid; grid-template-columns: 0.8fr 1.4fr 1fr; gap: 8px; }
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
.err { margin: 0; font-size: 11.5px; color: #ef4444; }

.seg-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 6px;
}
.seg-btn {
  padding: 11px 4px;
  background: #ffffff;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  font-size: 13px; font-weight: 600;
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
