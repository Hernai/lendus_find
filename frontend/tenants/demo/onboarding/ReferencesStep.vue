<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { formatPhoneInput } from '@/utils/formatters'
import { useAuthStore } from '@/stores/auth'
import { useTenantStore } from '@/stores/tenant'

/**
 * Referencias — variante del tenant DEMO. Captura el nombre SEPARADO (nombre /
 * primer apellido / segundo apellido, con los apellidos EN LÍNEA) + el TIPO DE
 * RELACIÓN en combo. El backend guarda first_name/last_name_1/last_name_2 +
 * relationship. Registrado vía registerTenantSteps('demo', { references }) —
 * MoneyCapital sigue con el ReferencesStepRenderer base.
 */

interface Reference {
  type: 'FAMILY' | 'PERSONAL'
  first_name: string
  last_name_1: string
  last_name_2: string
  relationship: string
  phone: string
}

const props = defineProps<{
  step: { id: string }
  modelValue: Reference[] | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Reference[]]
  'update:valid': [valid: boolean]
}>()

const tenantStore = useTenantStore()
// Parentescos: familiar (madre/padre/hermano…) vs no-familiar (amigo/vecino…).
const familyRels = computed(() => tenantStore.options.relationshipFamily ?? [])
const personalRels = computed(() => tenantStore.options.relationshipNonFamily ?? [])

const emptyRef = (type: 'FAMILY' | 'PERSONAL'): Reference => ({
  type, first_name: '', last_name_1: '', last_name_2: '', relationship: '', phone: '',
})
const family = ref<Reference>(props.modelValue?.find((r) => r.type === 'FAMILY') ?? emptyRef('FAMILY'))
const personal = ref<Reference>(props.modelValue?.find((r) => r.type === 'PERSONAL') ?? emptyRef('PERSONAL'))

// Requiere nombre + primer apellido + relación (segundo apellido opcional).
const validRef = (r: Reference) =>
  r.first_name.trim().length >= 2 && r.last_name_1.trim().length >= 2 && !!r.relationship
const validPhone = (p: string) => p.replace(/\D/g, '').length === 10

const authStore = useAuthStore()
const digits = (p: string) => p.replace(/\D/g, '')
const normName = (r: Reference) =>
  [r.first_name, r.last_name_1, r.last_name_2].join(' ').trim().toLowerCase().replace(/\s+/g, ' ')
const ownPhone = computed(() => digits(authStore.user?.phone ?? ''))

const samePhone = computed(() => {
  const a = digits(family.value.phone)
  return a.length === 10 && a === digits(personal.value.phone)
})
const sameName = computed(() => {
  const a = normName(family.value)
  return a !== '' && a === normName(personal.value)
})
const phoneIsOwn = computed(() =>
  ownPhone.value !== '' &&
  (digits(family.value.phone) === ownPhone.value || digits(personal.value.phone) === ownPhone.value),
)

const dupError = computed(() => {
  if (samePhone.value) return 'Las dos referencias tienen el mismo teléfono. Deben ser personas distintas.'
  if (sameName.value) return 'Las dos referencias tienen el mismo nombre. Deben ser personas distintas.'
  if (phoneIsOwn.value) return 'El teléfono de una referencia es el tuyo. Usa el de otra persona.'
  return ''
})

const isValid = computed(() =>
  validRef(family.value) && validPhone(family.value.phone) &&
  validRef(personal.value) && validPhone(personal.value.phone) &&
  !dupError.value,
)
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

function handlePhoneInput(r: Reference, ev: Event) {
  const input = ev.target as HTMLInputElement
  r.phone = formatPhoneInput(input.value)
  if (input.value !== r.phone) input.value = r.phone
}

watch([family, personal], () => emit('update:modelValue', [family.value, personal.value]), { deep: true })
</script>

<template>
  <div class="step-references">
    <p class="step-hint">
      Agrega 2 referencias para completar tu solicitud. Solo se usarán para contactarte si es necesario.
    </p>

    <!-- Referencia familiar -->
    <section class="ref-section">
      <header class="ref-header"><h3>Referencia familiar</h3></header>

      <label class="field-label">Nombre(s)</label>
      <div class="field" :class="{ 'field--valid': family.first_name.trim().length >= 2 }">
        <input v-model="family.first_name" type="text" placeholder="Ej. María" class="field-input" />
      </div>
      <div class="name-row">
        <div>
          <label class="field-label">Primer apellido</label>
          <div class="field" :class="{ 'field--valid': family.last_name_1.trim().length >= 2 }">
            <input v-model="family.last_name_1" type="text" placeholder="Ej. López" class="field-input" />
          </div>
        </div>
        <div>
          <label class="field-label">Segundo apellido (opcional)</label>
          <div class="field">
            <input v-model="family.last_name_2" type="text" placeholder="Ej. García" class="field-input" />
          </div>
        </div>
      </div>
      <label class="field-label">Parentesco</label>
      <div class="field" :class="{ 'field--valid': !!family.relationship }">
        <select v-model="family.relationship" class="field-input field-select">
          <option value="" disabled>Selecciona…</option>
          <option v-for="r in familyRels" :key="r.value" :value="r.value">{{ r.label }}</option>
        </select>
      </div>
      <label class="field-label">Teléfono</label>
      <div class="field" :class="{ 'field--valid': validPhone(family.phone) }">
        <span class="field-prefix">+52</span>
        <input :value="family.phone" type="tel" inputmode="numeric" placeholder="10 dígitos" maxlength="12" class="field-input" @input="handlePhoneInput(family, $event)" />
      </div>
    </section>

    <!-- Referencia personal -->
    <section class="ref-section">
      <header class="ref-header"><h3>Referencia personal</h3></header>

      <label class="field-label">Nombre(s)</label>
      <div class="field" :class="{ 'field--valid': personal.first_name.trim().length >= 2 }">
        <input v-model="personal.first_name" type="text" placeholder="Ej. Carlos" class="field-input" />
      </div>
      <div class="name-row">
        <div>
          <label class="field-label">Primer apellido</label>
          <div class="field" :class="{ 'field--valid': personal.last_name_1.trim().length >= 2 }">
            <input v-model="personal.last_name_1" type="text" placeholder="Ej. Ramírez" class="field-input" />
          </div>
        </div>
        <div>
          <label class="field-label">Segundo apellido (opcional)</label>
          <div class="field">
            <input v-model="personal.last_name_2" type="text" placeholder="Ej. Soto" class="field-input" />
          </div>
        </div>
      </div>
      <label class="field-label">Relación</label>
      <div class="field" :class="{ 'field--valid': !!personal.relationship }">
        <select v-model="personal.relationship" class="field-input field-select">
          <option value="" disabled>Selecciona…</option>
          <option v-for="r in personalRels" :key="r.value" :value="r.value">{{ r.label }}</option>
        </select>
      </div>
      <label class="field-label">Teléfono</label>
      <div class="field" :class="{ 'field--valid': validPhone(personal.phone) }">
        <span class="field-prefix">+52</span>
        <input :value="personal.phone" type="tel" inputmode="numeric" placeholder="10 dígitos" maxlength="12" class="field-input" @input="handlePhoneInput(personal, $event)" />
      </div>
    </section>

    <p v-if="dupError" class="ref-error" role="alert">{{ dupError }}</p>
  </div>
</template>

<style scoped>
.step-references { display: flex; flex-direction: column; gap: 16px; }
.step-hint { font-size: 13px; color: #64748b; margin: 0; line-height: 1.5; }
.ref-error { font-size: 13px; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 10px 12px; margin: 0; line-height: 1.4; }
.ref-section { display: flex; flex-direction: column; gap: 6px; }
.ref-header { margin-bottom: 2px; }
.ref-header h3 { font-size: 14px; font-weight: 700; color: var(--tenant-primary, #5B21B6); margin: 0; }
.name-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.name-row > div { display: flex; flex-direction: column; gap: 6px; }
.field-label { font-size: 12.5px; color: #64748b; font-weight: 500; margin-top: 6px; }
.field {
  display: flex; align-items: center; gap: 10px; padding: 0 14px;
  background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; min-height: 52px;
  transition: border-color 140ms ease;
}
.field:focus-within { border-color: var(--tenant-primary, #5B21B6); }
.field--valid { border-color: var(--tenant-primary, #5B21B6); }
.field-prefix {
  font-size: 14.5px; color: var(--tenant-primary, #5B21B6); font-weight: 600;
  border-right: 1px solid #e5e7eb; padding-right: 8px;
}
.field-input {
  flex: 1; border: none; background: transparent; font-size: 14.5px; color: #0f172a;
  outline: none; padding: 14px 0; min-width: 0;
}
.field-select { cursor: pointer; }
.field-input::placeholder { color: #9ca3af; }
</style>
