<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useTenantStore } from '@/stores/tenant'
import { useApplicationStore } from '@/stores/application'
import { useOnboardingStore } from '@/stores/onboarding'
import OnboardingStepRenderer from '@/components/onboarding/OnboardingStepRenderer.vue'
import type { OnboardingStep } from '@/types/v2/onboardingStep'
import { logger } from '@/utils/logger'
import { formatCurrency } from '@/utils/formatters'

/**
 * Vista de onboarding dinámica (white-label).
 *
 * Layout progressive según el step:
 *
 * - FIRST personal step (pantalla 3, educación):
 *     Hero card grande + dots + lista inline + auto-avance al seleccionar
 *     (sin botón Continuar visible)
 *
 * - SUBSEQUENT personal steps (pantallas 4-7):
 *     Compact pill "Hasta $15,000" + dots ✓ con check + copy reducido,
 *     bottom sheet auto-abierto con el renderer del step, auto-avance al
 *     seleccionar opción (select / number_select). State_city muestra
 *     Continuar dentro del sheet porque requiere 2 campos.
 *
 * - NON-personal steps (references, bank, kyc, review): layout estándar
 *     con botón Continuar al fondo.
 */

const log = logger.child('DynamicOnboarding')
const router = useRouter()
const route = useRoute()
const tenantStore = useTenantStore()
const applicationStore = useApplicationStore()
const onboardingStore = useOnboardingStore()

const isLoading = ref(true)

const steps = computed<OnboardingStep[]>(() => {
  const product = applicationStore.selectedProduct
  const raw = (product?.onboarding_steps as unknown as OnboardingStep[]) ?? []
  // Filtrar pasos por `condition` según integraciones activas del tenant.
  // `unless_kyc_provider`: el paso solo aplica si NO hay proveedor KYC activo
  // (porque típicamente Nubarium extrae estos datos del INE automáticamente).
  const hasKyc = tenantStore.hasKycProvider
  return raw.filter((s) => {
    const cond = (s as unknown as { condition?: string }).condition
    if (!cond) return true
    if (cond === 'unless_kyc_provider') return !hasKyc
    if (cond === 'if_kyc_provider') return hasKyc
    return true
  })
})

const currentIndex = computed(() => {
  const stepId = route.params.stepId as string
  return steps.value.findIndex((s) => s.id === stepId)
})

const currentStep = computed<OnboardingStep | null>(() => steps.value[currentIndex.value] ?? null)

const formData = computed<Record<string, unknown>>(() => onboardingStore.dynamicData ?? {})

const currentValue = computed({
  get(): unknown {
    if (!currentStep.value) return null
    return (formData.value as Record<string, unknown>)[currentStep.value.id] ?? null
  },
  set(v: unknown) {
    if (!currentStep.value) return
    onboardingStore.setDynamicField(currentStep.value.id, v)
    if (currentStep.value.type === 'select' || currentStep.value.type === 'number_select') {
      onboardingStore.setDynamicField(currentStep.value.field, v)
    } else if (currentStep.value.type === 'state_city' && v && typeof v === 'object') {
      const sc = v as { state: string; city: string }
      onboardingStore.setDynamicField('state', sc.state)
      onboardingStore.setDynamicField('city', sc.city)
    }
  },
})

const canContinue = computed(() => {
  const s = currentStep.value
  if (!s) return false
  const v = currentValue.value
  if (s.type === 'review' || s.type === 'review_full') return true
  if (s.type === 'state_city') {
    const sc = v as { state: string; city: string } | null
    return !!sc && !!sc.state && !!sc.city
  }
  if (s.type === 'references') {
    const refs = v as Array<{ name: string; phone: string }> | null
    if (!refs || refs.length < 2) return false
    return refs.every((r) => {
      const parts = (r.name || '').trim().split(/\s+/).filter(Boolean)
      const nameOk = parts.length >= 2 && parts.every((p) => p.length >= 2)
      return nameOk && r.phone.replace(/\D/g, '').length === 10
    })
  }
  if (s.type === 'bank_account') {
    const ba = v as { type?: string; bank_code?: string; account_number?: string } | null
    if (!ba || !ba.bank_code || !ba.account_number) return false
    const max = ba.type === 'CARD' ? 16 : 18
    return ba.account_number.replace(/\D/g, '').length === max
  }
  if (s.type === 'kyc_ine') {
    const k = v as {
      front_image?: string; back_image?: string;
      personal?: { curp?: string; clave_elector?: string; numero_ocr?: string }
    } | null
    if (!k?.front_image || !k?.back_image) return false
    if (tenantStore.hasKycProvider) return true
    // Sin OCR: validar los IDENTIFICADORES del INE (CURP, clave de elector, OCR).
    const p = k.personal ?? {}
    const curpOk = !!p.curp && /^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/.test(p.curp.toUpperCase())
    const claveOk = !!p.clave_elector && /^[A-Z0-9]{18}$/.test(p.clave_elector.toUpperCase())
    const ocrOk = !!p.numero_ocr && /^\d{13}$/.test(p.numero_ocr)
    return curpOk && claveOk && ocrOk
  }
  if (s.type === 'personal_data') {
    const pd = v as { first_name?: string; last_name?: string; birth_date?: string; rfc?: string; gender?: string; is_mexican?: string; birth_state?: string; nationality?: string } | null
    if (!pd) return false
    if (!pd.first_name || pd.first_name.trim().length < 2) return false
    if (!pd.last_name || pd.last_name.trim().length < 2) return false
    if (!pd.birth_date || pd.birth_date.length !== 10) return false
    if (pd.gender !== 'M' && pd.gender !== 'F') return false
    if (pd.is_mexican !== 'SI' && pd.is_mexican !== 'NO') return false
    if (pd.is_mexican === 'SI' && !pd.birth_state) return false
    if (!pd.rfc || !/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/.test(pd.rfc.toUpperCase())) return false
    return true
  }
  if (s.type === 'address') {
    const ad = v as { postal_code?: string; state?: string; municipality?: string; neighborhood?: string; street?: string; ext_number?: string; housing_type?: string; years_at_address?: number; months_at_address?: number } | null
    return !!ad
      && /^\d{5}$/.test(ad.postal_code || '')
      && !!ad.state && !!ad.municipality && !!ad.neighborhood && !!ad.street && !!ad.ext_number
      && !!ad.housing_type
      && ((ad.years_at_address ?? 0) > 0 || (ad.months_at_address ?? 0) > 0)
  }
  if (s.type === 'kyc_selfie') {
    return typeof v === 'string' && v.length > 0
  }
  return v !== null && v !== '' && v !== undefined
})

const PERSONAL_STEP_TYPES = ['select', 'state_city']
const personalSteps = computed(() =>
  steps.value.filter((s) => PERSONAL_STEP_TYPES.includes(s.type)),
)
const personalCurrentIdx = computed(() => {
  if (!currentStep.value) return -1
  return personalSteps.value.findIndex((s) => s.id === currentStep.value!.id)
})
const isPersonalStep = computed(() => personalCurrentIdx.value >= 0)
const isFirstPersonalStep = computed(() => personalCurrentIdx.value === 0)

// Divide los steps en dos fases: la primera termina en el primer 'review'
// (review_personal en MoneyCapital). Si no hay review, fallback a la mitad.
const phaseSplitIdx = computed(() => {
  const i = steps.value.findIndex((s) => s.type === 'review')
  if (i >= 0) return i
  return Math.max(0, Math.floor(steps.value.length / 2) - 1)
})
const phaseNumber = computed<1 | 2>(() =>
  currentIndex.value <= phaseSplitIdx.value ? 1 : 2,
)
const phaseSteps = computed(() => {
  if (phaseNumber.value === 1) return steps.value.slice(0, phaseSplitIdx.value + 1)
  return steps.value.slice(phaseSplitIdx.value + 1)
})
const phaseCurrentIdx = computed(() =>
  phaseNumber.value === 1
    ? currentIndex.value
    : currentIndex.value - (phaseSplitIdx.value + 1),
)
const phaseLabel = computed(() =>
  phaseNumber.value === 1 ? 'Datos personales' : 'Verificación',
)

// Timers con cleanup para no disparar callbacks tras desmontar el componente.
let phaseTransitionTimer: ReturnType<typeof setTimeout> | null = null
let autoAdvanceTimer: ReturnType<typeof setTimeout> | null = null

// Overlay de transición al pasar de fase 1 a fase 2
const showPhaseTransition = ref(false)
watch(phaseNumber, (next, prev) => {
  if (prev === 1 && next === 2) {
    showPhaseTransition.value = true
    if (phaseTransitionTimer) clearTimeout(phaseTransitionTimer)
    phaseTransitionTimer = setTimeout(() => { showPhaseTransition.value = false }, 1400)
  }
})
const isAutoAdvanceStep = computed(() => {
  const s = currentStep.value
  if (!s) return false
  return s.type === 'select' || s.type === 'number_select'
})

const product = computed(() => applicationStore.selectedProduct)
const maxAmountFmt = computed(() => {
  const max = (product.value as unknown as { max_amount?: number | string })?.max_amount
  return max == null ? '$15,000' : formatCurrency(Number(max))
})
const minAmountFmt = computed(() => {
  const min = (product.value as unknown as { min_amount?: number | string })?.min_amount
  return min == null ? '$300' : formatCurrency(Number(min))
})

const headerTitle = computed(() => {
  const s = currentStep.value
  if (!s) return 'Información personal'
  if (PERSONAL_STEP_TYPES.includes(s.type)) return 'Información personal'
  if (s.type === 'references') return 'Información de referencias'
  if (s.type === 'number_select') return 'Información crediticia'
  if (s.type === 'bank_account') return 'Cuenta bancaria'
  if (s.type === 'kyc_ine') return 'Validación de INE'
  if (s.type === 'personal_data') return 'Datos personales'
  if (s.type === 'address') return 'Domicilio'
  if (s.type === 'kyc_selfie') return 'Validación facial'
  if (s.type === 'review' || s.type === 'review_full') return 'Información personal'
  return s.label || 'Información personal'
})

const useSheet = computed(() => isPersonalStep.value && !isFirstPersonalStep.value)
const sheetOpen = ref(false)

watch(useSheet, (val) => {
  sheetOpen.value = val
}, { immediate: true })

watch(currentStep, () => {
  if (useSheet.value) sheetOpen.value = true
})

// Subtitle del sheet por tipo de step
const sheetSubtitle = computed(() => {
  const s = currentStep.value
  if (!s) return ''
  if (s.type === 'state_city') return 'Selecciona tu estado y tu ciudad.'
  if (s.type === 'select') return ''
  return ''
})

// Mensaje de copy en el header compacto por step
const compactCopy = computed(() => {
  const s = currentStep.value
  if (!s) return ''
  if (s.type === 'state_city') return 'Selecciona dónde vives actualmente. Tu ubicación nos ayuda a continuar con tu evaluación.'
  if (s.id === 'marital') return 'Completa tu información personal con datos reales. Esto ayudará a evaluar tu solicitud.'
  if (s.id === 'employment') return 'Selecciona tu actividad actual. Esto nos ayuda a evaluar mejor tu solicitud.'
  if (s.id === 'salary_range') return 'Indicar tu ingreso aproximado nos ayuda a evaluar tu solicitud y presentarte una oferta adecuada.'
  return 'Completa tu información personal con datos reales. Esto ayudará a evaluar tu solicitud.'
})

async function ensureApplication() {
  if (applicationStore.currentApplication?.id) return applicationStore.currentApplication
  const prod = applicationStore.selectedProduct as unknown as {
    id: string
    max_amount?: number | string
    min_amount?: number | string
    min_term_months?: number
    rules?: {
      default_amount?: number | string
      min_amount?: number | string
      default_term_days?: number
      min_term_days?: number
      term_in_days?: boolean
      payment_frequencies?: string[]
    }
  } | null
  if (!prod?.id) return null

  const rules = prod.rules ?? {}
  const sim = applicationStore.simulation
  // Monto inicial: 1) lo simulado, 2) `rules.default_amount` del producto, 3) min_amount, 4) fallback 1000.
  const amount = Number(
    sim?.requested_amount
      ?? rules.default_amount
      ?? prod.min_amount
      ?? 1000,
  )
  // Plazo: si el producto se mide en días, mandamos `requested_term_days`
  // (10 para MC). `term_months` se queda en 1 para pasar la validación del backend.
  const term = sim?.term_months ?? Number(prod.min_term_months ?? 1)
  const termDays = rules.term_in_days
    ? Number(rules.default_term_days ?? rules.min_term_days ?? 10)
    : undefined
  // Frecuencia real del producto (SINGLE para BULLET de MoneyCapital).
  const freq = sim?.payment_frequency
    ?? (rules.payment_frequencies?.[0] as string | undefined)
    ?? 'MONTHLY'
  try {
    const app = await applicationStore.createApplication({
      product_id: prod.id,
      requested_amount: amount,
      term_months: term,
      requested_term_days: termDays,
      payment_frequency: freq as never,
    })
    return app
  } catch (e) {
    log.warn('ensureApplication failed', { error: e })
    return null
  }
}

// Persiste el step recién terminado vía dispatcher (onboardingStore.persistDynamic).
// Cada step llama el endpoint del profile correspondiente (Person, Address, Employment, etc.).
async function persistCurrentStep() {
  const s = currentStep.value
  if (!s) return
  try {
    await onboardingStore.persistDynamic(s.id, s.type, currentValue.value)
  } catch (e) {
    log.warn('persistCurrentStep failed', { error: e, stepId: s.id })
  }
}

async function finishOnboarding() {
  try {
    const app = await ensureApplication()
    await persistCurrentStep()
    if (app?.id) {
      try {
        await applicationStore.submitApplication()
      } catch (e) {
        log.warn('submitApplication failed', { error: e })
      }
    } else {
      log.warn('No application could be created to finish onboarding')
    }
  } finally {
    // Siempre navegar al home (mostrará el estado correcto según lo que haya en backend)
    await router.replace({ name: 'm-home' })
  }
}

// Auto-avance para single-select cuando v cambia
watch(currentValue, async (v, prev) => {
  if (!isAutoAdvanceStep.value) return
  if (v === null || v === undefined || v === '') return
  if (v === prev) return
  if (autoAdvanceTimer) clearTimeout(autoAdvanceTimer)
  autoAdvanceTimer = setTimeout(async () => {
    if (!canContinue.value) return
    await persistCurrentStep()
    const nextIdx = currentIndex.value + 1
    if (nextIdx >= steps.value.length) {
      await finishOnboarding()
      return
    }
    sheetOpen.value = false
    const nextId = steps.value[nextIdx]!.id
    await router.push({ name: 'm-onboarding-step', params: { stepId: nextId } })
  }, 250)
})

async function next() {
  if (!canContinue.value || !currentStep.value) return
  await persistCurrentStep()
  const nextIdx = currentIndex.value + 1
  if (nextIdx >= steps.value.length) {
    await finishOnboarding()
    return
  }
  const nextId = steps.value[nextIdx]!.id
  await router.push({ name: 'm-onboarding-step', params: { stepId: nextId } })
}

async function prev() {
  const prevIdx = currentIndex.value - 1
  if (prevIdx < 0) {
    const slug = tenantStore.slug
    await router.push(slug ? `/${slug}` : '/')
    return
  }
  const prevId = steps.value[prevIdx]!.id
  await router.push({ name: 'm-onboarding-step', params: { stepId: prevId } })
}

function closeSheet() {
  sheetOpen.value = false
}

onMounted(async () => {
  if (!tenantStore.isLoaded) await tenantStore.loadConfig()
  tenantStore.applyTheme()
  await onboardingStore.init()

  if (!applicationStore.selectedProduct && tenantStore.activeProducts.length > 0) {
    applicationStore.setSelectedProduct(tenantStore.activeProducts[0] ?? null)
  }

  // Crea la application lo antes posible (idempotente) para que exista al
  // momento de subir documentos y de hacer submit al final del flujo.
  await ensureApplication()

  if (!route.params.stepId && steps.value.length > 0) {
    await router.replace({ name: 'm-onboarding-step', params: { stepId: steps.value[0]!.id } })
  }

  isLoading.value = false
})

onUnmounted(() => {
  if (phaseTransitionTimer) clearTimeout(phaseTransitionTimer)
  if (autoAdvanceTimer) clearTimeout(autoAdvanceTimer)
})
</script>

<template>
  <div class="dyn-onboarding">
    <header class="dyn-header">
      <div class="header-row">
        <button type="button" class="back-btn" aria-label="Atrás" @click="prev">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
        <h1 class="header-title">{{ headerTitle }}</h1>
        <div class="header-spacer" />
      </div>
      <div class="phase-meta">
        <span class="phase-label">{{ phaseLabel }}</span>
        <span class="phase-count">Paso {{ phaseCurrentIdx + 1 }} de {{ phaseSteps.length }} · Sección {{ phaseNumber }} de 2</span>
      </div>
      <div class="step-dots step-dots--header">
        <div
          v-for="(s, i) in phaseSteps"
          :key="s.id"
          class="dot"
          :class="{
            'dot--done': i < phaseCurrentIdx,
            'dot--current': i === phaseCurrentIdx,
          }"
        >
          <svg v-if="i < phaseCurrentIdx" viewBox="0 0 24 24" fill="none">
            <path d="M5 12l5 5L20 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          <span v-else class="dot-inner" />
        </div>
      </div>
    </header>

    <main class="dyn-body">
      <!-- Hero card solo en el primer step personal -->
      <section v-if="isFirstPersonalStep" class="hero-card">
        <span class="hero-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" />
            <path d="M8 14l3-3 2 2 4-4M14 9h3v3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <circle cx="12" cy="16.5" r="1.2" fill="currentColor" />
          </svg>
        </span>
        <div class="hero-body">
          <span class="hero-label">Hasta</span>
          <div class="hero-amount">
            <span class="hero-amount-value">{{ maxAmountFmt }}</span>
            <span class="hero-amount-currency">MXN</span>
          </div>
          <span class="hero-pill">Solicita desde <strong>{{ minAmountFmt }}</strong> MXN</span>
        </div>
      </section>

      <!-- Pill "Hasta $15,000" en pasos personales 2-5 (sin dots a la derecha) -->
      <span v-else-if="isPersonalStep" class="compact-pill">Hasta <strong>{{ maxAmountFmt }}</strong></span>

      <!-- Copy contextual por layout -->
      <p v-if="isFirstPersonalStep" class="motivational">
        Completa tu información personal con datos reales. Esto nos ayuda a evaluar tu solicitud y puede
        <strong>mejorar tu perfil crediticio.</strong>
      </p>
      <p v-else-if="isPersonalStep" class="motivational motivational--compact">{{ compactCopy }}</p>

      <!-- Renderer del paso: layouts A y C lo renderizan inline.
           LAYOUT B (sheet) lo renderiza dentro del bottom-sheet. -->
      <OnboardingStepRenderer
        v-if="currentStep && !useSheet"
        v-model="currentValue"
        :step="currentStep"
        :form-data="formData"
      />

      <div v-if="isLoading" class="loading"><div class="spin" /></div>

      <!-- Security pill (excepto cuando hay sheet abierto) -->
      <div v-if="!sheetOpen && isFirstPersonalStep" class="security-pill">
        <span class="security-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
            <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <span>Tu información está protegida con los más altos estándares de seguridad.</span>
      </div>
    </main>

    <!-- Footer Continuar: solo para steps que NO auto-avanzan ni usan sheet -->
    <footer v-if="!isAutoAdvanceStep && !useSheet" class="dyn-footer">
      <button
        type="button"
        class="btn-continue"
        :disabled="!canContinue"
        @click="next"
      >
        Continuar
      </button>
    </footer>

    <!-- Bottom sheet para personal steps 2-5 -->
    <Teleport to="body">
      <div v-if="sheetOpen && currentStep" class="sheet-overlay" @click.self="closeSheet">
        <div class="sheet">
          <div class="sheet-handle" />
          <header class="sheet-header">
            <h2>{{ currentStep.label }}</h2>
            <button type="button" class="sheet-close" aria-label="Cerrar" @click="closeSheet">
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
            </button>
          </header>
          <p v-if="sheetSubtitle" class="sheet-subtitle">{{ sheetSubtitle }}</p>
          <div class="sheet-body">
            <OnboardingStepRenderer
              :model-value="currentValue"
              :step="currentStep"
              :form-data="formData"
              @update:model-value="(v) => (currentValue = v)"
            />
          </div>
          <div v-if="currentStep.type === 'state_city'" class="sheet-footer">
            <button
              type="button"
              class="btn-continue"
              :disabled="!canContinue"
              @click="next"
            >
              Continuar
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Overlay de transición al pasar de fase 1 a fase 2 -->
    <Teleport to="body">
      <Transition name="phase-fade">
        <div v-if="showPhaseTransition" class="phase-transition">
          <div class="phase-transition__card">
            <div class="phase-transition__ring">
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M5 12l5 5L20 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </div>
            <p class="phase-transition__eyebrow">Sección 1 completada</p>
            <h2 class="phase-transition__title">Verificación</h2>
            <p class="phase-transition__copy">Continuemos con tus referencias y documentos.</p>
            <div class="phase-transition__bar"><span /></div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.dyn-onboarding {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-height: 100dvh;
  background: #ffffff;
  padding-top: env(safe-area-inset-top);
  color: #0f172a;
}

.dyn-header {
  position: sticky;
  top: 0;
  z-index: 30;
  display: flex;
  flex-direction: column;
  padding: 10px 16px 12px;
  gap: 8px;
  background: #ffffff;
  border-bottom: 1px solid #eef0f4;
  padding-top: calc(10px + env(safe-area-inset-top));
}
.header-row {
  display: flex;
  align-items: center;
  gap: 12px;
}
.back-btn {
  background: transparent;
  border: none;
  padding: 4px;
  cursor: pointer;
  color: var(--tenant-primary, #5B21B6);
}
.back-btn svg {
  width: 22px;
  height: 22px;
}
.header-title {
  flex: 1;
  text-align: center;
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}
.header-spacer { width: 30px; }

.phase-meta {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 10px;
  padding: 0 2px;
}
.phase-label {
  font-size: 12.5px;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: var(--tenant-primary, #5B21B6);
}
.phase-count {
  font-size: 11.5px;
  color: #64748b;
  font-weight: 500;
}
.step-dots--header { margin: 2px 0 0; }

.dyn-body {
  flex: 1;
  padding: 12px 18px 100px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.hero-card {
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border-radius: 18px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 14px;
}
.hero-icon {
  width: 56px; height: 56px;
  border-radius: 999px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: var(--tenant-primary, #5B21B6);
  display: grid; place-items: center;
  flex-shrink: 0;
}
.hero-icon svg { width: 28px; height: 28px; }
.hero-body { flex: 1; display: flex; flex-direction: column; gap: 4px; }
.hero-label { font-size: 14px; color: #475569; }
.hero-amount { display: flex; align-items: baseline; gap: 6px; color: var(--tenant-primary, #5B21B6); }
.hero-amount-value { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; }
.hero-amount-currency { font-size: 12px; font-weight: 700; }
.hero-pill { align-self: flex-start; background: #fff; border-radius: 999px; padding: 4px 12px; font-size: 12px; color: #475569; }
.hero-pill strong { color: var(--tenant-primary, #5B21B6); font-weight: 700; }

.step-dots {
  display: flex; align-items: center; gap: 4px; margin: 4px 0 0;
}
.dot {
  flex: 1; height: 20px; display: grid; place-items: center; position: relative;
}
.dot::after { content: ''; position: absolute; left: 50%; right: -50%; top: 50%; height: 2px; background: #e2e8f0; z-index: 0; }
.dot:last-child::after { display: none; }
.dot-inner { width: 14px; height: 14px; border-radius: 999px; border: 2px solid #cbd5e1; background: #fff; position: relative; z-index: 1; }
.dot--current .dot-inner { border-color: var(--tenant-primary, #5B21B6); border-width: 2.5px; }
.dot--done { position: relative; z-index: 1; }
.dot--done::before { content: ''; width: 14px; height: 14px; border-radius: 999px; background: var(--tenant-primary, #5B21B6); position: absolute; z-index: 0; }
.dot--done svg { width: 8px; height: 8px; position: relative; z-index: 1; }
.dot--done::after { background: var(--tenant-primary, #5B21B6); }

/* Pill "Hasta $15,000" (pantallas personales 2-5) */
.compact-pill {
  align-self: flex-start;
  background: var(--tenant-primary, #5B21B6);
  color: #ffffff;
  font-size: 12.5px;
  font-weight: 600;
  padding: 8px 16px;
  border-radius: 999px;
  line-height: 1;
}
.compact-pill strong { font-weight: 700; }

.motivational {
  font-size: 14px; color: #475569; line-height: 1.5; margin: 0;
}
.motivational--compact { font-size: 13px; }
.motivational strong { color: var(--tenant-primary, #5B21B6); font-weight: 700; }

.loading { display: grid; place-items: center; padding: 32px 0; }
.spin { width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: var(--tenant-primary, #5B21B6); border-radius: 999px; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.security-pill {
  margin-top: auto;
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px; border-radius: 14px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: #475569; font-size: 12.5px; line-height: 1.4;
}
.security-icon { width: 24px; height: 24px; flex-shrink: 0; color: var(--tenant-primary, #5B21B6); }
.security-icon svg { width: 100%; height: 100%; }

.dyn-footer {
  position: sticky; bottom: 0; background: #fff;
  padding: 12px 18px calc(12px + env(safe-area-inset-bottom));
  box-shadow: 0 -8px 18px -12px rgba(15, 23, 42, 0.08);
}
.btn-continue {
  width: 100%;
  background: var(--tenant-primary, #5B21B6);
  color: #fff; border: none;
  border-radius: 18px;
  padding: 17px;
  font-size: 16.5px; font-weight: 700;
  cursor: pointer;
  transition: opacity 120ms ease, transform 120ms ease;
  box-shadow: 0 10px 22px -10px rgb(var(--primary-500-rgb, 139 92 246) / 0.55);
  -webkit-tap-highlight-color: transparent;
}
.btn-continue:disabled { opacity: 0.5; cursor: not-allowed; box-shadow: none; }
.btn-continue:not(:disabled):active { transform: translateY(1px); }

/* Sheet */
.sheet-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: grid; place-items: end center;
  z-index: 60;
  animation: fadeIn 160ms ease;
}
.sheet {
  background: #fff;
  width: 100%; max-width: 520px;
  max-height: 85vh;
  border-radius: 24px 24px 0 0;
  display: flex; flex-direction: column;
  animation: slideUp 220ms ease;
  padding-bottom: env(safe-area-inset-bottom);
}
.sheet-handle {
  width: 40px; height: 4px;
  background: #e2e8f0; border-radius: 999px;
  margin: 10px auto 6px;
}
.sheet-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 4px 20px 0;
}
.sheet-header h2 {
  font-size: 16.5px; font-weight: 700; color: #0f172a; margin: 0;
}
.sheet-close {
  background: transparent; border: none; cursor: pointer; color: #64748b;
  width: 30px; height: 30px; display: grid; place-items: center; border-radius: 999px;
}
.sheet-close svg { width: 18px; height: 18px; }
.sheet-subtitle {
  margin: 4px 20px 8px;
  font-size: 13px; color: #64748b; text-align: center;
}
.sheet-body {
  flex: 1; overflow-y: auto;
  padding: 8px 20px 16px;
}
.sheet-footer {
  padding: 12px 20px;
  border-top: 1px solid #f1f5f9;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }

/* Overlay de transición fase 1 → fase 2 */
.phase-transition {
  position: fixed; inset: 0;
  z-index: 200;
  display: grid; place-items: center;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(6px);
  padding: 24px;
}
.phase-transition__card {
  width: 100%; max-width: 320px;
  background: #ffffff;
  border-radius: 22px;
  padding: 28px 24px 24px;
  text-align: center;
  box-shadow: 0 20px 50px -16px rgba(15, 23, 42, 0.3);
  animation: pt-pop 360ms cubic-bezier(0.22, 1, 0.36, 1);
}
.phase-transition__ring {
  width: 64px; height: 64px;
  margin: 0 auto 14px;
  border-radius: 999px;
  background: var(--tenant-primary, #5B21B6);
  display: grid; place-items: center;
  box-shadow: 0 0 0 6px rgba(91, 33, 182, 0.12);
  animation: pt-ring 360ms cubic-bezier(0.22, 1, 0.36, 1);
}
.phase-transition__ring svg { width: 28px; height: 28px; }
.phase-transition__eyebrow {
  margin: 0;
  font-size: 11.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.06em;
  color: #16a34a;
}
.phase-transition__title {
  margin: 6px 0 4px;
  font-size: 22px; font-weight: 800; color: #0f172a;
}
.phase-transition__copy {
  margin: 0 0 18px;
  font-size: 13.5px; color: #475569; line-height: 1.5;
}
.phase-transition__bar {
  height: 4px; border-radius: 999px;
  background: #eef0f4; overflow: hidden;
}
.phase-transition__bar span {
  display: block; height: 100%;
  background: var(--tenant-primary, #5B21B6);
  width: 0%;
  animation: pt-fill 1300ms ease forwards;
}
@keyframes pt-pop {
  from { opacity: 0; transform: scale(0.92) translateY(8px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
@keyframes pt-ring {
  from { transform: scale(0.4); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
@keyframes pt-fill { to { width: 100%; } }

.phase-fade-enter-active, .phase-fade-leave-active { transition: opacity 220ms ease; }
.phase-fade-enter-from, .phase-fade-leave-to { opacity: 0; }
</style>
