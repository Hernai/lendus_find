<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { BankAccountStep } from '@/types/v2/onboardingStep'
import { MEXICAN_BANKS } from '@/utils/banks'
import { useProfileStore } from '@/stores'

/**
 * Renderiza step `bank_account`: captura cuenta bancaria del aplicante.
 *
 * Layout:
 *  - Banco — inferido automáticamente desde la CLABE (readonly + check verde);
 *    si no se detecta, selector tipo dropdown (sheet) como fallback manual.
 *  - Número de cuenta (CLABE) — input con icono + validación
 *  - Aviso de confirmación lavanda
 *
 * La cuenta siempre es CLABE de 18 dígitos. Tenant-agnóstico.
 */

interface BankAccount {
  // Se mantiene la unión (en vez de un literal 'CLABE') por compatibilidad
  // estructural con la interfaz homónima de StepBankAccount.vue (usada en el
  // v-model). El valor emitido aquí siempre es 'CLABE'.
  type: 'CLABE' | 'CARD'
  bank_code: string
  account_number: string
}

const props = defineProps<{
  step: BankAccountStep
  modelValue: BankAccount | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: BankAccount]
  'update:valid': [valid: boolean]
}>()

const profileStore = useProfileStore()

const bankCode = ref<string>(props.modelValue?.bank_code ?? '')
const accountNumber = ref<string>(props.modelValue?.account_number ?? '')
const bankSheetOpen = ref(false)
const detectingBank = ref(false)
const bankAutoDetected = ref(false)
// Última CLABE (18 díg. sin espacios) ya consultada al backend. Evita
// re-validar la misma CLABE, pero SÍ re-valida ediciones in-situ (retipear un
// dígito) y paste-over de otra CLABE de 18 dígitos.
const lastValidatedClabe = ref('')

const BANKS = MEXICAN_BANKS
const CLABE_DIGITS = 18

const selectedBank = computed(() => BANKS.find((b) => b.code === bankCode.value) ?? null)

const bankLabel = computed(() => {
  if (!bankCode.value) return ''
  return selectedBank.value?.name ?? bankCode.value
})

// Acreditación inmediata: el banco elegido debe permitir transferencias con
// acreditación inmediata (SPEI/CEP). Default conservador — un banco desconocido
// o no confirmado (incluida la comodín "Otro") NO es apto.
const bankAllowsTransfer = computed(() => selectedBank.value?.validForTransfer === true)

// Aviso visible cuando el aplicante tiene seleccionado un banco no apto (p. ej.
// de un borrador previo o derivado de CLABE): la cuenta no recibiría el préstamo.
const showTransferWarning = computed(() => !!bankCode.value && !bankAllowsTransfer.value)

// Validez del paso (contrato): banco apto para acreditación inmediata + CLABE
// de 18 dígitos. Reproduce legacyCanContinue('bank_account') (congelado en
// stepValidation.spec.ts) y le suma el requisito de banco válido para
// transferencia.
const isValid = computed(() =>
  !!bankCode.value && bankAllowsTransfer.value && !!accountNumber.value &&
  accountNumber.value.replace(/\D/g, '').length === CLABE_DIGITS,
)
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

const maxDigits = CLABE_DIGITS
const digitCount = computed(() => accountNumber.value.replace(/\D/g, '').length)
const isValidNumber = computed(() => digitCount.value === maxDigits)
const isComplete = computed(() => !!bankCode.value && isValidNumber.value)

function pickBank(code: string) {
  // Los bancos no aptos para acreditación inmediata están deshabilitados en el
  // sheet; blindamos por si el evento llega igual.
  const bank = BANKS.find((b) => b.code === code)
  if (bank && !bank.validForTransfer) return
  bankCode.value = code
  bankSheetOpen.value = false
}

function handleAccountInput(ev: Event) {
  const input = ev.target as HTMLInputElement
  const raw = input.value
  const caretBefore = input.selectionStart ?? raw.length
  // Cuántos dígitos hay antes del caret (los espacios no cuentan).
  const digitsBeforeCaret = raw.slice(0, caretBefore).replace(/\D/g, '').length
  // Solo dígitos, agrupar de 4 en 4 con espacio.
  const digits = raw.replace(/\D/g, '').slice(0, maxDigits)
  const grouped = digits.match(/.{1,4}/g)?.join(' ') ?? digits
  accountNumber.value = grouped
  if (input.value !== grouped) {
    input.value = grouped
  }
  // Reposiciona el caret después del Nth dígito (no al final).
  let pos = 0
  let counted = 0
  while (pos < grouped.length && counted < digitsBeforeCaret) {
    if (/\d/.test(grouped.charAt(pos))) counted++
    pos++
  }
  try { input.setSelectionRange(pos, pos) } catch { /* noop */ }
}

watch([bankCode, accountNumber], () => {
  if (isComplete.value) {
    emit('update:modelValue', {
      type: 'CLABE',
      bank_code: bankCode.value,
      account_number: accountNumber.value.replace(/\s/g, ''),
    })
  }
})

// Inferencia de banco: al completar los 18 dígitos de la CLABE, se consulta al
// backend (misma fuente que el perfil) y se autocompleta `bankCode`. Se
// re-valida siempre que la CLABE cambie respecto a la última consultada, lo que
// cubre ediciones in-situ (retipear un dígito) y paste-over de otra CLABE de 18
// dígitos — así el bank_code emitido nunca queda desfasado del account_number.
watch(accountNumber, async (newVal) => {
  const digits = newVal.replace(/\D/g, '')

  if (digits.length < CLABE_DIGITS) {
    bankCode.value = ''
    bankAutoDetected.value = false
    lastValidatedClabe.value = ''
    return
  }
  if (digits === lastValidatedClabe.value) return // misma CLABE ya consultada

  lastValidatedClabe.value = digits
  detectingBank.value = true
  try {
    const result = await profileStore.validateClabe(digits)
    // Guard de carrera: si la CLABE cambió mientras esperábamos la respuesta,
    // descartamos este resultado (una respuesta tardía no debe pisar el estado).
    if (accountNumber.value.replace(/\D/g, '') !== digits) return
    if (result?.is_valid && result.bank_code && BANKS.some((b) => b.code === result.bank_code)) {
      bankCode.value = result.bank_code
      bankAutoDetected.value = true
    } else {
      // Sin detección: limpiamos para exponer el selector manual (fallback).
      bankCode.value = ''
      bankAutoDetected.value = false
    }
  } finally {
    if (accountNumber.value.replace(/\D/g, '') === digits) {
      detectingBank.value = false
    }
  }
})
</script>

<template>
  <div class="step-bank">
    <p class="step-hint">
      Ingresa la cuenta bancaria donde quieres recibir tu préstamo. Debe estar a tu nombre.
    </p>

    <!-- Banco -->
    <label class="field-label">Banco</label>

    <!-- Detectado automáticamente desde la CLABE: solo lectura. Check verde solo
         si el banco es apto para acreditación inmediata; si no, estilo de
         advertencia sin check (el .bank-warning explica y la CLABE sigue
         editable para reingresar otra cuenta). -->
    <div
      v-if="bankAutoDetected && selectedBank"
      class="bank-detected"
      :class="{ 'bank-detected--warn': !bankAllowsTransfer }"
    >
      <span class="bank-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M4 10l8-5 8 5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
          <path d="M5 10h14v9H5z" stroke="currentColor" stroke-width="1.6" />
          <path d="M8 14v3M12 14v3M16 14v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
        </svg>
      </span>
      <span class="bank-value">{{ selectedBank.name }}</span>
      <svg v-if="bankAllowsTransfer" class="bank-check" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="10" fill="#10b981" />
        <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </div>

    <!-- Fallback: no se detectó banco automáticamente, selector manual -->
    <button v-else type="button" class="bank-dropdown" :disabled="detectingBank" @click="bankSheetOpen = true">
      <span class="bank-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M4 10l8-5 8 5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
          <path d="M5 10h14v9H5z" stroke="currentColor" stroke-width="1.6" />
          <path d="M8 14v3M12 14v3M16 14v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
        </svg>
      </span>
      <span class="bank-value" :class="{ 'bank-value--placeholder': !bankCode }">
        {{ detectingBank ? 'Detectando banco…' : (bankLabel || 'Selecciona tu banco') }}
      </span>
      <div v-if="detectingBank" class="bank-spinner" aria-hidden="true" />
      <svg v-else class="bank-chevron" viewBox="0 0 24 24" fill="none">
        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <!-- Aviso banco no apto para acreditación inmediata -->
    <p v-if="showTransferWarning" class="bank-warning" role="alert">
      <span class="bank-warning-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M12 3l9 16H3L12 3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
          <path d="M12 10v4M12 17h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
      </span>
      <span>
        Esta cuenta no puede recibir tu préstamo. Ingresa la CLABE de otra cuenta apta.
      </span>
    </p>

    <!-- Número de cuenta -->
    <label class="field-label">CLABE interbancaria</label>
    <div class="field" :class="{ 'field--valid': isValidNumber }">
      <span class="field-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <rect x="3" y="6" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.6" />
          <path d="M3 10h18" stroke="currentColor" stroke-width="1.6" />
        </svg>
      </span>
      <input
        :value="accountNumber"
        type="tel"
        inputmode="numeric"
        placeholder="18 dígitos"
        class="field-input"
        @input="handleAccountInput"
      />
      <svg v-if="isValidNumber" class="field-check" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="10" fill="#10b981" />
        <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </div>
    <p v-if="digitCount > 0 && !isValidNumber" class="field-hint">
      {{ digitCount }} / {{ maxDigits }} dígitos
    </p>

    <!-- Pill confirmación -->
    <div class="confirm-pill">
      <span class="confirm-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z" fill="white" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
          <path d="M8 12.2l2.6 2.6 5.2-5.2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </span>
      <span>
        Verifica cuidadosamente la información de tu cuenta de recepción antes de guardar.
      </span>
    </div>

    <!-- Sheet de bancos -->
    <Teleport to="body">
      <div v-if="bankSheetOpen" class="sheet-overlay" @click.self="bankSheetOpen = false">
        <div class="sheet">
          <div class="sheet-handle" />
          <header class="sheet-header">
            <h2>Selecciona tu banco</h2>
            <button type="button" class="sheet-close" @click="bankSheetOpen = false">
              <svg viewBox="0 0 24 24" fill="none"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
            </button>
          </header>
          <ul class="sheet-list">
            <li v-for="b in BANKS" :key="b.code">
              <button
                type="button"
                class="sheet-row"
                :class="{
                  'sheet-row--active': bankCode === b.code,
                  'sheet-row--disabled': !b.validForTransfer,
                }"
                :disabled="!b.validForTransfer"
                :aria-disabled="!b.validForTransfer"
                @click="pickBank(b.code)"
              >
                <span class="sheet-row-main">
                  <span>{{ b.name }}</span>
                  <span v-if="!b.validForTransfer" class="sheet-row-note">
                    No disponible para recibir tu préstamo
                  </span>
                </span>
                <span
                  v-if="b.validForTransfer"
                  class="sheet-radio"
                  :class="{ 'sheet-radio--active': bankCode === b.code }"
                >
                  <svg v-if="bankCode === b.code" viewBox="0 0 24 24" fill="none">
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
.step-bank {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.step-hint {
  font-size: 13px;
  color: #64748b;
  margin: 0 0 4px;
  line-height: 1.5;
}

.field-label {
  font-size: 12.5px;
  color: #64748b;
  font-weight: 500;
  margin-top: 8px;
}

.bank-dropdown {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 14px;
  background: #ffffff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  cursor: pointer;
  min-height: 56px;
  -webkit-tap-highlight-color: transparent;
  transition: border-color 140ms ease;
}
.bank-dropdown:active {
  border-color: var(--tenant-primary, #5B21B6);
}
.bank-icon {
  width: 22px;
  height: 22px;
  color: var(--tenant-primary, #5B21B6);
  flex-shrink: 0;
}
.bank-icon svg {
  width: 100%;
  height: 100%;
}
.bank-value {
  flex: 1;
  text-align: left;
  font-size: 14.5px;
  color: #0f172a;
  font-weight: 600;
}
.bank-value--placeholder {
  color: #9ca3af;
  font-weight: 400;
}
.bank-chevron {
  width: 18px;
  height: 18px;
  color: #94a3b8;
  flex-shrink: 0;
}
.bank-spinner {
  width: 18px;
  height: 18px;
  border: 2px solid #cbd5e1;
  border-top-color: var(--tenant-primary, #5B21B6);
  border-radius: 999px;
  flex-shrink: 0;
  animation: spin 700ms linear infinite;
}

.bank-detected {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 14px;
  background: #f0fdf4;
  border: 1.5px solid #86efac;
  border-radius: 14px;
  min-height: 56px;
}
/* Banco detectado pero NO apto para acreditación inmediata: estilo de
   advertencia, sin check verde. */
.bank-detected--warn {
  background: #fffbeb;
  border-color: #fcd34d;
}
.bank-check {
  width: 22px;
  height: 22px;
  flex-shrink: 0;
}

.field {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 12px 0 14px;
  background: #ffffff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  min-height: 56px;
  transition: border-color 140ms ease;
}
.field:focus-within {
  border-color: var(--tenant-primary, #5B21B6);
}
.field-icon {
  width: 22px;
  height: 22px;
  color: var(--tenant-primary, #5B21B6);
  flex-shrink: 0;
}
.field-icon svg {
  width: 100%;
  height: 100%;
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
  letter-spacing: 0.5px;
}
.field-input::placeholder {
  color: #9ca3af;
}
.field-check {
  width: 22px;
  height: 22px;
  flex-shrink: 0;
}
.field-hint {
  font-size: 12px;
  color: #64748b;
  margin: -4px 0 0;
}

.bank-warning {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 12px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
  font-size: 12.5px;
  line-height: 1.45;
  margin: -2px 0 0;
}
.bank-warning-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  color: #dc2626;
  margin-top: 1px;
}
.bank-warning-icon svg {
  width: 100%;
  height: 100%;
}

.confirm-pill {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 14px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: #475569;
  font-size: 12.5px;
  line-height: 1.45;
  margin-top: 6px;
}
.confirm-icon {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  color: var(--tenant-primary, #5B21B6);
  display: grid;
  place-items: center;
}
.confirm-icon svg {
  width: 22px;
  height: 22px;
}

/* Sheet de bancos (mismo estilo que StateCity) */
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
.sheet-row--disabled {
  cursor: not-allowed;
  color: #94a3b8;
  background: #f8fafc;
}
.sheet-row-main {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  text-align: left;
}
.sheet-row-note {
  font-size: 11.5px;
  color: #dc2626;
  font-weight: 500;
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
@keyframes spin { to { transform: rotate(360deg); } }
</style>
