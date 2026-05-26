<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTenantStore } from '@/stores'
import { v2 } from '@/services/v2'

const route = useRoute()
const router = useRouter()
const tenantStore = useTenantStore()

const applicationId = computed(() => String(route.params.id ?? ''))

const status = ref<string | null>(null)
let pollId: number | null = null

const supportHours = computed(() => {
  const s = tenantStore.tenant?.settings as Record<string, unknown> | undefined
  return (s?.support_hours as string) || 'Lunes a viernes de 9:00 a 18:00'
})

const supportPhone = computed(() => {
  const s = tenantStore.tenant?.settings as Record<string, unknown> | undefined
  return (s?.support_phone as string) || null
})

const refresh = async () => {
  try {
    const res = await v2.applicant.application.get(applicationId.value)
    status.value = res.data?.status ?? null
    if (status.value === 'APPROVED' || status.value === 'PRE_APPROVED') {
      router.replace({ name: 'm-loan-offer', params: { id: applicationId.value } })
    } else if (status.value === 'DISBURSED' || status.value === 'ACTIVE') {
      router.replace({ name: 'm-loan-dashboard' })
    } else if (status.value === 'REJECTED') {
      router.replace({ name: 'dashboard' })
    }
  } catch {
    // silent retry
  }
}

onMounted(() => {
  refresh()
  pollId = window.setInterval(refresh, 8000)
})

onUnmounted(() => {
  if (pollId) window.clearInterval(pollId)
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <div class="flex-1 flex flex-col items-center justify-center px-6 py-10 text-center">
      <div class="w-32 h-32 mb-6 relative">
        <div class="absolute inset-0 rounded-full bg-primary-100 animate-pulse" />
        <div class="absolute inset-3 rounded-full bg-primary-200 animate-pulse" style="animation-delay: 0.3s" />
        <div class="absolute inset-6 rounded-full bg-primary-600 flex items-center justify-center">
          <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
      </div>

      <h1 class="text-2xl font-bold text-gray-900">¡Felicidades!</h1>
      <p class="text-gray-600 mt-2 max-w-sm">
        Tu solicitud está siendo procesada. Mantente al pendiente de tu teléfono — si es necesario, uno de nuestros agentes te contactará pronto.
      </p>

      <div class="bg-white rounded-2xl p-5 mt-8 shadow-sm border border-gray-100 w-full max-w-sm">
        <p class="text-sm text-gray-500">Gracias por usar</p>
        <p class="text-lg font-bold text-primary-700">{{ tenantStore.tenant?.name || 'nuestra plataforma' }}</p>
      </div>

      <div class="bg-white rounded-2xl p-5 mt-3 shadow-sm border border-gray-100 w-full max-w-sm text-left">
        <p class="text-sm font-semibold text-gray-900 flex items-center gap-2">
          <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Horario de atención
        </p>
        <p class="text-sm text-gray-600 mt-1">{{ supportHours }}</p>
        <a v-if="supportPhone" :href="`tel:${supportPhone}`" class="text-sm text-primary-600 font-semibold mt-2 inline-block">
          {{ supportPhone }}
        </a>
      </div>
    </div>
  </div>
</template>
