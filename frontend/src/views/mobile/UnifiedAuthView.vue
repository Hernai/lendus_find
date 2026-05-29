<script setup lang="ts">
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore, useTenantStore, useApplicationStore } from '@/stores'
import { logger } from '@/utils/logger'
import { formatPhoneInput } from '@/utils/formatters'
import type { OtpMethod } from '@/types'

/**
 * Auth unificada (white-label).
 *
 * Combina los pasos número de celular + selección de método (SMS/WhatsApp)
 * + ingreso de código OTP en una sola pantalla. Sustituye al trío
 * AuthMethodView → AuthPhoneView → AuthOtpView cuando el tenant tiene
 * `features.unified_auth_screen = true`.
 *
 * Visual alineado al PDF de mocks white-label. Tenant-agnóstica: el color,
 * logo y nombre vienen del store + CSS vars (--tenant-primary, --primary-N-rgb).
 *
 * Flow:
 * 1. Usuario tipea celular → elige método → tap "Enviar código"
 * 2. Backend manda OTP, se habilita el input de código
 * 3. Tap "Continuar" → verifica OTP y navega (PIN setup / onboarding / dashboard)
 *
 * Si el celular ya tiene PIN, después del checkUser se redirige a PIN login.
 */

const log = logger.child('UnifiedAuth')
const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const tenantStore = useTenantStore()
const applicationStore = useApplicationStore()

const tenantName = computed(() => tenantStore.name || 'LendusFind')
const brandLogoUrl = computed(() => tenantStore.tenant?.branding?.logo_url || '')

const getTenantSlug = (): string | undefined => {
  const routeTenant = route.params.tenant as string
  return routeTenant || tenantStore.slug || undefined
}

const phoneInput = ref<HTMLInputElement | null>(null)
const phone = ref('')
const code = ref('')
const method = ref<OtpMethod>('sms')
const error = ref('')
const codeSent = ref(false)
const sendingCode = ref(false)
const verifying = ref(false)
const resendCountdown = ref(0)

const digitCount = computed(() => phone.value.replace(/\D/g, '').length)
const isValidPhone = computed(() => digitCount.value === 10)
const isValidCode = computed(() => code.value.replace(/\D/g, '').length === 6)
const canSendCode = computed(() => isValidPhone.value && !sendingCode.value && resendCountdown.value === 0)
const canSubmit = computed(() => codeSent.value && isValidCode.value && !verifying.value)

const sendButtonLabel = computed(() => {
  if (sendingCode.value) return 'Enviando…'
  if (resendCountdown.value > 0) return `Reenviar en ${resendCountdown.value}s`
  if (codeSent.value) return 'Reenviar código'
  return 'Enviar código'
})

let countdownInterval: ReturnType<typeof setInterval> | null = null

function startCountdown() {
  resendCountdown.value = 45
  countdownInterval && clearInterval(countdownInterval)
  countdownInterval = setInterval(() => {
    resendCountdown.value--
    if (resendCountdown.value <= 0) {
      clearInterval(countdownInterval!)
      countdownInterval = null
    }
  }, 1000)
}

function handlePhoneInput(event: Event) {
  const input = event.target as HTMLInputElement
  const raw = input.value
  const caretBefore = input.selectionStart ?? raw.length
  // Cuántos dígitos hay antes del caret en el valor crudo
  const digitsBeforeCaret = raw.slice(0, caretBefore).replace(/\D/g, '').length
  const formatted = formatPhoneInput(raw)
  phone.value = formatted

  if (input.value !== formatted) {
    input.value = formatted
  }

  // Reposiciona el caret justo después del Nth dígito en el string formateado
  let pos = 0
  let counted = 0
  while (pos < formatted.length && counted < digitsBeforeCaret) {
    if (/\d/.test(formatted.charAt(pos))) counted++
    pos++
  }
  try { input.setSelectionRange(pos, pos) } catch { /* noop */ }

  if (codeSent.value) {
    codeSent.value = false
    code.value = ''
    resendCountdown.value = 0
    countdownInterval && clearInterval(countdownInterval)
  }
}

function handleCodeInput(event: Event) {
  const input = event.target as HTMLInputElement
  code.value = input.value.replace(/\D/g, '').slice(0, 6)
}

async function handleSendCode() {
  if (!canSendCode.value) return
  error.value = ''
  sendingCode.value = true
  const cleanPhone = phone.value.replace(/\D/g, '')

  try {
    // Si el usuario ya tiene PIN, redirigir a PIN login (sin OTP).
    const userCheck = await authStore.checkUser(cleanPhone)
    if (userCheck.exists && userCheck.has_pin && !userCheck.is_locked) {
      const tenantSlug = getTenantSlug()
      const params = { phone: cleanPhone, redirect: route.query.redirect as string }
      if (tenantSlug) {
        router.push({ name: 'tenant-auth-pin-login', params: { tenant: tenantSlug }, query: params })
      } else {
        router.push({ name: 'auth-pin-login', query: params })
      }
      return
    }

    await authStore.sendOtp(cleanPhone, method.value)
    codeSent.value = true
    startCountdown()
    await nextTick()
    document.getElementById('mc-otp-input')?.focus()
  } catch (e) {
    log.error('sendOtp failed', { error: e })
    error.value = 'No pudimos enviar el código. Intenta de nuevo.'
  } finally {
    sendingCode.value = false
  }
}

async function handleSubmit() {
  if (!canSubmit.value) return
  error.value = ''
  verifying.value = true
  try {
    const result = await authStore.verifyOtp(code.value)
    if (!result.success) {
      if (result.error === 'OTP_EXPIRED') error.value = 'El código expiró. Solicita uno nuevo.'
      else if (result.error === 'MAX_ATTEMPTS_EXCEEDED') error.value = 'Demasiados intentos. Solicita un nuevo código.'
      else if (result.attempts_remaining !== undefined) error.value = `Código incorrecto. ${result.attempts_remaining} intentos restantes.`
      else error.value = 'Código incorrecto. Intenta de nuevo.'
      code.value = ''
      return
    }

    await authStore.checkAuth()
    applicationStore.init()

    const tenantSlug = getTenantSlug()
    const redirect = route.query.redirect as string | undefined

    if (redirect) {
      await router.push(redirect)
      return
    }

    // Para tenants white-label con onboarding dinámico (MoneyCapital y similares):
    //  - Usuario NUEVO (primera vez con este teléfono) → empezar onboarding desde paso 1.
    //  - Usuario YA registrado → ir directo al home (el home decide qué card mostrar
    //    según el estado de la solicitud/préstamo).
    // Otros tenants usan el flujo legacy /{tenant}/solicitud (OnboardingLayout).
    const features = (tenantStore.tenant?.features ?? {}) as Record<string, boolean>
    const useDynamicOnboarding = !!features.unified_auth_screen

    if (useDynamicOnboarding) {
      if (result.isNewUser) {
        await router.push({ name: 'm-onboarding-step' })
      } else {
        await router.push({ name: 'm-home' })
      }
      return
    }

    if (!authStore.onboardingCompleted) {
      const hasProductSelected =
        applicationStore.selectedProduct !== null || applicationStore.simulation !== null
      if (hasProductSelected) {
        await router.push(tenantSlug ? `/${tenantSlug}/solicitud/verificacion` : '/solicitud/verificacion')
      } else {
        await router.push(tenantSlug ? `/${tenantSlug}/solicitud` : '/solicitud')
      }
    } else {
      await router.push(tenantSlug ? `/${tenantSlug}/dashboard` : '/dashboard')
    }
  } catch (e) {
    log.error('verifyOtp failed', { error: e })
    error.value = 'Error al verificar el código. Intenta de nuevo.'
    code.value = ''
  } finally {
    verifying.value = false
  }
}

onMounted(async () => {
  if (!tenantStore.isLoaded) await tenantStore.loadConfig()
  tenantStore.applyTheme()
})

watch(method, () => {
  // Si cambia el método después de mandar código, permitir reenvío inmediato.
  if (codeSent.value) resendCountdown.value = 0
})
</script>

<template>
  <div class="ua-screen">
    <header class="brand-bar">
      <div class="brand">
        <div class="brand-mark">
          <img v-if="brandLogoUrl" :src="brandLogoUrl" :alt="tenantName" />
          <svg v-else viewBox="0 0 40 40" fill="none" aria-hidden="true">
            <circle cx="20" cy="20" r="20" fill="currentColor" />
            <rect x="11" y="15" width="4.5" height="10" rx="2.25" fill="white" />
            <rect x="17.75" y="10" width="4.5" height="20" rx="2.25" fill="white" />
            <rect x="24.5" y="15" width="4.5" height="10" rx="2.25" fill="white" />
          </svg>
        </div>
        <span class="brand-name">{{ tenantName }}</span>
      </div>
    </header>

    <main class="ua-main">
      <h1 class="ua-title">Inicia sesión o regístrate</h1>
      <p class="ua-sub">Tu número celular será tu acceso a la app.</p>

      <form class="ua-form" @submit.prevent="handleSubmit">
        <!-- Número de celular -->
        <div class="phone-field" :class="{ 'phone-field--error': !!error && !codeSent }">
          <div class="phone-prefix">
            <svg class="prefix-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <rect x="6" y="3" width="12" height="18" rx="3" stroke="currentColor" stroke-width="1.3" />
              <circle cx="12" cy="17.5" r="1" fill="currentColor" />
            </svg>
            <span class="cc">+52</span>
            <svg class="prefix-chevron" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>
          <input
            ref="phoneInput"
            type="tel"
            inputmode="numeric"
            :value="phone"
            placeholder="Número de celular"
            autocomplete="tel-national"
            maxlength="12"
            class="phone-input"
            @input="handlePhoneInput"
          />
        </div>

        <!-- Código de verificación con icono candado y botón Enviar -->
        <div class="code-row">
          <div class="code-field" :class="{ 'code-field--disabled': !codeSent && !canSendCode }">
            <svg class="code-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <rect x="5" y="11" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.3" />
              <path d="M8 11V7a4 4 0 018 0v4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" />
            </svg>
            <input
              id="mc-otp-input"
              type="tel"
              inputmode="numeric"
              :value="code"
              placeholder="Código de verificación"
              maxlength="6"
              :disabled="!codeSent"
              class="code-input"
              @input="handleCodeInput"
            />
            <button
              type="button"
              class="btn-send"
              :disabled="!canSendCode"
              @click="handleSendCode"
            >
              {{ sendButtonLabel }}
            </button>
          </div>
        </div>

        <!-- Método: fila simple icono + label, sin borde marcado -->
        <div class="method-options">
          <label class="method-option" :class="{ 'method-option--selected': method === 'sms' }">
            <input v-model="method" type="radio" value="sms" name="otp-method" />
            <span class="method-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M4 6a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2h-6l-4 4v-4H6a2 2 0 01-2-2V6z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" />
                <circle cx="9" cy="10" r="1" fill="currentColor" />
                <circle cx="12" cy="10" r="1" fill="currentColor" />
                <circle cx="15" cy="10" r="1" fill="currentColor" />
              </svg>
            </span>
            <span class="method-label">Recibir código por SMS</span>
          </label>
          <label class="method-option" :class="{ 'method-option--selected': method === 'whatsapp' }">
            <input v-model="method" type="radio" value="whatsapp" name="otp-method" />
            <span class="method-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M3.5 20.5l1.4-4.3A8.5 8.5 0 1 1 8 19.1l-4.5 1.4z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" />
                <path d="M9.2 10.5c.4 1.7 1.6 2.9 3.3 3.3l1-1c.2-.2.5-.3.8-.2l1.7.6c.3.1.5.4.4.7-.3 1.3-1.6 2.1-3 2-3 0-5.5-2.5-5.5-5.5 0-1.4.8-2.6 2-3 .3-.1.6.1.7.4l.6 1.7c.1.3 0 .6-.2.8l-1 1z" fill="currentColor" />
              </svg>
            </span>
            <span class="method-label">Recibir código por WhatsApp</span>
          </label>
        </div>

        <p v-if="error" class="ua-error">{{ error }}</p>

        <button
          type="submit"
          class="btn-continue"
          :disabled="!canSubmit"
        >
          {{ verifying ? 'Verificando…' : 'Continuar' }}
        </button>
      </form>

      <div class="info-pill">
        <span class="info-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
            <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <span>Usa el mismo número para ingresar nuevamente en futuras ocasiones.</span>
      </div>
    </main>
  </div>
</template>

<style scoped>
.ua-screen {
  min-height: 100vh;
  min-height: 100dvh;
  background: #ffffff;
  padding-top: env(safe-area-inset-top);
  padding-bottom: calc(20px + env(safe-area-inset-bottom));
  display: flex;
  flex-direction: column;
  color: #0f172a;
}

.brand-bar {
  padding: 18px 22px 4px;
  background: #ffffff;
}
.brand {
  display: flex;
  align-items: center;
  gap: 12px;
}
.brand-mark {
  width: 42px;
  height: 42px;
  display: grid;
  place-items: center;
  color: var(--tenant-primary, #5B21B6);
  flex-shrink: 0;
}
.brand-mark img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 999px;
}
.brand-mark svg {
  width: 100%;
  height: 100%;
}
.brand-name {
  font-weight: 500;
  font-size: 20px;
  color: var(--tenant-primary, #5B21B6);
  letter-spacing: -0.2px;
}

.ua-main {
  flex: 1;
  padding: 12px 22px 16px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.ua-title {
  font-size: 30px;
  font-weight: 700;
  color: #0f172a;
  margin: 10px 0 0;
  letter-spacing: -0.6px;
  line-height: 1.12;
}
.ua-sub {
  font-size: 15px;
  color: #64748b;
  margin: 0 0 8px;
  line-height: 1.45;
}

.ua-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* Phone field con prefijo +52 — pill con icono de tel y chevron */
.phone-field {
  display: flex;
  align-items: stretch;
  border: 1.5px solid #e5e7eb;
  border-radius: 16px;
  background: #ffffff;
  overflow: hidden;
  transition: border-color 140ms ease, box-shadow 140ms ease;
  min-height: 60px;
}
.phone-field:focus-within {
  border-color: var(--tenant-primary, #5B21B6);
  box-shadow: 0 0 0 4px rgb(var(--primary-100-rgb, 237 233 254) / 0.6);
}
.phone-field--error {
  border-color: #ef4444;
}
.phone-prefix {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 14px 0 18px;
  color: var(--tenant-primary, #5B21B6);
  font-weight: 500;
  font-size: 16px;
  border-right: 1px solid #f1f5f9;
}
.prefix-icon {
  width: 22px;
  height: 22px;
  stroke-width: 2;
}
.prefix-chevron {
  width: 16px;
  height: 16px;
  color: var(--tenant-primary, #5B21B6);
  opacity: 0.8;
  stroke-width: 2.4;
}
.phone-prefix .cc {
  color: var(--tenant-primary, #5B21B6);
}
.phone-input {
  flex: 1;
  border: none;
  padding: 0 16px;
  font-size: 15.5px;
  background: transparent;
  outline: none;
  color: #0f172a;
}
.phone-input::placeholder {
  color: #cfd3da;
  font-weight: 400;
  opacity: 1;
}

/* Código + botón Enviar inline (todo dentro del field) */
.code-row {
  width: 100%;
}
.code-field {
  width: 100%;
  box-sizing: border-box;
  border: 1.5px solid #e5e7eb;
  border-radius: 16px;
  background: #ffffff;
  transition: border-color 140ms ease, box-shadow 140ms ease;
  min-height: 60px;
  display: flex;
  align-items: center;
  padding: 0 6px 0 16px;
  gap: 8px;
}
.code-field:focus-within {
  border-color: var(--tenant-primary, #5B21B6);
  box-shadow: 0 0 0 4px rgb(var(--primary-100-rgb, 237 233 254) / 0.6);
}
.code-field--disabled {
  background: #ffffff;
}
.code-icon {
  width: 22px;
  height: 22px;
  color: var(--tenant-primary, #5B21B6);
  flex-shrink: 0;
  stroke-width: 2;
}
.code-input {
  flex: 1;
  min-width: 0;
  border: none;
  padding: 0;
  font-size: 15.5px;
  background: transparent;
  outline: none;
  letter-spacing: 2px;
  color: #0f172a;
}
.code-input::placeholder {
  color: #cfd3da;
  letter-spacing: 0;
  font-weight: 400;
  opacity: 1;
}
.code-input:disabled {
  cursor: not-allowed;
  color: #0f172a;
  -webkit-text-fill-color: #0f172a;
  opacity: 1;
}
.code-input:disabled::placeholder {
  color: #cfd3da;
  -webkit-text-fill-color: #cfd3da;
  opacity: 1;
}
.btn-send {
  flex-shrink: 0;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: var(--tenant-primary, #5B21B6);
  border: none;
  border-radius: 12px;
  padding: 10px 14px;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  transition: background 120ms ease, opacity 120ms ease;
  white-space: nowrap;
  -webkit-tap-highlight-color: transparent;
}
.btn-send:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.btn-send:not(:disabled):active {
  background: rgb(var(--primary-200-rgb, 221 214 254) / 1);
}

/* Opciones SMS / WhatsApp — fila plana sin borde, icono morado + label */
.method-options {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 8px;
}
.method-option {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 12px;
  cursor: pointer;
  background: transparent;
  border-radius: 12px;
  transition: background 140ms ease;
  -webkit-tap-highlight-color: transparent;
}
.method-option input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.method-option--selected {
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
}
.method-icon {
  width: 30px;
  height: 30px;
  color: var(--tenant-primary, #5B21B6);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.method-icon svg {
  width: 100%;
  height: 100%;
  stroke-width: 2;
}
.method-label {
  font-size: 15px;
  font-weight: 500;
  color: var(--tenant-primary, #5B21B6);
}

.ua-error {
  color: #ef4444;
  font-size: 13px;
  margin: 0;
}

.btn-continue {
  margin-top: 10px;
  width: 100%;
  background: var(--tenant-primary, #5B21B6);
  color: #ffffff;
  border: none;
  border-radius: 18px;
  padding: 18px;
  font-size: 17px;
  font-weight: 700;
  letter-spacing: 0.2px;
  cursor: pointer;
  transition: opacity 120ms ease, transform 120ms ease;
  box-shadow: 0 10px 22px -10px rgb(var(--primary-500-rgb, 139 92 246) / 0.6);
  -webkit-tap-highlight-color: transparent;
}
.btn-continue:disabled {
  opacity: 0.55;
  cursor: not-allowed;
  box-shadow: none;
}
.btn-continue:not(:disabled):active {
  transform: translateY(1px);
}

/* Info pill al fondo con badge circular shield */
.info-pill {
  margin-top: auto;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 14px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: #475569;
  font-size: 13px;
  line-height: 1.45;
}
.info-icon {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  color: var(--tenant-primary, #5B21B6);
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border-radius: 999px;
  display: grid;
  place-items: center;
}
.info-icon svg {
  width: 20px;
  height: 20px;
}
</style>
