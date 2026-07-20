<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { ReferencesStep } from '@/types/v2/onboardingStep'
import { formatPhoneInput } from '@/utils/formatters'
import { useAuthStore } from '@/stores/auth'
import { platform } from '@/platform'

/**
 * Renderiza step `references`: usuario agrega referencias familiar y personal.
 *
 * Layout: dos secciones (familiar + personal), cada una con badge contador
 * (ej. "1 de 2"), inputs de nombre y teléfono con icono morado a la izquierda
 * y check verde a la derecha cuando son válidos.
 *
 * Tenant-agnóstico. Valida que cada referencia tenga nombre (≥3) y teléfono
 * de 10 dígitos.
 */

interface Reference {
  type: 'FAMILY' | 'PERSONAL'
  name: string
  phone: string
}

const props = defineProps<{
  step: ReferencesStep
  modelValue: Reference[] | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Reference[]]
  'update:valid': [valid: boolean]
}>()

// Estructura inicial: 1 familiar + 1 personal
const family = ref<Reference>(
  props.modelValue?.find((r) => r.type === 'FAMILY') ?? { type: 'FAMILY', name: '', phone: '' },
)
const personal = ref<Reference>(
  props.modelValue?.find((r) => r.type === 'PERSONAL') ?? { type: 'PERSONAL', name: '', phone: '' },
)

// Requiere nombre Y apellido (≥2 palabras), porque el backend exige
// first_name y last_name_1 por separado.
const validName = (n: string) => {
  const parts = n.trim().split(/\s+/).filter(Boolean)
  return parts.length >= 2 && parts.every((p) => p.length >= 2)
}
const validPhone = (p: string) => p.replace(/\D/g, '').length === 10

// Las referencias deben ser personas DISTINTAS entre sí y distintas del propio
// cliente. Validamos teléfono y nombre repetidos (y contra el del cliente).
const authStore = useAuthStore()
const digits = (p: string) => p.replace(/\D/g, '')
const normName = (n: string) => n.trim().toLowerCase().replace(/\s+/g, ' ')
const ownPhone = computed(() => digits(authStore.user?.phone ?? ''))

const samePhone = computed(() => {
  const a = digits(family.value.phone)
  return a.length === 10 && a === digits(personal.value.phone)
})
const sameName = computed(() => {
  const a = normName(family.value.name)
  return a !== '' && a === normName(personal.value.name)
})
const phoneIsOwn = computed(() =>
  ownPhone.value !== '' &&
  (digits(family.value.phone) === ownPhone.value || digits(personal.value.phone) === ownPhone.value),
)

// Mensaje de error visible para el cliente (y por qué no puede continuar).
const dupError = computed(() => {
  if (samePhone.value) return 'Las dos referencias tienen el mismo teléfono. Deben ser personas distintas.'
  if (sameName.value) return 'Las dos referencias tienen el mismo nombre. Deben ser personas distintas.'
  if (phoneIsOwn.value) return 'El teléfono de una referencia es el tuyo. Usa el de otra persona.'
  return ''
})

// Validez del paso (contrato): ambas referencias con nombre+teléfono válidos y
// distintas entre sí / del propio cliente (dupError). Reproduce legacyCanContinue
// ('references'), congelado en stepValidation.spec.ts.
const isValid = computed(() =>
  validName(family.value.name) && validPhone(family.value.phone) &&
  validName(personal.value.name) && validPhone(personal.value.phone) &&
  !dupError.value,
)
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

// Solo en la app nativa ofrecemos el picker de la agenda; en web (PWA/navegador)
// se conserva únicamente la captura manual (fallback).
const isNative = platform.device.isNative()

// Prellena una referencia desde la agenda del dispositivo. Si el usuario cancela
// o niega el permiso, `pickContact()` devuelve null y no se modifica nada.
async function pickFromContacts(target: Reference) {
  const picked = await platform.contacts.pickContact()
  if (!picked) return

  if (picked.name) {
    target.name = picked.name.trim()
  }
  if (picked.phone) {
    // Normaliza a 10 dígitos: quita separadores y el prefijo +52 tomando los
    // últimos 10 dígitos. El campo sigue editable y sujeto a validación.
    const local = picked.phone.replace(/\D/g, '').slice(-10)
    target.phone = formatPhoneInput(local)
  }
}

function handlePhoneInput(ref: Reference, ev: Event) {
  const input = ev.target as HTMLInputElement
  const raw = input.value
  const caretBefore = input.selectionStart ?? raw.length
  const digitsBeforeCaret = raw.slice(0, caretBefore).replace(/\D/g, '').length
  const formatted = formatPhoneInput(raw)
  ref.phone = formatted
  if (input.value !== formatted) {
    input.value = formatted
  }
  let pos = 0
  let counted = 0
  while (pos < formatted.length && counted < digitsBeforeCaret) {
    if (/\d/.test(formatted.charAt(pos))) counted++
    pos++
  }
  try { input.setSelectionRange(pos, pos) } catch { /* noop */ }
}

watch(
  [family, personal],
  () => {
    emit('update:modelValue', [family.value, personal.value])
  },
  { deep: true },
)
</script>

<template>
  <div class="step-references">
    <p class="step-hint">
      Agrega 2 referencias para completar tu solicitud. Solo se usarán para contactarte si es necesario.
    </p>

    <!-- Referencia familiar -->
    <section class="ref-section">
      <header class="ref-header">
        <span class="ref-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="9" cy="9" r="3.5" stroke="currentColor" stroke-width="1.6" />
            <circle cx="17" cy="11" r="2.5" stroke="currentColor" stroke-width="1.6" />
            <path d="M2 20c0-3 3-5 7-5s7 2 7 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
            <path d="M16 16c2 0 6 1 6 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </span>
        <h3>Referencia familiar</h3>
        <span class="ref-count">1 de 1</span>
      </header>

      <button
        v-if="isNative"
        type="button"
        class="contacts-btn"
        @click="pickFromContacts(family)"
      >
        <svg class="contacts-btn-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <rect x="4" y="3" width="14" height="18" rx="2.5" stroke="currentColor" stroke-width="1.6" />
          <path d="M20 7v10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          <circle cx="11" cy="10" r="2.4" stroke="currentColor" stroke-width="1.6" />
          <path d="M7.5 16c.5-1.7 2-2.6 3.5-2.6s3 .9 3.5 2.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
        </svg>
        Elegir de mis contactos
      </button>

      <label class="field-label">Nombre y apellido</label>
      <div class="field" :class="{ 'field--valid': validName(family.name) }">
        <span class="field-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.6" />
            <path d="M4 21c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </span>
        <input v-model="family.name" type="text" placeholder="Ej. María López" class="field-input" />
        <svg v-if="validName(family.name)" class="field-check" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" fill="#10b981" />
          <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </div>

      <label class="field-label">Teléfono</label>
      <div class="field" :class="{ 'field--valid': validPhone(family.phone) }">
        <span class="field-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <rect x="6" y="3" width="12" height="18" rx="3" stroke="currentColor" stroke-width="1.6" />
            <circle cx="12" cy="17.5" r="1" fill="currentColor" />
          </svg>
        </span>
        <span class="field-prefix">+52</span>
        <input
          :value="family.phone"
          type="tel"
          inputmode="numeric"
          placeholder="10 dígitos"
          maxlength="12"
          class="field-input"
          @input="handlePhoneInput(family, $event)"
        />
        <svg v-if="validPhone(family.phone)" class="field-check" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" fill="#10b981" />
          <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </div>
    </section>

    <!-- Referencia personal -->
    <section class="ref-section">
      <header class="ref-header">
        <span class="ref-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.6" />
            <path d="M4 21c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </span>
        <h3>Referencia personal</h3>
        <span class="ref-count">1 de 1</span>
      </header>

      <button
        v-if="isNative"
        type="button"
        class="contacts-btn"
        @click="pickFromContacts(personal)"
      >
        <svg class="contacts-btn-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <rect x="4" y="3" width="14" height="18" rx="2.5" stroke="currentColor" stroke-width="1.6" />
          <path d="M20 7v10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          <circle cx="11" cy="10" r="2.4" stroke="currentColor" stroke-width="1.6" />
          <path d="M7.5 16c.5-1.7 2-2.6 3.5-2.6s3 .9 3.5 2.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
        </svg>
        Elegir de mis contactos
      </button>

      <label class="field-label">Nombre y apellido</label>
      <div class="field" :class="{ 'field--valid': validName(personal.name) }">
        <span class="field-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.6" />
            <path d="M4 21c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
        </span>
        <input v-model="personal.name" type="text" placeholder="Ej. Carlos Ramírez" class="field-input" />
        <svg v-if="validName(personal.name)" class="field-check" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" fill="#10b981" />
          <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </div>

      <label class="field-label">Teléfono</label>
      <div class="field" :class="{ 'field--valid': validPhone(personal.phone) }">
        <span class="field-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <rect x="6" y="3" width="12" height="18" rx="3" stroke="currentColor" stroke-width="1.6" />
            <circle cx="12" cy="17.5" r="1" fill="currentColor" />
          </svg>
        </span>
        <span class="field-prefix">+52</span>
        <input
          :value="personal.phone"
          type="tel"
          inputmode="numeric"
          placeholder="10 dígitos"
          maxlength="12"
          class="field-input"
          @input="handlePhoneInput(personal, $event)"
        />
        <svg v-if="validPhone(personal.phone)" class="field-check" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" fill="#10b981" />
          <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </div>
    </section>

    <!-- Aviso: referencias repetidas o iguales al cliente -->
    <p v-if="dupError" class="ref-error" role="alert">{{ dupError }}</p>
  </div>
</template>

<style scoped>
.step-references {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.step-hint {
  font-size: 13px;
  color: #64748b;
  margin: 0;
  line-height: 1.5;
}
.ref-error {
  font-size: 13px;
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  padding: 10px 12px;
  margin: 0;
  line-height: 1.4;
}
.ref-section {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ref-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 4px;
}
.ref-icon {
  width: 32px;
  height: 32px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: var(--tenant-primary, #5B21B6);
  border-radius: 999px;
  display: grid;
  place-items: center;
}
.ref-icon svg {
  width: 18px;
  height: 18px;
}
.ref-header h3 {
  flex: 1;
  font-size: 14px;
  font-weight: 700;
  color: var(--tenant-primary, #5B21B6);
  margin: 0;
}
.ref-count {
  font-size: 12px;
  color: #64748b;
  background: #f3f4f6;
  padding: 2px 8px;
  border-radius: 999px;
}

/* Botón "Elegir de mis contactos" — solo visible en la app nativa. */
.contacts-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 11px 14px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: var(--tenant-primary, #5b21b6);
  border: 1.5px solid var(--tenant-primary, #5b21b6);
  border-radius: 14px;
  font-size: 13.5px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 140ms ease;
}
.contacts-btn:active {
  opacity: 0.7;
}
.contacts-btn-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.field-label {
  font-size: 12.5px;
  color: #64748b;
  font-weight: 500;
  margin-top: 6px;
}

.field {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 12px 0 14px;
  background: #ffffff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  min-height: 52px;
  transition: border-color 140ms ease;
}
.field:focus-within {
  border-color: var(--tenant-primary, #5B21B6);
}
.field--valid .field-icon {
  color: var(--tenant-primary, #5B21B6);
}
.field-icon {
  width: 20px;
  height: 20px;
  color: var(--tenant-primary, #5B21B6);
  flex-shrink: 0;
}
.field-icon svg {
  width: 100%;
  height: 100%;
}
.field-prefix {
  font-size: 14.5px;
  color: var(--tenant-primary, #5B21B6);
  font-weight: 600;
  border-right: 1px solid #e5e7eb;
  padding-right: 8px;
}
.field-input {
  flex: 1;
  border: none;
  background: transparent;
  font-size: 14.5px;
  color: #0f172a;
  outline: none;
  padding: 14px 0;
  min-width: 0;
}
.field-input::placeholder {
  color: #9ca3af;
}
.field-check {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}
</style>
