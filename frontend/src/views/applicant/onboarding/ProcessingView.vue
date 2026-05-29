<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTenantStore } from '@/stores'
import { v2 } from '@/services/v2'

/**
 * Pantalla de procesamiento de solicitud (white-label).
 *
 * Combina los mocks 16 + 18: progress bar con porcentaje, checklist de
 * pasos completados, mensaje de "tu solicitud está siendo procesada",
 * tenant brand y horario de atención.
 *
 * Polls cada 8s al backend para detectar cambios de estado y redirige a
 * la oferta o dashboard cuando aplica.
 */

const route = useRoute()
const router = useRouter()
const tenantStore = useTenantStore()

const applicationId = computed(() => String(route.params.id ?? ''))
const status = ref<string | null>(null)
const progress = ref(29)
let pollId: number | null = null

const tenantName = computed(() => tenantStore.tenant?.name || 'MoneyCapital')
const brandLogoUrl = computed(() => tenantStore.tenant?.branding?.logo_url || '')

const supportHours = computed(() => {
  const s = tenantStore.tenant?.settings as Record<string, unknown> | undefined
  const h = s?.support_hours as Record<string, string> | undefined
  if (!h) return null
  return [
    { day: 'Lunes a viernes:', hours: h.monday_friday || '8:30 a.m. a 6:00 p.m.' },
    { day: 'Sábado:', hours: h.saturday || '8:30 a.m. a 2:00 p.m.' },
    { day: 'Domingo:', hours: h.sunday || 'cerrado' },
  ]
})

interface ChecklistItem {
  id: string
  label: string
  status: 'done' | 'progress' | 'pending'
}

const checklist = ref<ChecklistItem[]>([
  { id: 'personal', label: 'Datos personales', status: 'done' },
  { id: 'ine', label: 'Documento de identidad (INE)', status: 'done' },
  { id: 'bank', label: 'Información bancaria', status: 'done' },
  { id: 'review', label: 'Revisión de la solicitud', status: 'progress' },
])

const refresh = async () => {
  if (!applicationId.value) return
  try {
    const res = await v2.applicant.application.get(applicationId.value)
    status.value = res.data?.status ?? null

    // Map status → progress estimate
    if (status.value === 'IN_REVIEW') progress.value = Math.min(80, progress.value + 1)
    else if (status.value === 'APPROVED' || status.value === 'PRE_APPROVED') progress.value = 100

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

onMounted(async () => {
  if (!tenantStore.isLoaded) await tenantStore.loadConfig()
  tenantStore.applyTheme()
  await refresh()
  pollId = window.setInterval(refresh, 8000)
})

onUnmounted(() => {
  if (pollId) window.clearInterval(pollId)
})
</script>

<template>
  <div class="pv-screen">
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

    <main class="pv-main">
      <!-- Ilustración + monto -->
      <div class="illustration">
        <svg viewBox="0 0 120 120" fill="none" aria-hidden="true">
          <!-- mano + monedas estilizadas -->
          <ellipse cx="60" cy="100" rx="48" ry="6" fill="rgb(var(--surface-soft-rgb, 243 242 250) / 1)" />
          <path d="M22 78c0-3 2-5 5-5h66c3 0 5 2 5 5v10c0 3-2 5-5 5H27c-3 0-5-2-5-5V78z" fill="rgb(var(--surface-soft-rgb, 243 242 250) / 1)" stroke="currentColor" stroke-width="1.6" />
          <circle cx="55" cy="50" r="14" fill="white" stroke="currentColor" stroke-width="1.6" />
          <circle cx="78" cy="42" r="11" fill="white" stroke="currentColor" stroke-width="1.6" />
          <text x="55" y="55" text-anchor="middle" font-size="13" font-weight="700" fill="currentColor">$</text>
          <text x="78" y="46" text-anchor="middle" font-size="11" font-weight="700" fill="currentColor">$</text>
        </svg>
      </div>

      <h1 class="pv-progress-percent">{{ progress }}%</h1>
      <div class="pv-progress-bar">
        <div class="pv-progress-fill" :style="{ width: `${progress}%` }" />
      </div>

      <h2 class="pv-title">Tu solicitud está en proceso de revisión.<br>Por favor, espera.</h2>
      <p class="pv-sub">
        Estamos revisando tu información para brindarte un préstamo responsable y seguro.
      </p>

      <!-- Checklist -->
      <ul class="pv-checklist">
        <li v-for="item in checklist" :key="item.id" class="pv-item">
          <span class="pv-item-icon" :class="`pv-item-icon--${item.status}`">
            <svg v-if="item.status === 'done'" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" fill="currentColor" />
              <path d="M8 12l2.5 2.5L16 9" stroke="white" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <svg v-else-if="item.status === 'progress'" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="3 3" />
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" opacity="0.4" />
            </svg>
          </span>
          <div class="pv-item-body">
            <span class="pv-item-label" :class="`pv-item-label--${item.status}`">{{ item.label }}</span>
            <span class="pv-item-sub">
              {{ item.status === 'done' ? 'Completado' : item.status === 'progress' ? 'En proceso' : 'Pendiente' }}
            </span>
          </div>
        </li>
      </ul>

      <!-- Pill seguridad -->
      <div class="pv-security">
        <span class="pv-security-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
            <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <span>Tu información está protegida con los más altos estándares de seguridad y privacidad.</span>
      </div>

      <!-- Horario de atención -->
      <section v-if="supportHours" class="pv-support">
        <span class="pv-support-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6" />
            <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <div class="pv-support-body">
          <h3>Horario de atención</h3>
          <ul>
            <li v-for="(h, i) in supportHours" :key="i">
              <span class="day">{{ h.day }}</span>
              <span class="hours">{{ h.hours }}</span>
            </li>
          </ul>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
.pv-screen {
  min-height: 100vh;
  min-height: 100dvh;
  background: #ffffff;
  padding-top: env(safe-area-inset-top);
  padding-bottom: calc(20px + env(safe-area-inset-bottom));
  color: #0f172a;
  display: flex;
  flex-direction: column;
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
.brand-mark img,
.brand-mark svg {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.brand-name {
  font-weight: 500;
  font-size: 20px;
  color: var(--tenant-primary, #5B21B6);
  letter-spacing: -0.2px;
}

.pv-main {
  flex: 1;
  padding: 12px 22px 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.illustration {
  display: grid;
  place-items: center;
  color: var(--tenant-primary, #5B21B6);
}
.illustration svg {
  width: 130px;
  height: 130px;
}

.pv-progress-percent {
  text-align: center;
  font-size: 36px;
  font-weight: 800;
  color: var(--tenant-primary, #5B21B6);
  margin: 0;
  letter-spacing: -0.5px;
}
.pv-progress-bar {
  height: 8px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border-radius: 999px;
  overflow: hidden;
  margin: -4px 0 0;
}
.pv-progress-fill {
  height: 100%;
  background: var(--tenant-primary, #5B21B6);
  border-radius: 999px;
  transition: width 240ms ease;
}

.pv-title {
  text-align: center;
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
  margin: 8px 0 0;
  line-height: 1.35;
}
.pv-sub {
  text-align: center;
  font-size: 13.5px;
  color: #475569;
  margin: 0;
  line-height: 1.5;
}

.pv-checklist {
  list-style: none;
  padding: 14px;
  margin: 4px 0 0;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.pv-item {
  display: flex;
  align-items: center;
  gap: 12px;
}
.pv-item-icon {
  width: 24px;
  height: 24px;
  flex-shrink: 0;
  display: grid;
  place-items: center;
}
.pv-item-icon svg {
  width: 100%;
  height: 100%;
}
.pv-item-icon--done {
  color: var(--tenant-primary, #5B21B6);
}
.pv-item-icon--progress {
  color: var(--tenant-primary, #5B21B6);
}
.pv-item-icon--pending {
  color: #cbd5e1;
}
.pv-item-body {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.pv-item-label {
  font-size: 13.5px;
  font-weight: 600;
  color: #0f172a;
}
.pv-item-label--progress {
  color: var(--tenant-primary, #5B21B6);
}
.pv-item-sub {
  font-size: 11.5px;
  color: #94a3b8;
}

.pv-security {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 14px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: #475569;
  font-size: 12px;
  line-height: 1.45;
}
.pv-security-icon {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  color: var(--tenant-primary, #5B21B6);
  display: grid;
  place-items: center;
}
.pv-security-icon svg {
  width: 22px;
  height: 22px;
}

.pv-support {
  display: flex;
  gap: 12px;
  padding: 14px;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
}
.pv-support-icon {
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  color: var(--tenant-primary, #5B21B6);
}
.pv-support-icon svg {
  width: 100%;
  height: 100%;
}
.pv-support-body h3 {
  margin: 0 0 6px;
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
}
.pv-support-body ul {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.pv-support-body li {
  display: flex;
  justify-content: space-between;
  font-size: 12.5px;
  color: #475569;
  gap: 8px;
}
.pv-support-body .day {
  font-weight: 500;
}
.pv-support-body .hours {
  font-weight: 400;
  color: #64748b;
}
</style>
