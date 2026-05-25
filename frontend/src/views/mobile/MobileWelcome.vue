<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTenantStore } from '@/stores/tenant'
import { useAuthStore } from '@/stores/auth'
import { useApplicationStore } from '@/stores/application'
import { detectTenantSlug } from '@/utils/tenant'
import SimulatorCard from '@/components/simulator/SimulatorCard.vue'
import { logger } from '@/utils/logger'

const log = logger.child('MobileWelcome')

const router = useRouter()
const tenantStore = useTenantStore()
const authStore = useAuthStore()
const applicationStore = useApplicationStore()

const isLoading = ref(true)
const tenantSlug = ref<string>('')
const selectedProductIndex = ref(0)

onMounted(async () => {
  tenantSlug.value = detectTenantSlug() || 'demo'
  try {
    if (!tenantStore.isLoaded) {
      await tenantStore.loadConfig()
    }
    // Aplicar el theme del tenant para que SimulatorCard use los primary colors.
    tenantStore.applyTheme()
    // Pre-selecciona el primer producto activo en el applicationStore para que
    // SimulatorCard use sus reglas.
    if (!applicationStore.selectedProduct && tenantStore.activeProducts.length > 0) {
      applicationStore.setSelectedProduct(tenantStore.activeProducts[0])
    }
  } catch (e) {
    log.error('No se pudo cargar la configuración del tenant', { error: e })
  } finally {
    isLoading.value = false
  }
})

const tenantName = computed(() => tenantStore.tenant?.name || 'LendusFind')
const products = computed(() => tenantStore.activeProducts ?? [])
const activeProduct = computed(() => products.value[selectedProductIndex.value] ?? null)

function pickProduct(index: number) {
  selectedProductIndex.value = index
  if (products.value[index]) {
    applicationStore.setSelectedProduct(products.value[index])
  }
}

function buildRoute(suffix: string): string {
  return tenantSlug.value ? `/${tenantSlug.value}/${suffix}` : `/${suffix}`
}

async function goToLogin() {
  await router.push(buildRoute('auth'))
}
</script>

<template>
  <div class="mobile-screen">
    <!-- HERO compacto -->
    <header class="hero">
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
        <button class="link-login" type="button" @click="goToLogin">Ingresar</button>
      </div>
      <h1 class="hero-title">Solicita tu crédito</h1>
      <p class="hero-sub">Elige cuánto necesitas y paga como te acomode.</p>
    </header>

    <!-- SELECTOR DE PRODUCTO (chips horizontales) -->
    <section v-if="products.length > 1" class="product-chips" aria-label="Selecciona el producto">
      <button
        v-for="(p, i) in products"
        :key="p.id"
        type="button"
        :class="['chip', { 'chip-active': i === selectedProductIndex }]"
        @click="pickProduct(i)"
      >
        {{ p.name }}
      </button>
    </section>

    <!-- SIMULADOR -->
    <section class="simulator-wrap">
      <div v-if="isLoading" class="skeleton">
        <div class="sk-row" style="width: 50%;"></div>
        <div class="sk-row" style="height: 28px;"></div>
        <div class="sk-row" style="width: 80%;"></div>
        <div class="sk-row" style="height: 60px;"></div>
      </div>
      <SimulatorCard
        v-else-if="activeProduct"
        :product="activeProduct"
        :compact="true"
      />
      <div v-else class="placeholder">
        <p>Tu institución aún no publica productos.</p>
      </div>
    </section>
  </div>
</template>

<style scoped>
.mobile-screen {
  min-height: 100vh;
  min-height: 100dvh;
  background: #f8fafc;
  padding-top: env(safe-area-inset-top);
  padding-bottom: calc(20px + env(safe-area-inset-bottom));
}

/* === HERO === */
.hero {
  background: linear-gradient(135deg, var(--tenant-primary, #1e40af), color-mix(in srgb, var(--tenant-primary, #1e40af) 60%, black));
  color: #fff;
  padding: 20px 20px 28px;
  border-radius: 0 0 24px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.brand { display: flex; align-items: center; gap: 10px; }
.brand-logo { height: 32px; width: auto; border-radius: 8px; background: #fff; padding: 4px; }
.brand-mark {
  width: 32px; height: 32px;
  border-radius: 9px;
  background: rgba(255, 255, 255, 0.18);
  font-weight: 700;
  display: grid; place-items: center;
  font-size: 13px;
}
.brand-name { font-weight: 600; font-size: 14px; opacity: 0.95; flex: 1; }
.link-login {
  background: transparent; border: none; color: #fff;
  font-size: 14px; font-weight: 500;
  text-decoration: underline; cursor: pointer;
  padding: 4px 8px;
}
.link-login:active { opacity: 0.7; }
.hero-title { font-size: 26px; font-weight: 700; line-height: 1.15; margin: 0; letter-spacing: -0.3px; }
.hero-sub { font-size: 14px; opacity: 0.9; margin: 0; }

/* === CHIPS === */
.product-chips {
  margin: 14px 0 0;
  padding: 0 20px;
  display: flex; gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
}
.product-chips::-webkit-scrollbar { display: none; }
.chip {
  flex-shrink: 0;
  padding: 8px 14px;
  border-radius: 999px;
  background: #fff;
  border: 1px solid #e2e8f0;
  color: #475569;
  font-size: 13px; font-weight: 500;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}
.chip:active { transform: scale(0.97); }
.chip-active {
  background: var(--tenant-primary, #1e40af);
  border-color: var(--tenant-primary, #1e40af);
  color: #fff;
}

/* === SIMULADOR === */
.simulator-wrap {
  margin: 16px;
}
/* El SimulatorCard ya trae su propio fondo blanco y sombras. */
.simulator-wrap :deep(.bg-white) {
  border-radius: 18px;
  box-shadow: 0 6px 24px rgba(15, 23, 42, 0.08);
}

/* === PLACEHOLDERS === */
.skeleton {
  background: #fff;
  border-radius: 18px;
  padding: 24px;
  display: flex; flex-direction: column; gap: 12px;
  box-shadow: 0 6px 24px rgba(15, 23, 42, 0.08);
}
.skeleton .sk-row {
  height: 14px; border-radius: 6px;
  background: linear-gradient(90deg, #e2e8f0, #f1f5f9, #e2e8f0);
  background-size: 200% 100%;
  animation: sk 1.4s infinite;
}
@keyframes sk { 0% { background-position: 0 0; } 100% { background-position: -200% 0; } }

.placeholder {
  background: #fff;
  border-radius: 18px;
  padding: 28px;
  text-align: center;
  color: #475569;
}
</style>
