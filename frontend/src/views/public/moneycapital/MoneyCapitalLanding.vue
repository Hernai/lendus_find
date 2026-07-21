<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTenantStore, useApplicationStore } from '@/stores'
import { getTenantBasePath } from '@/utils/tenant'
import AppHeader from '@/components/layout/AppHeader.vue'
import AppFooter from '@/components/layout/AppFooter.vue'

const router = useRouter()
const tenantStore = useTenantStore()
const applicationStore = useApplicationStore()

// '/:tenant' (path-based) o '' (subdomain) — evita prefix redundante.
const goToAuth = () => {
  // Si el tenant tiene >1 producto y el usuario no eligió uno todavía,
  // mandarlo al simulador (que muestra grid de selección + simulación).
  // Saltarse esto deja `selectedProduct=null` y confunde al onboarding —
  // ver AuthPinSetupView.handlePinSubmit. Con 1 solo producto lo
  // auto-seleccionamos y vamos directo a auth.
  const products = tenantStore.activeProducts
  if (!applicationStore.selectedProduct) {
    if (products.length === 1) {
      applicationStore.setSelectedProduct(products[0]!)
    } else if (products.length > 1) {
      router.push(`${getTenantBasePath()}/simulador`)
      return
    }
  }
  router.push(`${getTenantBasePath()}/auth`)
}

const goToSimulator = () => {
  router.push(`${getTenantBasePath()}/simulador`)
}

onMounted(async () => {
  await tenantStore.loadConfig()
  tenantStore.applyTheme()
})
</script>

<template>
  <div class="mc-landing">
    <AppHeader />

    <main>
      <!-- HERO con split: copy + tarjeta de simulación visual -->
      <section class="hero">
        <div class="hero-grid">
          <div class="hero-copy">
            <span class="eyebrow">Crédito personal en minutos</span>
            <h1>
              Liquidez inmediata,<br />
              <span class="accent">sin papeleo y sin buró</span>
            </h1>
            <p class="lead">
              Solicita hasta <strong>$15,000 MXN</strong> 100% digital. Aprobación
              en menos de 5 minutos y depósito el mismo día a cualquier banco de
              México.
            </p>
            <div class="cta-row">
              <button type="button" class="btn-primary" @click="goToAuth">
                Solicitar ahora
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </button>
              <button type="button" class="btn-ghost" @click="goToSimulator">
                Calcular mi pago
              </button>
            </div>
            <!-- Descarga de la app: la solicitud se completa en la app móvil. -->
            <div class="store-row">
              <a class="store-badge" href="https://play.google.com/store/apps/details?id=mx.moneycapital.app" target="_blank" rel="noopener">Descarga en Google Play</a>
              <a class="store-badge" href="https://apps.apple.com/mx/app/moneycapital" target="_blank" rel="noopener">Descarga en App Store</a>
            </div>
            <div class="trust-row">
              <span class="trust-item">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Datos protegidos
              </span>
              <span class="trust-item">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6" />
                  <path d="M12 7v6l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                </svg>
                Depósito el mismo día
              </span>
            </div>
          </div>

          <!-- Card decorativa estilo simulador -->
          <aside class="hero-card" aria-hidden="true">
            <div class="card-glow"></div>
            <div class="card">
              <div class="card-row">
                <span class="card-label">Monto solicitado</span>
                <span class="card-value">$10,000</span>
              </div>
              <div class="card-slider">
                <div class="card-slider-fill"></div>
              </div>
              <div class="card-mini">
                <div>
                  <span class="card-label">Plazo</span>
                  <strong>10 días</strong>
                </div>
                <div>
                  <span class="card-label">Pago único</span>
                  <strong>$10,360</strong>
                </div>
              </div>
              <div class="card-cta">
                Solicitar
              </div>
            </div>
          </aside>
        </div>
      </section>

      <!-- BENEFICIOS -->
      <section class="benefits">
        <header class="section-header">
          <span class="eyebrow">¿Por qué MoneyCapital?</span>
          <h2>Pensado para gente real, no para algoritmos</h2>
        </header>
        <div class="benefits-grid">
          <article class="benefit">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
              </svg>
            </div>
            <h3>Aprobación inmediata</h3>
            <p>Respuesta en menos de 5 minutos. Sin filas, sin sucursales, sin esperar días.</p>
          </article>
          <article class="benefit">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
              </svg>
            </div>
            <h3>Sin consultar buró</h3>
            <p>Evaluamos tu capacidad real de pago. Tu historial no decide tu futuro.</p>
          </article>
          <article class="benefit">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <rect x="6" y="3" width="12" height="18" rx="2" stroke="currentColor" stroke-width="1.6" />
                <circle cx="12" cy="17.5" r="1" fill="currentColor" />
              </svg>
            </div>
            <h3>100% digital</h3>
            <p>Solicita, firma y recibe el dinero sin moverte de casa. Todo desde tu celular.</p>
          </article>
          <article class="benefit">
            <div class="benefit-icon">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M3 12l4 4L21 4 M3 20l4-4 11-11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </div>
            <h3>Sin letra chica</h3>
            <p>Tasa, comisión y total a pagar visible desde el primer paso. Cero sorpresas.</p>
          </article>
        </div>
      </section>

      <!-- PASOS -->
      <section class="steps">
        <header class="section-header">
          <span class="eyebrow">Cómo funciona</span>
          <h2>Tu crédito en 4 pasos simples</h2>
        </header>
        <ol class="steps-list">
          <li>
            <span class="step-num">1</span>
            <div>
              <h4>Simula tu crédito</h4>
              <p>Elige monto y plazo. Ve tu pago al instante.</p>
            </div>
          </li>
          <li>
            <span class="step-num">2</span>
            <div>
              <h4>Crea tu cuenta</h4>
              <p>Verifica tu celular en 30 segundos.</p>
            </div>
          </li>
          <li>
            <span class="step-num">3</span>
            <div>
              <h4>Valida tu identidad</h4>
              <p>Foto de tu INE y selfie. Sin documentos adicionales.</p>
            </div>
          </li>
          <li>
            <span class="step-num">4</span>
            <div>
              <h4>Recibe tu dinero</h4>
              <p>Depósito directo a tu cuenta el mismo día.</p>
            </div>
          </li>
        </ol>
      </section>

      <!-- CTA FINAL -->
      <section class="final-cta">
        <div class="final-cta-card">
          <h2>¿Listo para resolver tu liquidez hoy?</h2>
          <p>Toma menos de 5 minutos. Sin compromiso, sin costos ocultos.</p>
          <button type="button" class="btn-primary btn-primary--inverse" @click="goToAuth">
            Comenzar mi solicitud
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
          <p class="finepoint">Regulado por la CNBV · SOFOM E.N.R.</p>
        </div>
      </section>
    </main>

    <AppFooter />
  </div>
</template>

<style scoped>
.mc-landing {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background: #ffffff;
  color: #0f172a;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

main { flex: 1; }

/* Tokens locales — uso el primary morado del tenant via CSS var con fallback */
.mc-landing {
  --mc-primary: var(--tenant-primary, #5B21B6);
  --mc-primary-dark: #371F91;
  --mc-bg-soft: #F5F3FF;
  --mc-ink: #0F172A;
  --mc-ink-soft: #475569;
}

.eyebrow {
  display: inline-block;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--mc-primary);
  background: var(--mc-bg-soft);
  padding: 6px 12px;
  border-radius: 999px;
  margin-bottom: 16px;
}

/* HERO */
.hero {
  padding: 48px 24px 64px;
  background: linear-gradient(180deg, var(--mc-bg-soft) 0%, #ffffff 100%);
}
.hero-grid {
  max-width: 1120px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr;
  gap: 48px;
  align-items: center;
}
@media (min-width: 960px) {
  .hero-grid { grid-template-columns: 1.1fr 0.9fr; gap: 64px; }
}
.hero-copy h1 {
  font-size: clamp(32px, 5vw, 52px);
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.02em;
  margin: 0 0 20px;
  color: var(--mc-ink);
}
.hero-copy h1 .accent { color: var(--mc-primary); }
.hero-copy .lead {
  font-size: 18px;
  line-height: 1.6;
  color: var(--mc-ink-soft);
  margin: 0 0 28px;
  max-width: 540px;
}
.hero-copy .lead strong { color: var(--mc-ink); font-weight: 700; }

.cta-row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}
.store-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 28px;
}
.store-badge {
  display: inline-flex;
  align-items: center;
  padding: 10px 16px;
  border: 1.5px solid rgba(255, 255, 255, 0.35);
  border-radius: 10px;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  transition: background 140ms ease;
}
.store-badge:hover {
  background: rgba(255, 255, 255, 0.12);
}
.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 24px;
  background: var(--mc-primary);
  color: #fff;
  border: none;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
  box-shadow: 0 4px 12px -2px rgba(91, 33, 182, 0.4);
}
.btn-primary:hover { background: var(--mc-primary-dark); transform: translateY(-1px); box-shadow: 0 8px 20px -4px rgba(91, 33, 182, 0.5); }
.btn-primary svg { width: 18px; height: 18px; }
.btn-primary--inverse { background: #fff; color: var(--mc-primary); box-shadow: 0 4px 12px -2px rgba(0,0,0,0.15); }
.btn-primary--inverse:hover { background: var(--mc-bg-soft); }

.btn-ghost {
  display: inline-flex;
  align-items: center;
  padding: 14px 24px;
  background: transparent;
  color: var(--mc-primary);
  border: 1.5px solid #e5e7eb;
  border-radius: 12px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.btn-ghost:hover { border-color: var(--mc-primary); background: var(--mc-bg-soft); }

.trust-row {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  font-size: 13px;
  color: var(--mc-ink-soft);
}
.trust-item { display: inline-flex; align-items: center; gap: 6px; }
.trust-item svg { width: 16px; height: 16px; color: var(--mc-primary); flex-shrink: 0; }

/* HERO card decorativa */
.hero-card {
  position: relative;
  display: none;
}
@media (min-width: 960px) {
  .hero-card { display: block; }
}
.card-glow {
  position: absolute;
  inset: -40px;
  background: radial-gradient(circle at 60% 40%, rgba(91, 33, 182, 0.25), transparent 60%);
  filter: blur(40px);
  z-index: 0;
}
.card {
  position: relative;
  background: #fff;
  border-radius: 24px;
  padding: 28px;
  box-shadow: 0 20px 60px -20px rgba(15, 23, 42, 0.25), 0 0 0 1px rgba(15, 23, 42, 0.04);
  display: flex;
  flex-direction: column;
  gap: 18px;
  z-index: 1;
}
.card-row { display: flex; justify-content: space-between; align-items: baseline; }
.card-label { font-size: 12px; color: var(--mc-ink-soft); display: block; margin-bottom: 4px; }
.card-value { font-size: 28px; font-weight: 800; color: var(--mc-primary); }
.card-slider {
  height: 8px;
  background: var(--mc-bg-soft);
  border-radius: 999px;
  overflow: hidden;
}
.card-slider-fill {
  width: 65%;
  height: 100%;
  background: linear-gradient(90deg, var(--mc-primary), var(--mc-primary-dark));
}
.card-mini { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.card-mini strong { font-size: 18px; color: var(--mc-ink); font-weight: 700; }
.card-cta {
  text-align: center;
  padding: 12px;
  background: var(--mc-primary);
  color: #fff;
  border-radius: 12px;
  font-weight: 600;
  font-size: 14px;
  margin-top: 4px;
}

/* SECTION HEADER común */
.section-header {
  max-width: 720px;
  margin: 0 auto 40px;
  text-align: center;
}
.section-header h2 {
  font-size: clamp(26px, 3.5vw, 36px);
  font-weight: 700;
  letter-spacing: -0.02em;
  margin: 0;
  color: var(--mc-ink);
}

/* BENEFICIOS */
.benefits { padding: 72px 24px; background: #fff; }
.benefits-grid {
  max-width: 1120px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}
@media (min-width: 640px) { .benefits-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1024px) { .benefits-grid { grid-template-columns: repeat(4, 1fr); } }
.benefit {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  padding: 24px;
  transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s;
}
.benefit:hover {
  border-color: var(--mc-primary);
  transform: translateY(-2px);
  box-shadow: 0 10px 25px -10px rgba(91, 33, 182, 0.25);
}
.benefit-icon {
  width: 44px;
  height: 44px;
  background: var(--mc-bg-soft);
  border-radius: 12px;
  display: grid;
  place-items: center;
  margin-bottom: 16px;
  color: var(--mc-primary);
}
.benefit-icon svg { width: 22px; height: 22px; }
.benefit h3 { font-size: 17px; font-weight: 700; margin: 0 0 6px; color: var(--mc-ink); }
.benefit p { font-size: 14px; line-height: 1.55; color: var(--mc-ink-soft); margin: 0; }

/* PASOS */
.steps { padding: 72px 24px; background: var(--mc-bg-soft); }
.steps-list {
  max-width: 880px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
  list-style: none;
  padding: 0;
}
@media (min-width: 768px) { .steps-list { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1024px) { .steps-list { grid-template-columns: repeat(4, 1fr); gap: 20px; } }
.steps-list li {
  display: flex;
  gap: 16px;
  align-items: flex-start;
  background: #fff;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 4px 12px -4px rgba(15, 23, 42, 0.08);
}
.step-num {
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  background: var(--mc-primary);
  color: #fff;
  border-radius: 12px;
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: 16px;
}
.steps-list h4 { font-size: 15px; font-weight: 700; margin: 0 0 4px; color: var(--mc-ink); }
.steps-list p { font-size: 13px; line-height: 1.5; color: var(--mc-ink-soft); margin: 0; }

/* CTA FINAL */
.final-cta { padding: 72px 24px; }
.final-cta-card {
  max-width: 880px;
  margin: 0 auto;
  background: linear-gradient(135deg, var(--mc-primary) 0%, var(--mc-primary-dark) 100%);
  border-radius: 24px;
  padding: 56px 32px;
  text-align: center;
  color: #fff;
  box-shadow: 0 20px 50px -20px rgba(91, 33, 182, 0.5);
}
.final-cta-card h2 {
  font-size: clamp(24px, 3vw, 32px);
  font-weight: 700;
  letter-spacing: -0.02em;
  margin: 0 0 12px;
  color: #fff;
}
.final-cta-card p { font-size: 16px; opacity: 0.9; margin: 0 0 28px; }
.final-cta-card .finepoint { font-size: 12px; opacity: 0.7; margin: 20px 0 0; }
</style>
