<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTenantStore } from '@/stores/tenant'
import { detectTenantSlug } from '@/utils/tenant'
import { platform } from '@/platform'
import { storage, STORAGE_KEYS } from '@/utils/storage'
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

async function handleContinue() {
  if (!accepted.value || isRequestingPermissions.value) return
  isRequestingPermissions.value = true
  try {
    // Pedir permisos en orden — cada uno muestra su prompt del sistema.
    if (platform.push.isSupported()) {
      await platform.push.requestPermission()
    }
    if (platform.geolocation.isSupported()) {
      const state = await platform.geolocation.requestPermission()
      if (state === 'granted' || state === 'prompt') {
        // Disparar getCurrent para que aparezca el prompt en web
        await platform.geolocation.getCurrent({ cacheMs: 60_000, timeoutMs: 4_000 })
      }
    }
    // Cámara: capabilities check (no muestra prompt hasta usarse).
    await platform.camera.isAvailable()

    // Persistir consents — se leen al iniciar la app para no volver a pedir.
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

  // Navegar al login
  const slug = tenantSlug.value
  await router.replace(slug ? `/${slug}/auth` : '/auth')
}
</script>

<template>
  <div class="wc-screen">
    <!-- Brand header -->
    <header class="brand-bar">
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
    </header>

    <main class="wc-main">
      <h1 class="welcome-title">Bienvenido a {{ tenantName }}</h1>
      <p class="welcome-sub">
        Para continuar, acepta los permisos y autorizaciones iniciales necesarios para tu solicitud.
      </p>

      <!-- Permisos y aceptación inicial -->
      <section class="consent-card">
        <div class="consent-header">
          <span class="consent-icon">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
          </span>
          <h3>Permisos y aceptación inicial</h3>
        </div>
        <p class="consent-body">
          Al continuar, autorizas los permisos y validaciones necesarias para procesar tu solicitud:
          notificaciones, cámara/fotos, ubicación y validación de datos, conforme a nuestro Aviso
          de privacidad y Términos y condiciones.
        </p>
        <ul class="consent-links">
          <li>
            <button type="button" class="link" @click="showPrivacy = true">
              Aviso de privacidad
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </button>
          </li>
          <li>
            <button type="button" class="link" @click="showTerms = true">
              Términos y condiciones
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </button>
          </li>
        </ul>
        <label class="consent-checkbox">
          <input v-model="accepted" type="checkbox" />
          <span>He leído y acepto para continuar</span>
        </label>
      </section>

      <!-- Requisitos básicos -->
      <section class="requirements-card">
        <div class="req-header">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <h3>Requisitos básicos</h3>
        </div>
        <ul class="req-list">
          <li>Ser mayor de 18 años</li>
          <li>Ser mexicano y residir en México</li>
          <li>Contar con INE / IFE vigente</li>
          <li>Tener cuenta bancaria a tu nombre</li>
        </ul>
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
  min-height: 100vh; min-height: 100dvh;
  background: #f8fafc;
  padding-top: env(safe-area-inset-top);
  padding-bottom: calc(20px + env(safe-area-inset-bottom));
  display: flex; flex-direction: column;
}

.brand-bar {
  background: var(--tenant-primary, #5B21B6);
  color: #fff;
  padding: 18px 20px;
}
.brand { display: flex; align-items: center; gap: 10px; }
.brand-logo { height: 36px; width: auto; border-radius: 8px; background: #fff; padding: 4px; }
.brand-mark {
  width: 36px; height: 36px; border-radius: 10px;
  background: rgba(255, 255, 255, 0.18);
  font-weight: 700; display: grid; place-items: center;
  font-size: 14px;
}
.brand-name { font-weight: 600; font-size: 15px; }

.wc-main { flex: 1; padding: 24px 20px; display: flex; flex-direction: column; gap: 20px; }

.welcome-title { font-size: 24px; font-weight: 700; color: #0f172a; margin: 0; letter-spacing: -0.3px; }
.welcome-sub { font-size: 14px; color: #64748b; margin: 0; line-height: 1.5; }

.consent-card, .requirements-card {
  background: #fff;
  border-radius: 16px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
  display: flex; flex-direction: column; gap: 12px;
}
.consent-header, .req-header { display: flex; align-items: center; gap: 8px; color: var(--tenant-primary, #5B21B6); }
.consent-header h3, .req-header h3 { font-size: 14px; font-weight: 700; color: #0f172a; margin: 0; }
.consent-body { font-size: 13px; color: #475569; line-height: 1.5; margin: 0; }
.consent-links { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px; }
.consent-links .link {
  display: flex; justify-content: space-between; align-items: center;
  width: 100%; background: transparent; border: none;
  padding: 10px 4px; font-size: 13px; color: #0f172a; cursor: pointer;
}
.consent-checkbox { display: flex; align-items: center; gap: 10px; padding-top: 6px; font-size: 13px; color: #0f172a; cursor: pointer; }
.consent-checkbox input { width: 18px; height: 18px; accent-color: var(--tenant-primary, #5B21B6); }

.req-list { list-style: disc; padding-left: 20px; margin: 0; font-size: 13px; color: #475569; line-height: 1.7; }

.wc-footer { padding: 12px 20px 0; }
.btn-continue {
  width: 100%;
  background: var(--tenant-primary, #5B21B6);
  color: #fff; border: none;
  border-radius: 14px;
  padding: 16px;
  font-size: 16px; font-weight: 600;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  transition: opacity 120ms ease;
}
.btn-continue:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-continue:not(:disabled):active { opacity: 0.92; }

.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.5);
  display: grid; place-items: end center;
  z-index: 50;
  animation: fadeIn 140ms ease;
}
.modal {
  background: #fff;
  width: 100%; max-width: 480px;
  max-height: 85vh;
  border-radius: 20px 20px 0 0;
  display: flex; flex-direction: column;
  animation: slideUp 200ms ease;
}
.modal header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; }
.modal header h2 { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; }
.modal header button { background: transparent; border: none; font-size: 18px; color: #64748b; cursor: pointer; }
.modal-body { padding: 16px 20px; overflow-y: auto; font-size: 14px; color: #475569; line-height: 1.6; display: flex; flex-direction: column; gap: 12px; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
</style>
