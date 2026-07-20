<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useTenantStore } from '@/stores/tenant'
import { fetchMunicipalities } from '@/services/v2/postalCode.service'
import type { StateCityStep } from '@/types/v2/onboardingStep'

/**
 * Renderiza step `state_city`: combina selector de estado (bottom sheet)
 * + selector de municipio del catálogo SEPOMEX.
 *
 * Layout:
 *  - Fila "Estado" tipo dropdown → tap abre sheet con todos los estados
 *  - Lista de municipios del estado (radio rows), servida por el endpoint
 *    público de municipios (postal_codes). Reemplaza la lista estática previa.
 *  - Si el catálogo no tiene municipios para ese estado (sin datos/offline),
 *    se degrada a captura manual — NUNCA como vía principal.
 *
 * Tenant-agnóstico.
 */

const props = defineProps<{
  step: StateCityStep
  modelValue: { state: string; city: string } | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: { state: string; city: string }]
  'update:valid': [valid: boolean]
}>()

// Validez del paso (contrato): estado Y ciudad presentes en el valor emitido.
// Se computa del modelValue (el valor comprometido) para igualar legacyCanContinue,
// congelado en stepValidation.spec.ts (incluye el caso de ciudad custom).
const isValid = computed(() => {
  const sc = props.modelValue
  return !!sc && !!sc.state && !!sc.city
})
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

const tenantStore = useTenantStore()

const state = ref<string>(props.modelValue?.state ?? '')
const city = ref<string>(props.modelValue?.city ?? '')
const customCity = ref<string>(props.modelValue?.city ?? '')
const stateSheetOpen = ref(false)

const stateOptions = computed(() => tenantStore.options.mexicanState ?? [])

const stateLabel = computed(() => {
  if (!state.value) return ''
  return stateOptions.value.find((o) => o.value === state.value)?.label ?? state.value
})

// Municipios del estado seleccionado, servidos por el catálogo SEPOMEX
// (postal_codes) vía endpoint público. El código de estado enviado es el valor
// del enum MexicanState (p. ej. `CDMX`, `JAL`) — el mismo que emite el selector,
// lo que corrige el bug histórico `CMX` (nunca empataba y dejaba CDMX vacío).
const municipalities = ref<string[]>([])
const loadingMunicipalities = ref(false)

async function loadMunicipalities(stateCode: string) {
  municipalities.value = []
  if (!stateCode) return
  loadingMunicipalities.value = true
  try {
    municipalities.value = await fetchMunicipalities(stateCode)
  } finally {
    loadingMunicipalities.value = false
  }
}

// Carga al elegir estado y al montar con estado prellenado (reanudar onboarding).
watch(state, (s) => loadMunicipalities(s), { immediate: true })

// El catálogo no trae municipios para el estado (sin datos/offline) → habilita
// la captura manual como fallback, nunca como vía principal.
const municipalitiesEmpty = computed(
  () => !!state.value && !loadingMunicipalities.value && municipalities.value.length === 0,
)

function pickState(v: string) {
  state.value = v
  city.value = ''
  customCity.value = ''
  stateSheetOpen.value = false
}

function pickCity(c: string) {
  city.value = c
}

// En modo fallback (sin catálogo), el texto libre es el municipio comprometido.
watch(customCity, (v) => {
  if (municipalitiesEmpty.value) city.value = v
})

watch([state, city], () => {
  if (state.value && city.value) {
    emit('update:modelValue', { state: state.value, city: city.value })
  }
})
</script>

<template>
  <div class="step-state-city">
    <h2 v-if="step.label" class="step-section-title">{{ step.label }}</h2>

    <!-- Selector de estado tipo dropdown -->
    <button type="button" class="state-dropdown" @click="stateSheetOpen = true">
      <span class="state-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M12 22s7-7 7-13a7 7 0 10-14 0c0 6 7 13 7 13z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
          <circle cx="12" cy="9" r="2.5" stroke="currentColor" stroke-width="1.6" />
        </svg>
      </span>
      <span class="state-text">
        <span class="state-label">Estado</span>
        <span class="state-value" :class="{ 'state-value--placeholder': !state }">
          {{ stateLabel || 'Selecciona tu estado' }}
        </span>
      </span>
      <svg class="state-chevron" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <!-- Lista de municipios del estado (visible solo cuando hay estado) -->
    <div v-if="state" class="city-block">
      <span class="block-title">Municipio</span>

      <p v-if="loadingMunicipalities" class="city-hint">Cargando municipios…</p>

      <ul v-else-if="municipalities.length" class="city-list">
        <li v-for="c in municipalities" :key="c">
          <button
            type="button"
            class="city-row"
            :class="{ 'city-row--active': city === c }"
            @click="pickCity(c)"
          >
            <span class="city-label">{{ c }}</span>
            <span class="city-radio" :class="{ 'city-radio--active': city === c }">
              <svg v-if="city === c" viewBox="0 0 24 24" fill="none">
                <path d="M5 12l5 5L20 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </span>
          </button>
        </li>
      </ul>

      <!-- Fallback: sin municipios en el catálogo → captura manual (no es la vía principal) -->
      <template v-else>
        <p class="city-hint">No encontramos municipios de este estado en el catálogo. Escríbelo:</p>
        <div class="custom-row custom-row--active">
          <input
            v-model="customCity"
            type="text"
            placeholder="Municipio"
            class="custom-input"
          />
        </div>
      </template>
    </div>

    <!-- Bottom sheet de estados -->
    <Teleport to="body">
      <div v-if="stateSheetOpen" class="sheet-overlay" @click.self="stateSheetOpen = false">
        <div class="sheet" role="dialog">
          <div class="sheet-handle" />
          <header class="sheet-header">
            <h2>Estado</h2>
            <button type="button" class="sheet-close" @click="stateSheetOpen = false">
              <svg viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
            </button>
          </header>
          <ul class="sheet-list">
            <li v-for="opt in stateOptions" :key="opt.value">
              <button
                type="button"
                class="sheet-row"
                :class="{ 'sheet-row--active': state === opt.value }"
                @click="pickState(opt.value)"
              >
                <span>{{ opt.label }}</span>
                <span class="sheet-radio" :class="{ 'sheet-radio--active': state === opt.value }">
                  <svg v-if="state === opt.value" viewBox="0 0 24 24" fill="none">
                    <path d="M5 12l5 5L20 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </span>
              </button>
            </li>
          </ul>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.step-state-city {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.step-section-title {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}

/* Dropdown del estado */
.state-dropdown {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: #ffffff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  min-height: 60px;
  transition: border-color 140ms ease;
}
.state-dropdown:active {
  border-color: var(--tenant-primary, #5B21B6);
}
.state-icon {
  width: 28px;
  height: 28px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: var(--tenant-primary, #5B21B6);
  border-radius: 999px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.state-icon svg {
  width: 16px;
  height: 16px;
}
.state-text {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  text-align: left;
  min-width: 0;
}
.state-label {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}
.state-value {
  font-size: 14.5px;
  color: #0f172a;
  font-weight: 600;
}
.state-value--placeholder {
  color: #9ca3af;
  font-weight: 400;
}
.state-chevron {
  width: 18px;
  height: 18px;
  color: #94a3b8;
  flex-shrink: 0;
}

/* Lista de ciudades */
.city-block {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.block-title {
  font-size: 13px;
  font-weight: 600;
  color: #475569;
}
.city-hint {
  font-size: 13px;
  color: #64748b;
  margin: 0;
}
.city-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.city-row {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  background: #ffffff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  cursor: pointer;
  transition: border-color 140ms ease, background 140ms ease;
  -webkit-tap-highlight-color: transparent;
}
.city-row--active {
  border-color: var(--tenant-primary, #5B21B6);
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
}
.city-label {
  font-size: 14.5px;
  font-weight: 500;
  color: #0f172a;
}
.city-row--active .city-label {
  color: var(--tenant-primary, #5B21B6);
  font-weight: 600;
}
.city-radio {
  width: 22px;
  height: 22px;
  border-radius: 999px;
  border: 2px solid #cbd5e1;
  display: grid;
  place-items: center;
  transition: background 140ms ease, border-color 140ms ease;
  flex-shrink: 0;
}
.city-radio--active {
  background: var(--tenant-primary, #5B21B6);
  border-color: var(--tenant-primary, #5B21B6);
}
.city-radio svg {
  width: 12px;
  height: 12px;
}

.custom-row {
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  background: #ffffff;
  transition: border-color 140ms ease;
}
.custom-row--active {
  border-color: var(--tenant-primary, #5B21B6);
}
.custom-input {
  width: 100%;
  border: none;
  background: transparent;
  padding: 14px 16px;
  font-size: 14.5px;
  color: #0f172a;
  outline: none;
}
.custom-input::placeholder {
  color: #9ca3af;
}

/* Sheet de estados */
.sheet-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: grid;
  place-items: end center;
  z-index: 60;
  animation: fadeIn 160ms ease;
}
.sheet {
  background: #ffffff;
  width: 100%;
  max-width: 520px;
  max-height: 80vh;
  border-radius: 24px 24px 0 0;
  display: flex;
  flex-direction: column;
  animation: slideUp 220ms ease;
  padding-bottom: env(safe-area-inset-bottom);
}
.sheet-handle {
  width: 40px;
  height: 4px;
  background: #e2e8f0;
  border-radius: 999px;
  margin: 10px auto 0;
}
.sheet-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 20px;
}
.sheet-header h2 {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}
.sheet-close {
  background: transparent;
  border: none;
  cursor: pointer;
  color: #64748b;
  width: 28px;
  height: 28px;
  display: grid;
  place-items: center;
  border-radius: 999px;
}
.sheet-close svg {
  width: 18px;
  height: 18px;
}
.sheet-list {
  list-style: none;
  margin: 0;
  padding: 4px 16px 20px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.sheet-row {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 12px;
  background: #ffffff;
  border: 1.5px solid transparent;
  border-radius: 12px;
  cursor: pointer;
  font-size: 14.5px;
  color: #0f172a;
  transition: background 140ms ease, border-color 140ms ease;
}
.sheet-row--active {
  border-color: var(--tenant-primary, #5B21B6);
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: var(--tenant-primary, #5B21B6);
  font-weight: 600;
}
.sheet-radio {
  width: 22px;
  height: 22px;
  border-radius: 999px;
  border: 2px solid #cbd5e1;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.sheet-radio--active {
  background: var(--tenant-primary, #5B21B6);
  border-color: var(--tenant-primary, #5B21B6);
}
.sheet-radio svg {
  width: 12px;
  height: 12px;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
</style>
