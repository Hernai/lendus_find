<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { BankAccountStep } from '@/types/v2/onboardingStep'
import { MEXICAN_BANKS } from '@/utils/banks'

/**
 * Renderiza step `bank_account`: captura cuenta bancaria del aplicante.
 *
 * Layout:
 *  - Tipo de cuenta (CLABE / Tarjeta) — radio cards
 *  - Banco — selector tipo dropdown (sheet)
 *  - Número de cuenta — input con icono + validación
 *  - Aviso de confirmación lavanda
 *
 * CLABE: 18 dígitos. Tarjeta: 16. Detectamos por longitud + validación.
 * Tenant-agnóstico.
 */

interface BankAccount {
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
}>()

const type = ref<BankAccount['type']>(props.modelValue?.type ?? 'CLABE')
const bankCode = ref<string>(props.modelValue?.bank_code ?? '')
const accountNumber = ref<string>(props.modelValue?.account_number ?? '')
const bankSheetOpen = ref(false)

const BANKS = MEXICAN_BANKS

const bankLabel = computed(() => {
  if (!bankCode.value) return ''
  return BANKS.find((b) => b.code === bankCode.value)?.name ?? bankCode.value
})

const maxDigits = computed(() => (type.value === 'CLABE' ? 18 : 16))
const digitCount = computed(() => accountNumber.value.replace(/\D/g, '').length)
const isValidNumber = computed(() => digitCount.value === maxDigits.value)
const isComplete = computed(() => !!bankCode.value && isValidNumber.value)

function pickType(t: BankAccount['type']) {
  type.value = t
  accountNumber.value = ''
}

function pickBank(code: string) {
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
  const digits = raw.replace(/\D/g, '').slice(0, maxDigits.value)
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

watch([type, bankCode, accountNumber], () => {
  if (isComplete.value) {
    emit('update:modelValue', {
      type: type.value,
      bank_code: bankCode.value,
      account_number: accountNumber.value.replace(/\s/g, ''),
    })
  }
})
</script>

<template>
  <div class="step-bank">
    <p class="step-hint">
      Ingresa la cuenta bancaria donde quieres recibir tu préstamo. Debe estar a tu nombre.
    </p>

    <!-- Tipo de cuenta -->
    <div class="type-row">
      <button
        type="button"
        class="type-card"
        :class="{ 'type-card--active': type === 'CLABE' }"
        @click="pickType('CLABE')"
      >
        <span class="type-radio" :class="{ 'type-radio--active': type === 'CLABE' }">
          <span v-if="type === 'CLABE'" class="type-radio-inner" />
        </span>
        <span class="type-text">
          <span class="type-title">CLABE</span>
          <span class="type-sub">18 dígitos</span>
        </span>
      </button>
      <button
        type="button"
        class="type-card"
        :class="{ 'type-card--active': type === 'CARD' }"
        @click="pickType('CARD')"
      >
        <span class="type-radio" :class="{ 'type-radio--active': type === 'CARD' }">
          <span v-if="type === 'CARD'" class="type-radio-inner" />
        </span>
        <span class="type-text">
          <span class="type-title">Tarjeta</span>
          <span class="type-sub">16 dígitos</span>
        </span>
      </button>
    </div>

    <!-- Banco -->
    <label class="field-label">Banco</label>
    <button type="button" class="bank-dropdown" @click="bankSheetOpen = true">
      <span class="bank-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M4 10l8-5 8 5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
          <path d="M5 10h14v9H5z" stroke="currentColor" stroke-width="1.6" />
          <path d="M8 14v3M12 14v3M16 14v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
        </svg>
      </span>
      <span class="bank-value" :class="{ 'bank-value--placeholder': !bankCode }">
        {{ bankLabel || 'Selecciona tu banco' }}
      </span>
      <svg class="bank-chevron" viewBox="0 0 24 24" fill="none">
        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <!-- Número de cuenta -->
    <label class="field-label">{{ type === 'CLABE' ? 'CLABE interbancaria' : 'Número de tarjeta' }}</label>
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
        :placeholder="type === 'CLABE' ? '18 dígitos' : '16 dígitos'"
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
                :class="{ 'sheet-row--active': bankCode === b.code }"
                @click="pickBank(b.code)"
              >
                <span>{{ b.name }}</span>
                <span class="sheet-radio" :class="{ 'sheet-radio--active': bankCode === b.code }">
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

.type-row {
  display: flex;
  gap: 10px;
}
.type-card {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  background: #ffffff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  transition: border-color 140ms ease, background 140ms ease;
}
.type-card--active {
  border-color: var(--tenant-primary, #5B21B6);
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
}
.type-radio {
  width: 20px;
  height: 20px;
  border-radius: 999px;
  border: 2px solid #cbd5e1;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.type-radio--active {
  border-color: var(--tenant-primary, #5B21B6);
}
.type-radio-inner {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  background: var(--tenant-primary, #5B21B6);
}
.type-text {
  display: flex;
  flex-direction: column;
  text-align: left;
  gap: 2px;
}
.type-title {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
}
.type-card--active .type-title {
  color: var(--tenant-primary, #5B21B6);
}
.type-sub {
  font-size: 11.5px;
  color: #64748b;
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
