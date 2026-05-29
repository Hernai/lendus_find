<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTenantStore } from '@/stores/tenant'
import { detectTenantSlug } from '@/utils/tenant'
import { platform } from '@/platform'
import { storage } from '@/utils/storage'
import { logger } from '@/utils/logger'

/**
 * Pantalla unificada de bienvenida + permisos + aceptación.
 *
 * Sustituye al MobileWelcome cuando el tenant tiene
 * `features.unified_consent_screen = true` (ej. MoneyCapital).
 *
 * En una sola pantalla el usuario:
 *  - Lee el aviso de privacidad y T&C (links abren modales).
 *  - Acepta el checkbox.
 *  - Tap "Continuar" → la app pide permisos en batch (notificaciones,
 *    cámara, ubicación) y luego navega al login.
 *
 * Los consents se guardan en localStorage para no volver a pedirlos.
 *
 * Visual alineado al mock pantalla 1 de MoneyCapital: header blanco con
 * marca, cards con badges circulares lavanda, card de requisitos con
 * fondo lavanda. Usa `var(--tenant-primary)` y `rgb(var(--primary-N-rgb))`
 * para respetar el branding de cualquier tenant que active el flag.
 */

const log = logger.child('WelcomeConsent')
const router = useRouter()
const tenantStore = useTenantStore()

const accepted = ref(false)
const showPrivacy = ref(false)
const showTerms = ref(false)
const isRequestingPermissions = ref(false)
const tenantSlug = ref<string>('')

onMounted(async () => {
  tenantSlug.value = detectTenantSlug() || 'moneycapital'
  if (!tenantStore.isLoaded) await tenantStore.loadConfig()
  tenantStore.applyTheme()
})

const tenantName = computed(() => tenantStore.tenant?.name || 'MoneyCapital')
const supportEmail = computed(() => tenantStore.tenant?.contact?.email || 'contacto@moneycapital.mx')
const brandLogoUrl = computed(() => tenantStore.tenant?.branding?.logo_url || '')

async function handleContinue() {
  if (!accepted.value || isRequestingPermissions.value) return
  isRequestingPermissions.value = true
  try {
    if (platform.push.isSupported()) {
      await platform.push.requestPermission()
    }
    if (platform.geolocation.isSupported()) {
      const state = await platform.geolocation.requestPermission()
      if (state === 'granted' || state === 'prompt') {
        await platform.geolocation.getCurrent({ cacheMs: 60_000, timeoutMs: 4_000 })
      }
    }
    await platform.camera.isAvailable()

    await storage.set('consents', {
      terms_accepted_at: new Date().toISOString(),
      privacy_accepted_at: new Date().toISOString(),
      permissions_granted_at: new Date().toISOString(),
      tenant_slug: tenantSlug.value,
    })
  } catch (e) {
    log.warn('Permisos parciales', { error: e })
  } finally {
    isRequestingPermissions.value = false
  }

  const slug = tenantSlug.value
  await router.replace(slug ? `/${slug}/auth` : '/auth')
}
</script>

<template>
  <div class="wc-screen">
    <!-- Brand header (blanco, logo + nombre en color tenant) -->
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

    <main class="wc-main">
      <h1 class="welcome-title">Bienvenido a {{ tenantName }}</h1>
      <p class="welcome-sub">
        Para continuar, acepta los permisos y autorizaciones iniciales necesarios para tu solicitud.
      </p>

      <!-- Permisos y aceptación inicial -->
      <section class="card consent-card">
        <div class="card-row">
          <span class="badge badge-lg badge-shield" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M12 2.5c2.5 1.6 5 2.4 7.5 2.4v6.6c0 4.6-3.2 8.4-7.5 9.5-4.3-1.1-7.5-4.9-7.5-9.5V4.9c2.5 0 5-.8 7.5-2.4z" fill="white" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
              <path d="M8 12.2l2.6 2.6 5.2-5.2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
          <div class="card-body">
            <h3 class="card-title">Permisos y aceptación inicial</h3>
            <p class="card-text">
              Al continuar, autorizas los permisos y validaciones necesarias para procesar
              tu solicitud, incluyendo notificaciones, cámara/fotos, ubicación y validación
              de datos, conforme a nuestro Aviso de privacidad y Términos y condiciones.
            </p>
          </div>
        </div>

        <ul class="link-list">
          <li>
            <button type="button" class="link-row" @click="showPrivacy = true">
              <span class="badge badge-sm" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                  <path d="M6 3h9l4 4v13a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                  <path d="M15 3v4h4" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                  <path d="M12 11l3 1v2.2c0 1.6-1.3 3-3 3.3-1.7-.3-3-1.7-3-3.3V12l3-1z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" />
                  <path d="M10.7 14l1 1 1.6-1.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </span>
              <span class="link-label">Aviso de privacidad</span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
          </li>
          <li>
            <button type="button" class="link-row" @click="showTerms = true">
              <span class="badge badge-sm" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                  <path d="M6 3h9l4 4v13a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                  <path d="M15 3v4h4" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                  <path d="M8 12h7M8 15h7M8 18h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" />
                </svg>
              </span>
              <span class="link-label">Términos y condiciones</span>
              <svg class="chevron" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
          </li>
        </ul>

        <label class="consent-checkbox">
          <input v-model="accepted" type="checkbox" />
          <span class="checkbox-box" aria-hidden="true">
            <svg v-if="accepted" viewBox="0 0 24 24" fill="none">
              <path d="M5 12l5 5L20 7" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
          <span class="checkbox-label">He leído y acepto para continuar</span>
        </label>
      </section>

      <!-- Requisitos básicos (card con fondo lavanda) -->
      <section class="card requirements-card">
        <div class="card-row">
          <span class="badge badge-lg" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <rect x="5" y="4.5" width="14" height="17" rx="2.5" stroke="currentColor" stroke-width="1.6" />
              <rect x="8.5" y="2.5" width="7" height="4" rx="1.2" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
              <path d="M8.5 11h7M8.5 14h7M8.5 17h4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            </svg>
          </span>
          <div class="card-body">
            <h3 class="card-title">Requisitos básicos</h3>
            <ul class="req-list">
              <li>Ser mayor de 18 años</li>
              <li>Ser mexicano y residir en México</li>
              <li>Contar con INE / IFE vigente</li>
              <li>Tener cuenta bancaria a tu nombre</li>
            </ul>
          </div>
        </div>
      </section>
    </main>

    <footer class="wc-footer">
      <button
        type="button"
        class="btn-continue"
        :disabled="!accepted || isRequestingPermissions"
        @click="handleContinue"
      >
        {{ isRequestingPermissions ? 'Solicitando permisos…' : 'Continuar' }}
      </button>
    </footer>

    <!-- Modal Aviso de privacidad -->
    <div v-if="showPrivacy" class="modal-overlay" @click.self="showPrivacy = false">
      <div class="modal">
        <header><h2>Aviso de Privacidad</h2><button @click="showPrivacy = false">✕</button></header>
        <div class="modal-body">
          <p>
            {{ tenantName }} recopila y procesa tus datos personales para evaluar tu solicitud de
            crédito, validar tu identidad y dar seguimiento a tu cuenta. Tus datos están protegidos
            conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares.
          </p>
          <p>
            Compartimos información con burós de crédito y autoridades cuando es legalmente requerido.
            Para ejercer tus derechos ARCO, escribe a <strong>{{ supportEmail }}</strong>.
          </p>
        </div>
      </div>
    </div>

    <!-- Modal T&C -->
    <div v-if="showTerms" class="modal-overlay" @click.self="showTerms = false">
      <div class="modal">
        <header><h2>Términos y Condiciones</h2><button @click="showTerms = false">✕</button></header>
        <div class="modal-body">
          <p>
            Al usar {{ tenantName }} aceptas que evaluamos tu perfil con tecnología, validación de
            identidad (INE/CURP/RFC) y análisis de comportamiento. El uso de la app implica el
            consentimiento electrónico para la firma de contratos y comunicaciones digitales.
          </p>
          <p>
            Las ofertas están sujetas a evaluación. El CAT, comisiones e intereses se informan
            antes de aceptar cualquier oferta de crédito.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wc-screen {
  min-height: 100vh;
  min-height: 100dvh;
  background: #ffffff;
  padding-top: env(safe-area-inset-top);
  padding-bottom: calc(20px + env(safe-area-inset-bottom));
  display: flex;
  flex-direction: column;
  color: #0f172a;
}

/* Header: blanco con marca a la izquierda */
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
  letter-spacing: -0.2px;
  color: var(--tenant-primary, #5B21B6);
}

.wc-main {
  flex: 1;
  padding: 8px 20px 12px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.welcome-title {
  font-size: 25px;
  font-weight: 700;
  color: #0f172a;
  margin: 2px 0 0;
  letter-spacing: -0.4px;
  line-height: 1.15;
}
.welcome-sub {
  font-size: 14px;
  color: #475569;
  margin: 0;
  line-height: 1.5;
}

/* Cards */
.card {
  border-radius: 16px;
  padding: 14px 14px;
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 10px rgba(15, 23, 42, 0.04);
}
.consent-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.requirements-card {
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  box-shadow: none;
  border: 1px solid rgb(var(--surface-soft-border-rgb, 232 229 243) / 1);
}
.requirements-card .card-title {
  color: var(--tenant-primary, #5B21B6);
  font-weight: 600;
}

.card-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.card-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}
.card-title {
  font-size: 14.5px;
  font-weight: 700;
  color: #0f172a;
  margin: 2px 0 0;
}
.card-text {
  font-size: 13px;
  color: #475569;
  line-height: 1.5;
  margin: 0;
}

/* Badges circulares lavanda con icono morado */
.badge {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  color: var(--tenant-primary, #5B21B6);
  border-radius: 999px;
}
.badge-lg {
  width: 46px;
  height: 46px;
}
.badge-lg svg {
  width: 26px;
  height: 26px;
}
.badge-sm {
  width: 22px;
  height: 22px;
  background: transparent;
  border-radius: 0;
}
.badge-sm svg {
  width: 22px;
  height: 22px;
}

/* Links de privacidad / T&C */
.link-list {
  list-style: none;
  padding: 0;
  margin: 0;
  border-top: 1px solid #f1f5f9;
}
.link-list li + li {
  border-top: 1px solid #f1f5f9;
}
.link-row {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 4px;
  background: transparent;
  border: none;
  cursor: pointer;
  color: #0f172a;
  -webkit-tap-highlight-color: transparent;
}
.link-label {
  flex: 1;
  text-align: left;
  font-size: 14.5px;
  font-weight: 600;
  color: var(--tenant-primary, #5B21B6);
}
.chevron {
  width: 18px;
  height: 18px;
  color: var(--tenant-primary, #5B21B6);
  flex-shrink: 0;
}

/* Checkbox custom */
.consent-checkbox {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  padding-top: 2px;
  font-size: 13.5px;
  color: #0f172a;
}
.consent-checkbox input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}
.checkbox-box {
  width: 22px;
  height: 22px;
  border-radius: 7px;
  border: 2px solid rgb(var(--primary-200-rgb, 221 214 254) / 1);
  background: #ffffff;
  display: grid;
  place-items: center;
  transition: background 120ms ease, border-color 120ms ease;
}
.checkbox-box svg {
  width: 14px;
  height: 14px;
}
.consent-checkbox input:checked + .checkbox-box {
  background: var(--tenant-primary, #5B21B6);
  border-color: var(--tenant-primary, #5B21B6);
}
.checkbox-label {
  font-weight: 500;
}

/* Lista de requisitos: bullets con color tenant */
.req-list {
  list-style: none;
  padding: 0;
  margin: 2px 0 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.req-list li {
  position: relative;
  padding-left: 14px;
  font-size: 13px;
  color: #313133;
  line-height: 1.45;
}
.req-list li::before {
  content: '•';
  position: absolute;
  left: 2px;
  top: 0;
  color: var(--tenant-primary, #5B21B6);
  font-weight: 800;
}

/* Footer botón */
.wc-footer {
  padding: 8px 20px 0;
}
.btn-continue {
  width: 100%;
  background: var(--tenant-primary, #5B21B6);
  color: #ffffff;
  border: none;
  border-radius: 16px;
  padding: 15px;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  transition: opacity 120ms ease, transform 120ms ease;
  box-shadow: 0 8px 18px -8px rgb(var(--primary-500-rgb, 139 92 246) / 0.55);
}
.btn-continue:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  box-shadow: none;
}
.btn-continue:not(:disabled):active {
  transform: translateY(1px);
  opacity: 0.94;
}

/* Modales */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: grid;
  place-items: end center;
  z-index: 50;
  animation: fadeIn 140ms ease;
}
.modal {
  background: #ffffff;
  width: 100%;
  max-width: 480px;
  max-height: 85vh;
  border-radius: 22px 22px 0 0;
  display: flex;
  flex-direction: column;
  animation: slideUp 200ms ease;
}
.modal header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid #e2e8f0;
}
.modal header h2 {
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
  margin: 0;
}
.modal header button {
  background: transparent;
  border: none;
  font-size: 18px;
  color: #64748b;
  cursor: pointer;
}
.modal-body {
  padding: 16px 20px;
  overflow-y: auto;
  font-size: 14px;
  color: #475569;
  line-height: 1.6;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
</style>
