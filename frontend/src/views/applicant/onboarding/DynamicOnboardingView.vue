<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useTenantStore } from '@/stores/tenant'
import { useApplicationStore } from '@/stores/application'
import { useOnboardingStore } from '@/stores/onboarding'
import { AppButton } from '@/components/common'
import OnboardingStepRenderer from '@/components/onboarding/OnboardingStepRenderer.vue'
import type { OnboardingStep } from '@/types/v2/onboardingStep'
import { logger } from '@/utils/logger'

/**
 * Vista de onboarding dinámica que lee `product.onboarding_steps` y monta
 * el renderer correcto por step. Reemplaza la cadena Step1..Step8 fija
 * para productos como MoneyCapital que declaran su propio pipeline.
 *
 * URL: `/m/solicitud/:stepId`
 *  - `:stepId` identifica el step actual dentro del array
 *  - El estado del formulario se persiste en `onboardingStore.dynamicData`
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
  return (product?.onboarding_steps as unknown as OnboardingStep[]) ?? []
})

const currentIndex = computed(() => {
  const stepId = route.params.stepId as string
  return steps.value.findIndex((s) => s.id === stepId)
})

const currentStep = computed<OnboardingStep | null>(() => steps.value[currentIndex.value] ?? null)

const formData = computed<Record<string, unknown>>(() => onboardingStore.dynamicData ?? {})

// model bidireccional para el step actual
const currentValue = computed({
  get(): unknown {
    if (!currentStep.value) return null
    return (formData.value as Record<string, unknown>)[currentStep.value.id] ?? null
  },
  set(v: unknown) {
    if (!currentStep.value) return
    onboardingStore.setDynamicField(currentStep.value.id, v)
    // Persistir también los campos individuales si el step es de tipo select/state_city
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
  if (s.type === 'review') return true
  if (s.type === 'state_city') {
    const sc = v as { state: string; city: string } | null
    return !!sc && !!sc.state && !!sc.city
  }
  return v !== null && v !== '' && v !== undefined
})

const progress = computed(() => {
  if (steps.value.length === 0) return 0
  return Math.round(((currentIndex.value + 1) / steps.value.length) * 100)
})

async function next() {
  if (!canContinue.value || !currentStep.value) return
  await onboardingStore.persistDynamic()
  const nextIdx = currentIndex.value + 1
  if (nextIdx >= steps.value.length) {
    await router.push('/solicitud/procesando')
    return
  }
  const nextId = steps.value[nextIdx]!.id
  await router.push({ name: 'm-onboarding-step', params: { stepId: nextId } })
}

async function prev() {
  const prevIdx = currentIndex.value - 1
  if (prevIdx < 0) return
  const prevId = steps.value[prevIdx]!.id
  await router.push({ name: 'm-onboarding-step', params: { stepId: prevId } })
}

function exitOnboarding() {
  router.push('/')
}

onMounted(async () => {
  if (!tenantStore.isLoaded) await tenantStore.loadConfig()
  await onboardingStore.init()

  // Si no hay producto seleccionado, tomar el primero del tenant.
  if (!applicationStore.selectedProduct && tenantStore.activeProducts.length > 0) {
    applicationStore.setSelectedProduct(tenantStore.activeProducts[0] ?? null)
  }

  // Si no hay :stepId en la URL, redirige al primero.
  if (!route.params.stepId && steps.value.length > 0) {
    await router.replace({ name: 'm-onboarding-step', params: { stepId: steps.value[0]!.id } })
  }

  isLoading.value = false
  log.debug('Mounted', { stepId: route.params.stepId, total: steps.value.length })
})

watch(
  () => route.params.stepId,
  (id) => log.debug('Step changed', { id }),
)
</script>

<template>
  <div class="dyn-onboarding">
    <!-- Header con progreso + close -->
    <header class="dyn-header">
      <button type="button" class="close-btn" @click="exitOnboarding">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
      <div class="header-title">Paso {{ currentIndex + 1 }} de {{ steps.length }}</div>
      <div class="spacer" />
    </header>
    <div class="progress-bar">
      <div class="progress-fill" :style="{ width: `${progress}%` }" />
    </div>

    <!-- Body -->
    <main class="dyn-body">
      <div v-if="isLoading || !currentStep" class="loading">
        <div class="spin" />
      </div>
      <OnboardingStepRenderer
        v-else
        v-model="currentValue"
        :step="currentStep"
        :form-data="formData"
      />
    </main>

    <!-- Footer -->
    <footer class="dyn-footer">
      <AppButton
        v-if="currentIndex > 0"
        type="button"
        variant="outline"
        size="lg"
        class="flex-1"
        @click="prev"
      >
        Atrás
      </AppButton>
      <AppButton
        type="button"
        variant="primary"
        size="lg"
        class="flex-1"
        :disabled="!canContinue"
        @click="next"
      >
        Continuar
      </AppButton>
    </footer>
  </div>
</template>

<style scoped>
.dyn-onboarding {
  display: flex; flex-direction: column;
  min-height: 100vh; min-height: 100dvh;
  background: #f8fafc;
  padding-top: env(safe-area-inset-top);
}
.dyn-header { display: flex; align-items: center; padding: 12px 16px 8px; gap: 8px; }
.close-btn { background: transparent; border: none; padding: 6px; color: #475569; cursor: pointer; }
.header-title { flex: 1; text-align: center; font-size: 13px; color: #64748b; font-weight: 500; }
.spacer { width: 32px; }
.progress-bar { height: 4px; background: #e2e8f0; margin: 0 16px; border-radius: 999px; overflow: hidden; }
.progress-fill { height: 100%; background: var(--tenant-primary, #5B21B6); transition: width 200ms ease; }
.dyn-body { flex: 1; padding: 20px 16px 24px; overflow-y: auto; }
.loading { display: grid; place-items: center; padding: 64px 0; }
.spin { width: 32px; height: 32px; border: 3px solid #e2e8f0; border-top-color: var(--tenant-primary, #5B21B6); border-radius: 999px; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.dyn-footer {
  position: sticky; bottom: 0;
  background: #fff; border-top: 1px solid #e2e8f0;
  padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
  display: flex; gap: 8px;
}
</style>
