import type { RouteRecordRaw } from 'vue-router'

// ==============================================
// MOBILE VIEWS (Capacitor native)
// ==============================================
const MobileWelcome = () => import('@/views/mobile/MobileWelcome.vue')
const WelcomeConsentView = () => import('@/views/mobile/WelcomeConsentView.vue')
const DynamicOnboardingView = () => import('@/views/applicant/onboarding/DynamicOnboardingView.vue')
const ProcessingView = () => import('@/views/applicant/onboarding/ProcessingView.vue')
const MobileHomeView = () => import('@/views/mobile/MobileHomeView.vue')
const LoanOfferView = () => import('@/views/applicant/loans/LoanOfferView.vue')
const LoanDashboardView = () => import('@/views/applicant/loans/LoanDashboardView.vue')
const LoanDetailView = () => import('@/views/applicant/loans/LoanDetailView.vue')
const ProfileView = () => import('@/views/applicant/dashboard/ProfileView.vue')

/**
 * Rutas del entry-point móvil nativo (Capacitor), prefijo `/m`.
 * El guard global (router/index.ts) desvía la landing pública a `/m` en nativo.
 */
export const mobileRoutes: RouteRecordRaw[] = [
  // MOBILE WELCOME (entry-point en Capacitor native)
  {
    path: '/m',
    name: 'mobile-welcome',
    component: MobileWelcome,
    meta: { public: true, mobileEntry: true },
  },
  // Welcome con consentimiento unificado (MoneyCapital). El guard global
  // redirige aquí si tenant.features.unified_consent_screen es true y no
  // hay consents persistidos todavía.
  {
    path: '/m/welcome-consent',
    name: 'mobile-welcome-consent',
    component: WelcomeConsentView,
    meta: { public: true, mobileEntry: true },
  },
  // Onboarding dinámico (configurable por producto, ej. MoneyCapital).
  // Requiere autenticación y un selectedProduct previo.
  {
    path: '/m/solicitud/:stepId?',
    name: 'm-onboarding-step',
    component: DynamicOnboardingView,
    meta: { requiresAuth: true, mobileEntry: true },
  },
  {
    path: '/m/home',
    name: 'm-home',
    component: MobileHomeView,
    meta: { requiresAuth: true, mobileEntry: true },
  },
  // Mi Perfil en el canal móvil: reutiliza ProfileView (misma vista que web).
  {
    path: '/m/perfil',
    name: 'm-profile',
    component: ProfileView,
    meta: { requiresAuth: true, mobileEntry: true },
  },
  {
    path: '/m/solicitud/:id/procesando',
    name: 'm-processing',
    component: ProcessingView,
    meta: { requiresAuth: true, mobileEntry: true },
  },
  {
    path: '/m/solicitud/:id/oferta',
    name: 'm-loan-offer',
    component: LoanOfferView,
    meta: { requiresAuth: true, mobileEntry: true },
  },
  {
    path: '/m/prestamos',
    name: 'm-loan-dashboard',
    component: LoanDashboardView,
    meta: { requiresAuth: true, mobileEntry: true, requiresLoanPortfolio: true },
  },
  {
    path: '/m/prestamos/:id',
    name: 'm-loan-detail',
    component: LoanDetailView,
    meta: { requiresAuth: true, mobileEntry: true, requiresLoanPortfolio: true },
  },
]
