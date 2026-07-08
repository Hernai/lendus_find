import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useApplicationStore } from '@/stores/application'
import { useTenantStore } from '@/stores/tenant'
import { useOnboardingStore } from '@/stores/onboarding'
import type { OnboardingStep } from '@/types/v2/onboardingStep'

/**
 * Fuente de verdad de los pasos del onboarding dinámico y su fase. Extraído de
 * DynamicOnboardingView para adelgazar el orquestador. Es PURO (solo computeds,
 * sin efectos ni timers):
 * - `steps`: los pasos del producto, filtrados por `condition` (según hasKycProvider).
 * - `currentIndex` / `currentStep`: derivados de `route.params.stepId`.
 * - fases: divide el flujo en 2 (la 1ª termina en el primer `review`).
 */
export function useOnboardingSteps() {
  const route = useRoute()
  const applicationStore = useApplicationStore()
  const tenantStore = useTenantStore()
  const onboardingStore = useOnboardingStore()

  const steps = computed<OnboardingStep[]>(() => {
    const product = applicationStore.selectedProduct
    const raw = (product?.onboarding_steps as unknown as OnboardingStep[]) ?? []
    // Filtrar pasos por `condition` según integraciones activas del tenant.
    // `unless_kyc_provider`: el paso solo aplica si NO hay proveedor KYC activo
    // (porque típicamente Nubarium extrae estos datos del INE automáticamente).
    const hasKyc = tenantStore.hasKycProvider
    // Rama persona física / moral (arrendamiento): la elige el paso
    // `applicant_type_select` (id 'applicant_type') y vive en dynamicData.
    // Hasta que el usuario elige, se asume INDIVIDUAL (muestra su rama).
    const applicantKind = onboardingStore.dynamicData?.['applicant_type'] as string | undefined
    const evalCond = (c: string): boolean => {
      if (c === 'unless_kyc_provider') return !hasKyc
      if (c === 'if_kyc_provider') return hasKyc
      if (c === 'if_company') return applicantKind === 'COMPANY'
      if (c === 'if_individual') return applicantKind !== 'COMPANY'
      return true
    }
    return raw.filter((s) => {
      const cond = (s as unknown as { condition?: string | string[] }).condition
      if (!cond) return true
      // Un array de condiciones se evalúa con AND (todas deben cumplirse). Ej.
      // ['if_individual','unless_kyc_provider'] = persona física Y sin proveedor KYC.
      return Array.isArray(cond) ? cond.every(evalCond) : evalCond(cond)
    })
  })

  const currentIndex = computed(() => {
    const stepId = route.params.stepId as string
    return steps.value.findIndex((s) => s.id === stepId)
  })

  const currentStep = computed<OnboardingStep | null>(() => steps.value[currentIndex.value] ?? null)

  // Divide los steps en dos fases: la primera termina en el primer 'review'
  // (review_personal en MoneyCapital). Si NO hay un `review` intermedio (ej.
  // arrendamiento, que solo tiene `review_full` al final), es UNA sola fase — no se
  // parte a la mitad (eso metía la INE bajo "Datos personales" y descuadraba todo).
  const hasReviewSplit = computed(() => steps.value.some((s) => s.type === 'review'))
  const isSinglePhase = computed(() => !hasReviewSplit.value)
  const phaseSplitIdx = computed(() => {
    const i = steps.value.findIndex((s) => s.type === 'review')
    if (i >= 0) return i
    // Una sola fase: todo cae en fase 1 (split al final).
    return Math.max(0, steps.value.length - 1)
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
    isSinglePhase.value
      ? 'Solicitud'
      : (phaseNumber.value === 1 ? 'Datos personales' : 'Verificación'),
  )

  return {
    steps,
    currentIndex,
    currentStep,
    phaseSplitIdx,
    phaseNumber,
    phaseSteps,
    phaseCurrentIdx,
    phaseLabel,
    isSinglePhase,
  }
}
