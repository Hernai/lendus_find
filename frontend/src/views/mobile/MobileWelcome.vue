<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { detectTenantSlug } from '@/utils/tenant'
import { platform } from '@/platform'
import { logger } from '@/utils/logger'

const log = logger.child('MobileWelcome')

const router = useRouter()
const tenantStore = useTenantStore()
const authStore = useAuthStore()

const isLoading = ref(true)
const tenantSlug = ref<string>('')

onMounted(async () => {
  tenantSlug.value = detectTenantSlug() || 'demo'
  try {
    if (!tenantStore.isLoaded) {
      await tenantStore.loadConfig()
    }
  } catch (e) {
    log.error('No se pudo cargar la configuración del tenant', { error: e })
  } finally {
    isLoading.value = false
  }
})

const tenantName = computed(() => tenantStore.tenant?.name || 'LendusFind')

const featuredProduct = computed(() => {
  const products = tenantStore.activeProducts ?? []
  return products[0] ?? null
})

const minAmount = computed(
  () =>
    featuredProduct.value?.min_amount ??
    featuredProduct.value?.rules?.min_amount ??
    null,
)
const maxAmount = computed(
  () =>
    featuredProduct.value?.max_amount ??
    featuredProduct.value?.rules?.max_amount ??
    null,
)

const amountRange = computed(() => {
  if (minAmount.value && maxAmount.value) {
    return `${formatCompact(Number(minAmount.value))} – ${formatCompact(Number(maxAmount.value))}`
  }
  if (maxAmount.value) return `Hasta ${formatCompact(Number(maxAmount.value))}`
  return null
})

const minTerm = computed(
  () =>
    featuredProduct.value?.min_term_months ??
    featuredProduct.value?.rules?.min_term_months ??
    null,
)
const maxTerm = computed(
  () =>
    featuredProduct.value?.max_term_months ??
    featuredProduct.value?.rules?.max_term_months ??
    null,
)

const annualRate = computed(
  () =>
    featuredProduct.value?.interest_rate_annual ??
    featuredProduct.value?.rules?.annual_rate ??
    featuredProduct.value?.interest_rate ??
    null,
)

function formatCompact(n: number): string {
  if (n >= 1_000_000) return `$${(n / 1_000_000).toFixed(n % 1_000_000 === 0 ? 0 : 1)}M`
  if (n >= 1000) return `$${(n / 1000).toFixed(0)}K`
  return `$${n.toFixed(0)}`
}

function buildRoute(suffix: string): string {
  const slug = tenantSlug.value
  return slug ? `/${slug}/${suffix}` : `/${suffix}`
}

async function startApplication() {
  // Si ya está autenticado, va directo al onboarding; si no, al login.
  if (authStore.isAuthenticated) {
    await router.push(buildRoute('solicitud/simulador'))
  } else {
    await router.push(buildRoute('auth'))
  }
}

async function goToLogin() {
  await router.push(buildRoute('auth'))
}
</script>

<template>
  <div class="mobile-screen">
    <!-- HERO -->
    <header class="hero" :class="{ 'is-loading': isLoading }">
      <div class="hero-inner">
        <div class="brand">
          <img
            v-if="tenantStore.tenant?.branding?.logo_url"
            :src="tenantStore.tenant.branding.logo_url"
            :alt="tenantName"
            class="brand-logo"
          />
          <div v-else class="brand-mark">
            {{ tenantName.slice(0, 2).toUpperCase() }}
          </div>
          <span class="brand-name">{{ tenantName }}</span>
        </div>
        <h1 class="hero-title">Tu crédito,<br />en pocos pasos</h1>
        <p class="hero-sub">
          Solicita 100% en línea. Sin filas, sin papeleo.
        </p>
      </div>
    </header>

    <!-- PRODUCTO DESTACADO -->
    <section v-if="featuredProduct" class="card product">
      <div class="product-head">
        <span class="product-pill">Producto destacado</span>
        <h2 class="product-name">{{ featuredProduct.name }}</h2>
        <p v-if="featuredProduct.description" class="product-desc">
          {{ featuredProduct.description }}
        </p>
      </div>
      <dl class="product-stats">
        <div v-if="amountRange">
          <dt>Monto</dt>
          <dd>{{ amountRange }}</dd>
        </div>
        <div v-if="minTerm && maxTerm">
          <dt>Plazo</dt>
          <dd>{{ minTerm }}–{{ maxTerm }} meses</dd>
        </div>
        <div v-if="annualRate" class="stat-rate">
          <dt>Tasa anual</dt>
          <dd>{{ Number(annualRate).toFixed(0) }}%</dd>
        </div>
      </dl>
    </section>

    <!-- LOADING SKELETON -->
    <section v-else-if="isLoading" class="card skeleton">
      <div class="sk-pill"></div>
      <div class="sk-title"></div>
      <div class="sk-row"></div>
      <div class="sk-row"></div>
    </section>

    <!-- FALLBACK SI NO HAY PRODUCTO -->
    <section v-else class="card placeholder">
      <p>Tu institución aún no publica productos.</p>
      <p class="placeholder-sub">Avanza y te ayudamos a empezar.</p>
    </section>

    <!-- CTAs FIJOS ABAJO -->
    <footer class="cta-stack">
      <button class="btn btn-primary" type="button" @click="startApplication">
        Solicitar crédito
      </button>
      <button class="btn btn-ghost" type="button" @click="goToLogin">
        Ya tengo cuenta
      </button>
    </footer>
  </div>
</template>

<style scoped>
.mobile-screen {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-height: 100dvh;
  background: #f8fafc;
  padding-top: env(safe-area-inset-top);
  padding-bottom: env(safe-area-inset-bottom);
}

.hero {
  background: linear-gradient(135deg, var(--tenant-primary, #1e40af), color-mix(in srgb, var(--tenant-primary, #1e40af) 60%, black));
  color: #fff;
  padding: 24px 24px 36px;
  border-radius: 0 0 28px 28px;
}
.hero-inner { display: flex; flex-direction: column; gap: 18px; }
.brand { display: flex; align-items: center; gap: 12px; }
.brand-logo { height: 36px; width: auto; border-radius: 8px; background: #fff; padding: 4px; }
.brand-mark {
  width: 36px; height: 36px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.18);
  font-weight: 700;
  display: grid; place-items: center;
  font-size: 14px;
  letter-spacing: 0.5px;
}
.brand-name { font-weight: 600; font-size: 15px; opacity: 0.95; }
.hero-title { font-size: 32px; font-weight: 700; line-height: 1.1; margin: 0; letter-spacing: -0.5px; }
.hero-sub { font-size: 15px; opacity: 0.9; margin: 0; }

.card {
  margin: -20px 16px 0;
  background: #fff;
  border-radius: 20px;
  padding: 20px;
  box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
}

.product-head { display: flex; flex-direction: column; gap: 8px; }
.product-pill {
  align-self: flex-start;
  font-size: 11px; font-weight: 600;
  background: color-mix(in srgb, var(--tenant-primary, #1e40af) 12%, transparent);
  color: var(--tenant-primary, #1e40af);
  padding: 4px 10px; border-radius: 999px;
  letter-spacing: 0.3px; text-transform: uppercase;
}
.product-name { font-size: 22px; font-weight: 700; margin: 0; color: #0f172a; }
.product-desc { font-size: 14px; color: #475569; margin: 0; line-height: 1.4; }

.product-stats {
  margin: 18px 0 0;
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px 18px;
}
.product-stats > div { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.product-stats > .stat-rate { grid-column: span 2; }
.product-stats dt { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.4px; }
.product-stats dd { margin: 0; font-weight: 600; color: #0f172a; font-size: 15px; }

.placeholder { display: flex; flex-direction: column; gap: 6px; align-items: center; text-align: center; padding: 32px 20px; color: #475569; }
.placeholder-sub { font-size: 13px; color: #94a3b8; }

.skeleton { display: flex; flex-direction: column; gap: 10px; }
.skeleton > div { border-radius: 8px; background: linear-gradient(90deg, #e2e8f0, #f1f5f9, #e2e8f0); background-size: 200% 100%; animation: sk 1.4s infinite; }
.sk-pill { height: 18px; width: 110px; }
.sk-title { height: 26px; width: 70%; margin-top: 6px; }
.sk-row { height: 14px; width: 100%; }
@keyframes sk { 0% { background-position: 0 0; } 100% { background-position: -200% 0; } }

.cta-stack {
  margin-top: auto;
  padding: 16px 20px calc(20px + env(safe-area-inset-bottom));
  display: flex; flex-direction: column; gap: 10px;
}
.btn {
  border: none;
  border-radius: 14px;
  padding: 16px 18px;
  font-size: 16px; font-weight: 600;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  transition: transform 80ms ease, opacity 120ms ease;
}
.btn:active { transform: scale(0.98); opacity: 0.92; }
.btn-primary {
  background: var(--tenant-primary, #1e40af);
  color: #fff;
  box-shadow: 0 8px 20px color-mix(in srgb, var(--tenant-primary, #1e40af) 35%, transparent);
}
.btn-ghost {
  background: transparent;
  color: #0f172a;
  border: 1px solid #e2e8f0;
}
</style>
