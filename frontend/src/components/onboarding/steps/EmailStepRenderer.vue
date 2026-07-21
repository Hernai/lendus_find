<script setup lang="ts">
import { ref, computed, watch } from 'vue'

/**
 * Step `email`: captura el correo electrónico del solicitante (dato de
 * contacto temprano). Se guarda como `ApplicantIdentity` type=EMAIL SIN
 * verificar (`verified_at = null`) vía `POST /v2/applicant/profile/email`
 * — no hay OTP en este paso, solo se registra el dato para notificaciones.
 */

const props = defineProps<{
  step: { id: string; type: string; label: string }
  modelValue: string | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'update:valid': [valid: boolean]
}>()

// Regex simple de formato de correo (no exhaustiva RFC 5322 — suficiente para
// validación de UI; el backend valida de nuevo con la regla `email` de Laravel).
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const email = ref(props.modelValue ?? '')

const isValid = computed(() => EMAIL_REGEX.test(email.value.trim()))
watch(isValid, (v) => emit('update:valid', v), { immediate: true })

watch(email, (v) => {
  if (isValid.value) {
    emit('update:modelValue', v.trim())
  }
})
</script>

<template>
  <div class="step-email">
    <p class="step-hint">
      Ingresa tu correo electrónico. Lo usaremos para enviarte notificaciones
      sobre el estado de tu solicitud.
    </p>

    <div
      class="field"
      :class="{ 'field--ok': isValid, 'field--err': email.length > 0 && !isValid }"
    >
      <label>Correo electrónico</label>
      <input
        v-model="email"
        type="email"
        inputmode="email"
        autocomplete="email"
        placeholder="correo@ejemplo.com"
      />
      <span v-if="isValid" class="field-hint field-hint--ok">Correo válido ✓</span>
      <span v-else-if="email.length > 0" class="field-hint field-hint--warn">
        Ingresa un correo con formato válido.
      </span>
    </div>
  </div>
</template>

<style scoped>
.step-email { display: flex; flex-direction: column; gap: 14px; box-sizing: border-box; }
.step-email * { box-sizing: border-box; }
.step-hint { font-size: 13.5px; color: #475569; margin: 0; line-height: 1.5; }
.field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.field label { font-size: 12.5px; color: #475569; font-weight: 600; }
.field input {
  width: 100%; min-width: 0;
  padding: 12px 14px;
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  font-size: 14px; color: #0f172a;
  background: #ffffff;
  font-family: inherit;
  outline: none;
}
.field input:focus { border-color: var(--tenant-primary, #5B21B6); }
.field--ok input { border-color: #16a34a; }
.field--err input { border-color: #ef4444; }
.field-hint { font-size: 12px; color: #64748b; }
.field-hint--ok { color: #15803d; }
.field-hint--warn { color: #b45309; }
</style>
