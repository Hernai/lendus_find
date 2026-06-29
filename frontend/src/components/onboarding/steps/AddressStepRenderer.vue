<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useTenantStore } from '@/stores/tenant'
import { lookupPostalCode } from '@/services/v2/postalCode.service'
import { reverseGeocode } from '@/services/v2/geo.service'

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
  latitude?: number | null
  longitude?: number | null
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
  latitude: props.modelValue?.latitude ?? null,
  longitude: props.modelValue?.longitude ?? null,
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

// --- Autollenado por código postal (SEPOMEX) ---
const cpLoading = ref(false)
const cpNotFound = ref(false)
const coloniaOptions = ref<string[]>([])
// Cuando hay colonias del CP, mostramos un select; "Otra" revela el texto libre.
const coloniaIsOther = ref(false)
let cpTimer: ReturnType<typeof setTimeout> | null = null
let lastLookupCp = ''

// Normaliza para comparar nombres de estado (mayúsculas, sin acentos).
const norm = (s: string) =>
  s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().trim()

function matchStateValue(estado: string): string {
  const target = norm(estado)
  const found = mexicanStates.value.find(
    (s) => norm(s.value) === target || norm(s.label) === target,
  )
  return found?.value ?? form.value.state
}

async function lookupCp(cp: string) {
  if (cp === lastLookupCp) return
  lastLookupCp = cp
  cpLoading.value = true
  cpNotFound.value = false
  try {
    const res = await lookupPostalCode(cp)
    if (!res) {
      cpNotFound.value = true
      coloniaOptions.value = []
      return
    }
    // Autollenar estado/municipio/ciudad.
    form.value.state = matchStateValue(res.estado)
    form.value.municipality = res.municipio || form.value.municipality
    if (res.ciudad) form.value.city = res.ciudad
    // Colonias: si hay una sola, la fijamos; si hay varias, dropdown.
    coloniaOptions.value = res.colonias.map((c) => c.nombre)
    coloniaIsOther.value = false
    if (form.value.neighborhood && !coloniaOptions.value.includes(form.value.neighborhood)) {
      // Conserva una colonia ya puesta (p.ej. de geolocalización) como opción.
      coloniaOptions.value.unshift(form.value.neighborhood)
    } else if (!form.value.neighborhood && coloniaOptions.value.length === 1) {
      form.value.neighborhood = coloniaOptions.value[0]!
    }
  } finally {
    cpLoading.value = false
  }
}

// --- "Estoy en mi domicilio": geolocalización del dispositivo ---
const geoLoading = ref(false)
const geoError = ref('')
const geoCaptured = ref(false)

function useMyLocation() {
  geoError.value = ''
  if (!('geolocation' in navigator)) {
    geoError.value = 'Tu dispositivo no permite geolocalización.'
    return
  }
  geoLoading.value = true
  navigator.geolocation.getCurrentPosition(
    async (pos) => {
      const { latitude, longitude } = pos.coords
      form.value.latitude = latitude
      form.value.longitude = longitude
      try {
        const res = await reverseGeocode(latitude, longitude)
        if (res && res.source === 'google') {
          if (res.state) form.value.state = matchStateValue(res.state)
          if (res.municipality) form.value.municipality = res.municipality
          if (res.city) form.value.city = res.city
          if (res.neighborhood) form.value.neighborhood = res.neighborhood
          if (res.street) form.value.street = res.street
          if (res.ext_number) form.value.ext_number = res.ext_number
          // El CP dispara además el autollenado de colonias (SEPOMEX).
          if (res.postal_code) form.value.postal_code = res.postal_code
        }
        geoCaptured.value = true
      } finally {
        geoLoading.value = false
      }
    },
    (err) => {
      geoLoading.value = false
      geoError.value = err.code === err.PERMISSION_DENIED
        ? 'Permiso de ubicación denegado. Actívalo o llena tu domicilio a mano.'
        : 'No se pudo obtener tu ubicación. Intenta de nuevo.'
    },
    { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 },
  )
}

// Dispara la consulta (con debounce) cuando el CP tiene 5 dígitos.
watch(() => form.value.postal_code, (cp) => {
  if (cpTimer) clearTimeout(cpTimer)
  if (!/^\d{5}$/.test(cp)) {
    cpNotFound.value = false
    return
  }
  cpTimer = setTimeout(() => lookupCp(cp), 350)
})

function onColoniaSelect(value: string) {
  if (value === '__other') {
    coloniaIsOther.value = true
    form.value.neighborhood = ''
  } else {
    coloniaIsOther.value = false
    form.value.neighborhood = value
  }
}

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

    <!-- Estoy en mi domicilio: geolocaliza y autollena -->
    <button type="button" class="geo-btn" :disabled="geoLoading" @click="useMyLocation">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 21s7-5.7 7-11a7 7 0 10-14 0c0 5.3 7 11 7 11z" stroke="currentColor" stroke-width="1.6" />
        <circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="1.6" />
      </svg>
      <span>{{ geoLoading ? 'Obteniendo tu ubicación…' : 'Estoy en mi domicilio' }}</span>
    </button>
    <p v-if="geoError" class="cp-hint cp-hint--warn">{{ geoError }}</p>
    <p v-else-if="geoCaptured" class="cp-hint cp-hint--ok">
      Ubicación capturada{{ form.latitude && form.postal_code ? ' y domicilio autollenado' : '. Ingresa tu CP para autollenar tu colonia.' }}
    </p>

    <div class="grid-2">
      <div class="field" :class="{ 'field--ok': isCpValid, 'field--err': form.postal_code.length === 5 && !isCpValid }">
        <label>Código postal</label>
        <input v-model="form.postal_code" type="text" inputmode="numeric" maxlength="5" placeholder="06700" />
        <span v-if="cpLoading" class="cp-hint">Buscando colonia…</span>
        <span v-else-if="cpNotFound" class="cp-hint cp-hint--warn">CP no encontrado — llena los campos a mano.</span>
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
      <!-- Si el CP trajo colonias, dropdown; "Otra" revela el texto libre. -->
      <select
        v-if="coloniaOptions.length > 0 && !coloniaIsOther"
        :value="form.neighborhood"
        @change="onColoniaSelect(($event.target as HTMLSelectElement).value)"
      >
        <option value="" disabled>Selecciona tu colonia</option>
        <option v-for="c in coloniaOptions" :key="c" :value="c">{{ c }}</option>
        <option value="__other">Otra (escribir)…</option>
      </select>
      <input v-else v-model="form.neighborhood" type="text" placeholder="Ej. Roma Norte" />
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
.cp-hint { font-size: 12px; color: #64748b; }
.cp-hint--warn { color: #b45309; }
.cp-hint--ok { color: #15803d; }
.geo-btn {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  width: 100%;
  padding: 12px 14px;
  border: 1.5px solid var(--tenant-primary, #5B21B6);
  border-radius: 12px;
  background: #ffffff;
  color: var(--tenant-primary, #5B21B6);
  font-size: 14px; font-weight: 700;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}
.geo-btn:disabled { opacity: 0.6; cursor: progress; }
.geo-btn svg { width: 20px; height: 20px; }

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
