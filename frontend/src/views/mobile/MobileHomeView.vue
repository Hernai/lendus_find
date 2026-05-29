<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTenantStore, useAuthStore, useLoanStore } from '@/stores'
import { v2 } from '@/services/v2'
import MobileBottomNav from '@/components/mobile/MobileBottomNav.vue'

/**
 * Home unificado mobile (MoneyCapital y similares).
 *
 * Cabecera morada con logo + bell + avatar y saludo.
 * Body con cards que cambian según el estado del crédito:
 *  - Sin solicitud → card "Iniciar solicitud"
 *  - SUBMITTED / IN_REVIEW / DRAFT → card 29% en proceso
 *  - PRE_APPROVED / APPROVED → card "Tu oferta está lista"
 *  - DISBURSED / ACTIVE → card préstamo + card prórroga + card recompensas + lista
 *  - REJECTED → card "Solicitud no aprobada"
 */

const router = useRouter()
const tenantStore = useTenantStore()
const authStore = useAuthStore()
const loanStore = useLoanStore()

const tenantName = computed(() => tenantStore.tenant?.name || 'MoneyCapital')
const brandLogoUrl = computed(() => tenantStore.tenant?.branding?.logo_url || '')

const application = ref<{ id: string; status: string } | null>(null)
const isLoading = ref(true)
const loadError = ref(false)
const hasLoadedOnce = ref(false)
const progress = ref(29)
const tab = ref<'all' | 'active' | 'completed'>('active')
const extensionDays = ref<7 | 15>(7)
let pollId: number | null = null

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

const status = computed(() => application.value?.status ?? null)
const activeLoan = computed(() => loanStore.activeLoan)

const isProcessing = computed(() =>
  !activeLoan.value && (status.value === 'SUBMITTED' || status.value === 'IN_REVIEW' || status.value === 'DRAFT'),
)
const hasOffer = computed(() =>
  !activeLoan.value && (status.value === 'PRE_APPROVED' || status.value === 'APPROVED'),
)
const isRejected = computed(() => !activeLoan.value && status.value === 'REJECTED')
// "Sin solicitud" solo si ya cargamos al menos una vez SIN error (evita mostrar
// el CTA cuando en realidad falló la red en el primer fetch).
const hasNoApplication = computed(() =>
  !activeLoan.value && !application.value && hasLoadedOnce.value && !loadError.value,
)

const firstName = computed(() => {
  const u = authStore.user as { first_name?: string; name?: string } | null
  return u?.first_name || u?.name?.split(' ')[0] || ''
})

const greeting = computed(() => firstName.value ? `¡Bienvenido ${firstName.value}!` : '¡Bienvenido!')

const formatMoney = (n: number | string | null | undefined) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(n ?? 0))

const formatDate = (d: string | null | undefined) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}

const daysLeft = computed(() => {
  const l = activeLoan.value
  if (!l?.due_date) return null
  const due = new Date(l.due_date).getTime()
  const now = Date.now()
  return Math.max(0, Math.ceil((due - now) / 86400000))
})

const extensionCost = computed(() => {
  const total = Number(activeLoan.value?.total_amount ?? 0)
  // Costo de prórroga: estimación 5% por 7 días, 10% por 15.
  const rate = extensionDays.value === 7 ? 0.05 : 0.1
  return total * rate
})

const newDueDate = computed(() => {
  const l = activeLoan.value
  if (!l?.due_date) return null
  const due = new Date(l.due_date)
  due.setDate(due.getDate() + extensionDays.value)
  return due.toISOString()
})

const visibleLoans = computed(() => {
  const list = loanStore.loans
  if (tab.value === 'active') return list.filter((l) => l.status === 'ACTIVE' || l.status === 'DISBURSED')
  if (tab.value === 'completed') return loanStore.completedLoans
  return list
})

async function refresh() {
  let ok = true
  try {
    const list = await v2.applicant.application.list()
    const items = (list.data?.applications ?? []) as Array<{ id: string; status: string; created_at?: string }>
    if (items.length > 0) {
      const latest = [...items].sort((a, b) => (b.created_at || '').localeCompare(a.created_at || ''))[0]
      application.value = latest
      if (latest.status === 'IN_REVIEW') progress.value = Math.min(80, progress.value + 1)
      else if (latest.status === 'PRE_APPROVED' || latest.status === 'APPROVED') progress.value = 100
    } else {
      application.value = null
    }
  } catch {
    ok = false
  }
  try {
    await loanStore.fetchAll()
  } catch {
    ok = false
  }
  loadError.value = !ok
  hasLoadedOnce.value = true
  isLoading.value = false
}

function startPolling() {
  if (pollId) return
  pollId = window.setInterval(refresh, 8000)
}
function stopPolling() {
  if (pollId) { window.clearInterval(pollId); pollId = null }
}
// Pausa el polling cuando la app pierde foco (ahorra batería/red).
function onVisibility() {
  if (document.hidden) stopPolling()
  else { refresh(); startPolling() }
}

onMounted(async () => {
  if (!tenantStore.isLoaded) await tenantStore.loadConfig()
  tenantStore.applyTheme()
  await refresh()
  startPolling()
  document.addEventListener('visibilitychange', onVisibility)
})

onUnmounted(() => {
  stopPolling()
  document.removeEventListener('visibilitychange', onVisibility)
})

function goToOffer() {
  if (application.value?.id) router.push({ name: 'm-loan-offer', params: { id: application.value.id } })
}
function startApplication() {
  router.push({ name: 'm-onboarding-step' })
}
function goToLoanDetail(id: string) {
  router.push({ name: 'm-loan-detail', params: { id } })
}
function logout() {
  if (window.confirm('¿Deseas cerrar sesión?')) {
    authStore.logout()
  }
}
</script>

<template>
  <div class="home-screen">
    <!-- HEADER MORADO -->
    <header class="hero-header">
      <div class="hero-top">
        <div class="brand">
          <div class="brand-mark">
            <img v-if="brandLogoUrl" :src="brandLogoUrl" :alt="tenantName" />
            <svg v-else viewBox="0 0 40 40" fill="none" aria-hidden="true">
              <circle cx="20" cy="20" r="20" fill="white" />
              <rect x="11" y="15" width="4.5" height="10" rx="2.25" fill="currentColor" />
              <rect x="17.75" y="10" width="4.5" height="20" rx="2.25" fill="currentColor" />
              <rect x="24.5" y="15" width="4.5" height="10" rx="2.25" fill="currentColor" />
            </svg>
          </div>
          <span class="brand-name">{{ tenantName }}</span>
        </div>
        <div class="hero-actions">
          <button type="button" class="icon-btn" aria-label="Notificaciones">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M6 9a6 6 0 1112 0v4l2 3H4l2-3V9z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
              <path d="M10 19a2 2 0 004 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </button>
          <button type="button" class="icon-btn" aria-label="Cerrar sesión" @click="logout">
            <svg viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="8.5" r="3.5" stroke="currentColor" stroke-width="1.8" />
              <path d="M4 21c.5-4 4-6 8-6s7.5 2 8 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </button>
        </div>
      </div>
      <div class="hero-greeting">
        <h1>{{ greeting }}</h1>
        <button v-if="activeLoan" type="button" class="contract-btn" @click="goToLoanDetail(activeLoan.id)">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
            <path d="M14 3v5h5M9 13h6M9 17h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
          </svg>
          Ver contrato
        </button>
      </div>
    </header>

    <!-- BODY -->
    <main class="home-body">
      <div v-if="isLoading" class="spinner-wrap"><div class="spin" /></div>

      <!-- PROCESANDO -->
      <section v-else-if="isProcessing" class="card card--processing">
        <div class="illustration" aria-hidden="true">
          <svg viewBox="0 0 120 120" fill="none">
            <ellipse cx="60" cy="100" rx="48" ry="6" fill="rgb(var(--surface-soft-rgb, 243 242 250) / 1)" />
            <path d="M22 78c0-3 2-5 5-5h66c3 0 5 2 5 5v10c0 3-2 5-5 5H27c-3 0-5-2-5-5V78z" fill="rgb(var(--surface-soft-rgb, 243 242 250) / 1)" stroke="currentColor" stroke-width="1.6" />
            <circle cx="55" cy="50" r="14" fill="white" stroke="currentColor" stroke-width="1.6" />
            <circle cx="78" cy="42" r="11" fill="white" stroke="currentColor" stroke-width="1.6" />
            <text x="55" y="55" text-anchor="middle" font-size="13" font-weight="700" fill="currentColor">$</text>
            <text x="78" y="46" text-anchor="middle" font-size="11" font-weight="700" fill="currentColor">$</text>
          </svg>
        </div>
        <h1 class="progress-percent">{{ progress }}%</h1>
        <div class="progress-bar"><div class="progress-fill" :style="{ width: `${progress}%` }" /></div>
        <h2 class="card-title">Tu solicitud está en proceso de revisión.<br>Por favor, espera.</h2>
        <p class="card-sub">Estamos revisando tu información para brindarte un préstamo responsable y seguro.</p>

        <ul class="checklist">
          <li v-for="item in checklist" :key="item.id" class="check-item">
            <span class="check-icon" :class="`check-icon--${item.status}`">
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
            <div class="check-body">
              <span class="check-label" :class="`check-label--${item.status}`">{{ item.label }}</span>
              <span class="check-sub">{{ item.status === 'done' ? 'Completado' : item.status === 'progress' ? 'En proceso' : 'Pendiente' }}</span>
            </div>
          </li>
        </ul>

        <div class="security">
          <span class="security-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
              <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
          <span>Tu información está protegida con los más altos estándares de seguridad.</span>
        </div>
      </section>

      <!-- OFERTA LISTA -->
      <section v-else-if="hasOffer" class="card card--offer">
        <div class="state-badge">¡Buenas noticias!</div>
        <h2 class="card-title">Tu oferta está lista</h2>
        <p class="card-sub">Revisamos tu solicitud y tenemos una oferta personalizada para ti.</p>
        <button type="button" class="btn-primary" @click="goToOffer">Ver oferta</button>
      </section>

      <!-- RECHAZADO -->
      <section v-else-if="isRejected" class="card card--rejected">
        <h2 class="card-title">Solicitud no aprobada</h2>
        <p class="card-sub">Por el momento no podemos otorgar el préstamo. Puedes intentar de nuevo más adelante.</p>
        <button type="button" class="btn-secondary" @click="startApplication">Iniciar nueva solicitud</button>
      </section>

      <!-- SIN SOLICITUD -->
      <section v-else-if="hasNoApplication" class="card card--empty">
        <h2 class="card-title">¿Listo para tu préstamo?</h2>
        <p class="card-sub">Inicia tu solicitud en pocos minutos y recibe tu oferta personalizada.</p>
        <button type="button" class="btn-primary" @click="startApplication">Iniciar solicitud</button>
      </section>

      <!-- PRÉSTAMO ACTIVO -->
      <template v-else-if="activeLoan">
        <!-- Card préstamo activo -->
        <section class="loan-card">
          <div class="loan-head">
            <span class="loan-icon">
              <svg viewBox="0 0 24 24" fill="none">
                <rect x="3" y="6" width="18" height="13" rx="3" stroke="currentColor" stroke-width="1.6" />
                <circle cx="17" cy="13" r="1.5" fill="currentColor" />
              </svg>
            </span>
            <div class="loan-head-body">
              <span class="loan-title">Préstamo simple</span>
              <span class="loan-status">Activo</span>
            </div>
          </div>
          <div class="loan-amount-row">
            <span class="amount-label">Total a pagar</span>
            <span class="amount-value">{{ formatMoney(activeLoan.total_amount) }}</span>
          </div>
          <div class="loan-meta-row">
            <span class="days-pill"><span class="dot" />Restan {{ daysLeft }} días</span>
            <button type="button" class="btn-primary btn-primary--inline" @click="goToLoanDetail(activeLoan.id)">Pagar</button>
          </div>
          <p class="due-text">Vence: {{ formatDate(activeLoan.due_date) }}</p>
        </section>

        <!-- Card Prórroga -->
        <section class="card extension-card">
          <div class="ext-head">
            <span class="ext-icon">
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M4 12a8 8 0 1115 4l1-4M20 16l-5-1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </span>
            <div>
              <h3>Prórroga / Extensión de fecha</h3>
              <p>Extiende tu fecha de pago y evita mora.</p>
            </div>
          </div>
          <div class="ext-options">
            <button
              type="button"
              class="ext-opt"
              :class="{ 'ext-opt--active': extensionDays === 7 }"
              @click="extensionDays = 7"
            >7 días</button>
            <button
              type="button"
              class="ext-opt"
              :class="{ 'ext-opt--active': extensionDays === 15 }"
              @click="extensionDays = 15"
            >15 días</button>
          </div>
          <div class="ext-info">
            <div>
              <span class="ext-info-label">Costo de prórroga</span>
              <strong>{{ formatMoney(extensionCost) }}</strong>
            </div>
            <div>
              <span class="ext-info-label">Nueva fecha de vencimiento</span>
              <strong>{{ formatDate(newDueDate) }}</strong>
            </div>
          </div>
          <button type="button" class="btn-primary" @click="goToLoanDetail(activeLoan.id)">Solicitar prórroga</button>
          <p class="ext-foot">Evita mora y conserva tu historial.</p>
        </section>

        <!-- Card Recompensas -->
        <section class="card rewards-card">
          <div class="rewards-illust" aria-hidden="true">
            <svg viewBox="0 0 64 64" fill="none">
              <rect x="8" y="22" width="48" height="32" rx="3" fill="#A78BFA" />
              <rect x="8" y="22" width="48" height="6" rx="2" fill="#7C3AED" />
              <rect x="28" y="22" width="8" height="32" fill="#7C3AED" />
              <path d="M16 22c-2-4 1-10 6-10s8 6 10 10c2-4 5-10 10-10s8 6 6 10" stroke="#7C3AED" stroke-width="2.5" fill="none" stroke-linecap="round" />
            </svg>
          </div>
          <div class="rewards-body">
            <h3>Recompensas {{ tenantName }}</h3>
            <ul>
              <li>
                <span class="rw-icon">🏆</span>
                <span>Gana beneficios por pago puntual</span>
              </li>
              <li>
                <span class="rw-icon">👥</span>
                <span>Invita amigos y obtén recompensas</span>
              </li>
            </ul>
          </div>
        </section>

        <!-- Tabs y lista -->
        <div class="tabs">
          <button type="button" class="tab" :class="{ 'tab--active': tab === 'all' }" @click="tab = 'all'">Todos</button>
          <button type="button" class="tab" :class="{ 'tab--active': tab === 'active' }" @click="tab = 'active'">Créditos actuales</button>
          <button type="button" class="tab" :class="{ 'tab--active': tab === 'completed' }" @click="tab = 'completed'">Créditos finalizados</button>
        </div>

        <ul v-if="visibleLoans.length > 0" class="loan-list">
          <li v-for="loan in visibleLoans" :key="loan.id" class="loan-row" @click="goToLoanDetail(loan.id)">
            <span class="lr-icon">
              <svg viewBox="0 0 24 24" fill="none">
                <rect x="4" y="5" width="16" height="15" rx="2" stroke="currentColor" stroke-width="1.6" />
                <path d="M4 9h16M8 4v3M16 4v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
              </svg>
            </span>
            <div class="lr-body">
              <div class="lr-title-row">
                <span class="lr-title">Préstamo simple</span>
                <span v-if="loan.status === 'ACTIVE' || loan.status === 'DISBURSED'" class="lr-tag">Activo</span>
              </div>
              <span class="lr-sub">Vence: {{ formatDate(loan.due_date) }}</span>
            </div>
            <div class="lr-right">
              <span class="lr-amount">{{ formatMoney(loan.total_amount) }}</span>
              <svg viewBox="0 0 24 24" fill="none" class="lr-chev">
                <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </div>
          </li>
        </ul>
        <p v-else class="empty-list">No hay préstamos en esta categoría.</p>
      </template>
    </main>

    <MobileBottomNav />
  </div>
</template>

<style scoped>
.home-screen {
  min-height: 100vh;
  min-height: 100dvh;
  background: #f6f3ff;
  padding-bottom: calc(74px + env(safe-area-inset-bottom));
  color: #0f172a;
  display: flex;
  flex-direction: column;
}

/* HEADER MORADO */
.hero-header {
  background: var(--tenant-primary, #371F91);
  color: #ffffff;
  padding: calc(14px + env(safe-area-inset-top)) 18px 24px;
  border-radius: 0 0 28px 28px;
}
.hero-top {
  display: flex; align-items: center; justify-content: space-between;
}
.brand { display: flex; align-items: center; gap: 10px; }
.brand-mark {
  width: 32px; height: 32px;
  display: grid; place-items: center;
  color: var(--tenant-primary, #371F91);
}
.brand-mark img, .brand-mark svg { width: 100%; height: 100%; object-fit: contain; }
.brand-name { font-weight: 700; font-size: 16.5px; }
.hero-actions { display: flex; gap: 6px; }
.icon-btn {
  width: 36px; height: 36px;
  background: rgba(255, 255, 255, 0.14);
  border: none; border-radius: 999px;
  color: #ffffff; cursor: pointer;
  display: grid; place-items: center;
  -webkit-tap-highlight-color: transparent;
}
.icon-btn:active { background: rgba(255, 255, 255, 0.22); }
.icon-btn svg { width: 20px; height: 20px; }

.hero-greeting {
  display: flex; align-items: center; justify-content: space-between;
  margin-top: 14px;
  gap: 12px;
}
.hero-greeting h1 {
  margin: 0;
  font-size: 19px; font-weight: 800;
  letter-spacing: -0.3px;
}
.contract-btn {
  display: inline-flex; align-items: center; gap: 6px;
  background: #ffffff;
  color: var(--tenant-primary, #371F91);
  border: none;
  border-radius: 999px;
  padding: 8px 14px;
  font-weight: 700; font-size: 12.5px;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}
.contract-btn svg { width: 14px; height: 14px; }

/* BODY */
.home-body {
  flex: 1;
  padding: 16px 18px 28px;
  display: flex; flex-direction: column; gap: 14px;
}

.spinner-wrap { display: grid; place-items: center; padding: 60px 0; }
.spin {
  width: 36px; height: 36px;
  border: 3px solid #e2e8f0; border-top-color: var(--tenant-primary, #5B21B6);
  border-radius: 999px;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.card {
  background: #ffffff;
  border-radius: 18px;
  padding: 18px;
  display: flex; flex-direction: column; gap: 10px;
  box-shadow: 0 6px 18px -14px rgba(15, 23, 42, 0.18);
}

/* Processing */
.card--processing { padding: 22px 18px; gap: 12px; }
.illustration { display: grid; place-items: center; color: var(--tenant-primary, #5B21B6); }
.illustration svg { width: 110px; height: 110px; }
.progress-percent {
  text-align: center; font-size: 32px; font-weight: 800;
  color: var(--tenant-primary, #5B21B6); margin: 0; letter-spacing: -0.5px;
}
.progress-bar {
  height: 8px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border-radius: 999px; overflow: hidden;
}
.progress-fill {
  height: 100%;
  background: var(--tenant-primary, #5B21B6);
  border-radius: 999px;
  transition: width 240ms ease;
}
.card-title {
  text-align: center; font-size: 17px; font-weight: 700;
  color: #0f172a; margin: 4px 0 0; line-height: 1.35;
}
.card-sub {
  text-align: center; font-size: 13.5px; color: #475569;
  margin: 0; line-height: 1.5;
}

.checklist {
  list-style: none; padding: 12px; margin: 4px 0 0;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border-radius: 14px; display: flex; flex-direction: column; gap: 10px;
}
.check-item { display: flex; align-items: center; gap: 12px; }
.check-icon { width: 24px; height: 24px; flex-shrink: 0; display: grid; place-items: center; }
.check-icon svg { width: 100%; height: 100%; }
.check-icon--done, .check-icon--progress { color: var(--tenant-primary, #5B21B6); }
.check-icon--pending { color: #cbd5e1; }
.check-body { flex: 1; display: flex; flex-direction: column; }
.check-label { font-size: 13.5px; font-weight: 600; color: #0f172a; }
.check-label--progress { color: var(--tenant-primary, #5B21B6); }
.check-sub { font-size: 11.5px; color: #94a3b8; }
.security {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; border-radius: 12px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: #475569; font-size: 12px; line-height: 1.45;
}
.security-icon { width: 28px; height: 28px; flex-shrink: 0; color: var(--tenant-primary, #5B21B6); }
.security-icon svg { width: 100%; height: 100%; }

/* Offer/Empty/Rejected */
.card--offer, .card--empty, .card--rejected {
  align-items: center; text-align: center; padding: 24px 22px;
}
.state-badge {
  background: rgba(22, 163, 74, 0.1); color: #16a34a;
  font-size: 11.5px; font-weight: 700;
  padding: 5px 12px; border-radius: 999px;
  text-transform: uppercase; letter-spacing: 0.04em;
}

/* LOAN CARD ACTIVO */
.loan-card {
  background: #ffffff;
  border-radius: 22px;
  padding: 18px;
  display: flex; flex-direction: column; gap: 12px;
  box-shadow: 0 6px 18px -14px rgba(15, 23, 42, 0.18);
}
.loan-head { display: flex; align-items: center; gap: 12px; }
.loan-icon {
  width: 44px; height: 44px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border-radius: 12px;
  display: grid; place-items: center;
  color: var(--tenant-primary, #5B21B6);
}
.loan-icon svg { width: 22px; height: 22px; }
.loan-head-body { display: flex; align-items: center; gap: 8px; flex: 1; }
.loan-title { font-weight: 700; font-size: 15px; }
.loan-status {
  background: #DCFCE7; color: #16a34a;
  font-size: 11px; font-weight: 700;
  padding: 3px 10px; border-radius: 999px;
}
.loan-amount-row {
  display: flex; flex-direction: column; align-items: center; gap: 2px;
  padding: 6px 0 4px;
}
.amount-label { font-size: 13px; color: #64748b; }
.amount-value { font-size: 30px; font-weight: 800; color: #0f172a; letter-spacing: -0.6px; }
.loan-meta-row {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
}
.days-pill {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: var(--tenant-primary, #5B21B6);
  font-size: 12.5px; font-weight: 600;
  padding: 7px 14px; border-radius: 999px;
}
.days-pill .dot { width: 8px; height: 8px; border-radius: 999px; background: currentColor; }
.due-text { margin: 0; font-size: 12.5px; color: #94a3b8; text-align: center; }

/* Buttons */
.btn-primary {
  width: 100%;
  background: var(--tenant-primary, #5B21B6);
  color: #ffffff; border: none;
  border-radius: 999px;
  padding: 13px 20px;
  font-weight: 700; font-size: 14.5px;
  cursor: pointer;
  transition: transform 80ms ease;
}
.btn-primary--inline { width: auto; padding: 10px 22px; font-size: 13px; }
.btn-primary:active { transform: scale(0.985); }
.btn-secondary {
  width: 100%;
  background: transparent;
  color: var(--tenant-primary, #5B21B6);
  border: 1.5px solid var(--tenant-primary, #5B21B6);
  border-radius: 999px;
  padding: 12px 20px;
  font-weight: 700; font-size: 14px;
  cursor: pointer;
}

/* EXTENSION */
.extension-card { gap: 12px; }
.ext-head { display: flex; align-items: center; gap: 12px; }
.ext-icon {
  width: 38px; height: 38px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border-radius: 999px;
  display: grid; place-items: center;
  color: var(--tenant-primary, #5B21B6);
}
.ext-icon svg { width: 20px; height: 20px; }
.ext-head h3 { margin: 0; font-size: 14.5px; font-weight: 700; color: #0f172a; }
.ext-head p { margin: 2px 0 0; font-size: 12.5px; color: #64748b; }
.ext-options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.ext-opt {
  background: #ffffff;
  border: 1.5px solid var(--tenant-primary, #5B21B6);
  color: var(--tenant-primary, #5B21B6);
  border-radius: 999px;
  padding: 11px 16px;
  font-weight: 700; font-size: 13.5px;
  cursor: pointer;
}
.ext-opt--active {
  background: var(--tenant-primary, #5B21B6);
  color: #ffffff;
}
.ext-info {
  display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
  padding: 4px 0;
}
.ext-info > div { display: flex; flex-direction: column; gap: 2px; }
.ext-info-label { font-size: 11.5px; color: #94a3b8; }
.ext-info strong { font-size: 13.5px; font-weight: 700; color: #0f172a; }
.ext-foot {
  margin: 0; text-align: center; font-size: 11.5px; color: #94a3b8;
}

/* REWARDS */
.rewards-card {
  background: linear-gradient(135deg, #EDE9FE 0%, #DDD6FE 100%);
  flex-direction: row; align-items: center; gap: 14px;
}
.rewards-illust { width: 70px; height: 70px; flex-shrink: 0; }
.rewards-illust svg { width: 100%; height: 100%; }
.rewards-body { flex: 1; }
.rewards-body h3 {
  margin: 0 0 6px;
  font-size: 14px; font-weight: 800; color: var(--tenant-primary, #5B21B6);
}
.rewards-body ul {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-direction: column; gap: 4px;
}
.rewards-body li { display: flex; align-items: center; gap: 8px; font-size: 11.5px; color: #475569; line-height: 1.35; }
.rw-icon { font-size: 14px; flex-shrink: 0; }

/* TABS */
.tabs {
  display: flex; gap: 8px;
  margin-top: 6px;
  padding: 2px;
  overflow-x: auto;
  scrollbar-width: none;
}
.tabs::-webkit-scrollbar { display: none; }
.tab {
  flex-shrink: 0;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  padding: 8px 16px;
  font-size: 12.5px; font-weight: 600;
  color: #64748b;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}
.tab--active {
  background: var(--tenant-primary, #5B21B6);
  color: #ffffff;
  border-color: var(--tenant-primary, #5B21B6);
}

/* LOAN LIST */
.loan-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.loan-row {
  background: #ffffff;
  border-radius: 14px;
  padding: 12px 14px;
  display: flex; align-items: center; gap: 12px;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}
.lr-icon {
  width: 38px; height: 38px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border-radius: 10px;
  display: grid; place-items: center;
  color: var(--tenant-primary, #5B21B6);
  flex-shrink: 0;
}
.lr-icon svg { width: 20px; height: 20px; }
.lr-body { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.lr-title-row { display: flex; align-items: center; gap: 8px; }
.lr-title { font-weight: 700; font-size: 13.5px; color: #0f172a; }
.lr-tag {
  background: #DCFCE7; color: #16a34a;
  font-size: 10.5px; font-weight: 700;
  padding: 2px 8px; border-radius: 999px;
}
.lr-sub { font-size: 11.5px; color: #94a3b8; }
.lr-right { display: flex; align-items: center; gap: 4px; }
.lr-amount { font-weight: 700; font-size: 14px; color: #0f172a; }
.lr-chev { width: 16px; height: 16px; color: #94a3b8; }
.empty-list { text-align: center; font-size: 13px; color: #94a3b8; margin: 12px 0; }
</style>
