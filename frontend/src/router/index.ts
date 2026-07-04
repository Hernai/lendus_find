import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useTenantStore } from '@/stores/tenant'
import { detectTenantSlug } from '@/utils/tenant'
import { storage, STORAGE_KEYS } from '@/utils/storage'
import { platform } from '@/platform'
import { mobileRoutes } from './routes/mobile'
import { adminRoutes } from './routes/admin'

// ==============================================
// PUBLIC VIEWS (no authentication required)
// ==============================================
const LandingView = () => import('@/views/public/LandingView.vue')
const TenantLandingDispatcher = () => import('@/views/public/TenantLandingDispatcher.vue')
const SimulatorView = () => import('@/views/public/SimulatorView.vue')
const LendusFindLanding = () => import('@/views/public/LendusFindLanding.vue')

// ==============================================
// APPLICANT VIEWS (solicitantes de crédito)
// ==============================================

// Applicant Auth (OTP, PIN)
const AuthMethodView = () => import('@/views/applicant/auth/AuthMethodView.vue')
const AuthPhoneView = () => import('@/views/applicant/auth/AuthPhoneView.vue')
const AuthEmailView = () => import('@/views/applicant/auth/AuthEmailView.vue')
const AuthOtpView = () => import('@/views/applicant/auth/AuthOtpView.vue')
const AuthPinSetupView = () => import('@/views/applicant/auth/AuthPinSetupView.vue')
const AuthPinLoginView = () => import('@/views/applicant/auth/AuthPinLoginView.vue')

// Applicant Onboarding (9-step wizard: simulator + KYC + 7 data steps)
const OnboardingLayout = () => import('@/views/applicant/onboarding/OnboardingLayout.vue')
const Step0Simulator = () => import('@/views/applicant/onboarding/Step0Simulator.vue')
const StepKycVerification = () => import('@/views/applicant/onboarding/StepKycVerification.vue')
const Step1PersonalData = () => import('@/views/applicant/onboarding/Step1PersonalData.vue')
const Step2Identification = () => import('@/views/applicant/onboarding/Step2Identification.vue')
const Step3Address = () => import('@/views/applicant/onboarding/Step3Address.vue')
const Step4Employment = () => import('@/views/applicant/onboarding/Step4Employment.vue')
const Step5LoanDetails = () => import('@/views/applicant/onboarding/Step5LoanDetails.vue')
const Step6Documents = () => import('@/views/applicant/onboarding/Step6Documents.vue')
const Step7References = () => import('@/views/applicant/onboarding/Step7References.vue')
const Step8Review = () => import('@/views/applicant/onboarding/Step8Review.vue')

// Applicant Dashboard
const DashboardView = () => import('@/views/applicant/dashboard/DashboardView.vue')
const ApplicationStatusView = () => import('@/views/applicant/dashboard/ApplicationStatusView.vue')
const DocumentsUploadView = () => import('@/views/applicant/dashboard/DocumentsUploadView.vue')
const DataCorrectionsView = () => import('@/views/applicant/dashboard/DataCorrectionsView.vue')
const ProfileView = () => import('@/views/applicant/dashboard/ProfileView.vue')
const NotificationsView = () => import('@/views/applicant/dashboard/NotificationsView.vue')

// Reserved paths that are NOT tenant slugs (must match utils/tenant.ts)
const RESERVED_PATHS = ['auth', 'admin', 'solicitud', 'dashboard', 'simulador', 'perfil', 'correcciones', 'find', 'notificaciones', 'm', 'mobile', 'sin-buro', 'liquidez-urgente']

// Helper to check if a path segment is a tenant slug
const isTenantSlug = (segment: string): boolean => {
  return !!segment && !RESERVED_PATHS.includes(segment.toLowerCase())
}

const routes: RouteRecordRaw[] = [
  // Rutas del entry-point móvil nativo (/m/*) — ver ./routes/mobile.ts
  ...mobileRoutes,

  // ==============================================
  // TENANT-PREFIXED ROUTES (e.g., /demo/simulador)
  // These must come BEFORE the non-prefixed routes
  // ==============================================
  {
    path: '/:tenant',
    name: 'tenant-landing',
    component: TenantLandingDispatcher,
    meta: { public: true },
    beforeEnter: (to, _from, next) => {
      // Only allow if it's a valid tenant slug
      if (isTenantSlug(to.params.tenant as string)) {
        next()
      } else {
        next('/')
      }
    }
  },
  {
    path: '/:tenant/simulador',
    name: 'tenant-simulator',
    component: SimulatorView,
    meta: { public: true }
  },
  {
    path: '/:tenant/auth',
    name: 'tenant-auth',
    component: AuthMethodView,
    meta: { public: true, guest: true }
  },
  {
    path: '/:tenant/auth/phone',
    name: 'tenant-auth-phone',
    component: AuthPhoneView,
    meta: { public: true, guest: true }
  },
  {
    path: '/:tenant/auth/email',
    name: 'tenant-auth-email',
    component: AuthEmailView,
    meta: { public: true, guest: true }
  },
  {
    path: '/:tenant/auth/verify',
    name: 'tenant-auth-otp',
    component: AuthOtpView,
    meta: { public: true, guest: true }
  },
  {
    path: '/:tenant/auth/pin/setup',
    name: 'tenant-auth-pin-setup',
    component: AuthPinSetupView,
    meta: { requiresAuth: true }
  },
  {
    path: '/:tenant/auth/pin/login',
    name: 'tenant-auth-pin-login',
    component: AuthPinLoginView,
    meta: { public: true, guest: true }
  },
  {
    path: '/:tenant/solicitud',
    component: OnboardingLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: (to) => `/${to.params.tenant}/solicitud/simulador` },
      { path: 'simulador', name: 'tenant-onboarding-simulator', component: Step0Simulator, meta: { step: 0, title: 'Simulador de crédito' } },
      { path: 'verificacion', name: 'tenant-onboarding-kyc', component: StepKycVerification, meta: { step: 1, title: 'Verificación de identidad' } },
      { path: 'paso-1', name: 'tenant-onboarding-step-1', component: Step1PersonalData, meta: { step: 2, title: '¿Cómo te llamas?' } },
      { path: 'paso-2', name: 'tenant-onboarding-step-2', component: Step2Identification, meta: { step: 3, title: 'Tu identificación' } },
      { path: 'paso-3', name: 'tenant-onboarding-step-3', component: Step3Address, meta: { step: 4, title: '¿Dónde vives?' } },
      { path: 'paso-4', name: 'tenant-onboarding-step-4', component: Step4Employment, meta: { step: 5, title: '¿A qué te dedicas?' } },
      { path: 'paso-5', name: 'tenant-onboarding-step-5', component: Step5LoanDetails, meta: { step: 6, title: 'Tu crédito' } },
      { path: 'paso-6', name: 'tenant-onboarding-step-6', component: Step6Documents, meta: { step: 7, title: 'Documentos' } },
      { path: 'paso-7', name: 'tenant-onboarding-step-7', component: Step7References, meta: { step: 8, title: 'Referencias' } },
      { path: 'paso-8', name: 'tenant-onboarding-step-8', component: Step8Review, meta: { step: 9, title: 'Revisión y firma' } }
    ]
  },
  {
    path: '/:tenant/dashboard',
    name: 'tenant-dashboard',
    component: DashboardView,
    meta: { requiresAuth: true }
  },
  {
    path: '/:tenant/solicitud/:id/estado',
    name: 'tenant-application-status',
    component: ApplicationStatusView,
    meta: { requiresAuth: true }
  },
  {
    path: '/:tenant/solicitud/:id/documentos',
    name: 'tenant-application-documents',
    component: DocumentsUploadView,
    meta: { requiresAuth: true }
  },
  {
    path: '/:tenant/correcciones',
    name: 'tenant-data-corrections',
    component: DataCorrectionsView,
    meta: { requiresAuth: true }
  },
  {
    path: '/:tenant/perfil',
    name: 'tenant-profile',
    component: ProfileView,
    meta: { requiresAuth: true }
  },
  {
    path: '/:tenant/notificaciones',
    name: 'tenant-notifications',
    component: NotificationsView,
    meta: { requiresAuth: true }
  },

  // ==============================================
  // NON-PREFIXED ROUTES (default tenant from env/subdomain)
  // ==============================================

  // LendusFind landing (no tenant required)
  {
    path: '/find',
    name: 'lendusfind-landing',
    component: LendusFindLanding,
    meta: { public: true, noTenant: true }
  },

  // Landings comerciales MoneyCapital (no requieren tenant prefix)
  {
    path: '/moneycapital/sin-buro',
    name: 'moneycapital-sin-buro',
    component: () => import('@/views/public/moneycapital/SinBuroLanding.vue'),
    meta: { public: true, noTenant: true },
  },
  {
    path: '/moneycapital/liquidez',
    name: 'moneycapital-liquidez',
    component: () => import('@/views/public/moneycapital/LiquidezUrgenteLanding.vue'),
    meta: { public: true, noTenant: true },
  },

  // Public routes
  {
    path: '/',
    name: 'landing',
    // Dispatcher resuelve el componente real según el subdominio (producción)
    // o el tenant detectado. Si no hay tenant → LandingView genérica.
    component: TenantLandingDispatcher,
    meta: { public: true }
  },
  {
    path: '/simulador',
    name: 'simulator',
    component: SimulatorView,
    meta: { public: true }
  },

  // Auth routes
  {
    path: '/auth',
    name: 'auth',
    component: AuthMethodView,
    meta: { public: true, guest: true }
  },
  {
    path: '/auth/phone',
    name: 'auth-phone',
    component: AuthPhoneView,
    meta: { public: true, guest: true }
  },
  {
    path: '/auth/email',
    name: 'auth-email',
    component: AuthEmailView,
    meta: { public: true, guest: true }
  },
  {
    path: '/auth/verify',
    name: 'auth-otp',
    component: AuthOtpView,
    meta: { public: true, guest: true }
  },
  {
    path: '/auth/pin/setup',
    name: 'auth-pin-setup',
    component: AuthPinSetupView,
    meta: { requiresAuth: true }
  },
  {
    path: '/auth/pin/login',
    name: 'auth-pin-login',
    component: AuthPinLoginView,
    meta: { public: true, guest: true }
  },

  // Onboarding wizard (protected)
  {
    path: '/solicitud',
    component: OnboardingLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/solicitud/simulador'
      },
      {
        path: 'simulador',
        name: 'onboarding-simulator',
        component: Step0Simulator,
        meta: { step: 0, title: 'Simulador de crédito' }
      },
      {
        path: 'verificacion',
        name: 'onboarding-kyc',
        component: StepKycVerification,
        meta: { step: 1, title: 'Verificación de identidad' }
      },
      {
        path: 'paso-1',
        name: 'onboarding-step-1',
        component: Step1PersonalData,
        meta: { step: 2, title: '¿Cómo te llamas?' }
      },
      {
        path: 'paso-2',
        name: 'onboarding-step-2',
        component: Step2Identification,
        meta: { step: 3, title: 'Tu identificación' }
      },
      {
        path: 'paso-3',
        name: 'onboarding-step-3',
        component: Step3Address,
        meta: { step: 4, title: '¿Dónde vives?' }
      },
      {
        path: 'paso-4',
        name: 'onboarding-step-4',
        component: Step4Employment,
        meta: { step: 5, title: '¿A qué te dedicas?' }
      },
      {
        path: 'paso-5',
        name: 'onboarding-step-5',
        component: Step5LoanDetails,
        meta: { step: 6, title: 'Tu crédito' }
      },
      {
        path: 'paso-6',
        name: 'onboarding-step-6',
        component: Step6Documents,
        meta: { step: 7, title: 'Documentos' }
      },
      {
        path: 'paso-7',
        name: 'onboarding-step-7',
        component: Step7References,
        meta: { step: 8, title: 'Referencias' }
      },
      {
        path: 'paso-8',
        name: 'onboarding-step-8',
        component: Step8Review,
        meta: { step: 9, title: 'Revisión y firma' }
      }
    ]
  },

  // Dashboard (protected)
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardView,
    meta: { requiresAuth: true }
  },
  {
    path: '/solicitud/:id/estado',
    name: 'application-status',
    component: ApplicationStatusView,
    meta: { requiresAuth: true }
  },
  {
    path: '/solicitud/:id/documentos',
    name: 'application-documents',
    component: DocumentsUploadView,
    meta: { requiresAuth: true }
  },
  {
    path: '/correcciones',
    name: 'data-corrections',
    component: DataCorrectionsView,
    meta: { requiresAuth: true }
  },
  {
    path: '/perfil',
    name: 'profile',
    component: ProfileView,
    meta: { requiresAuth: true }
  },
  {
    path: '/notificaciones',
    name: 'notifications',
    component: NotificationsView,
    meta: { requiresAuth: true }
  },
  {
    path: '/notificaciones/preferencias',
    name: 'notification-preferences',
    component: () => import('../views/applicant/NotificationPreferences.vue'),
    meta: { requiresAuth: true }
  },

  // Rutas del panel admin (/admin/*) — ver ./routes/admin.ts
  ...adminRoutes,

  // Catch-all redirect
  {
    path: '/:pathMatch(.*)*',
    redirect: '/'
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    }
    return { top: 0 }
  }
})

// Navigation guards
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  const tenantStore = useTenantStore()

  // ==============================================
  // NATIVE MOBILE: redirigir landing pública a /m
  // ==============================================
  // En Capacitor la app no debe abrir en la landing de marketing (LendusFind o
  // landing del tenant). Se desvía al entry-point móvil donde el usuario ve
  // directo el CTA "Solicita tu crédito" con el producto destacado.
  // Si ya está autenticado, va al dashboard del aplicante.
  if (platform.device.isNative() && !to.meta.mobileEntry) {
    const isMarketingLanding =
      to.path === '/' ||
      to.name === 'find-landing' ||
      to.name === 'tenant-landing'
    if (isMarketingLanding) {
      if (authStore.isAuthenticated) {
        const slug = tenantStore.slug || detectTenantSlug() || 'demo'
        return next({ path: `/${slug}/dashboard`, replace: true })
      }
      // Si el tenant tiene `unified_auth_screen` (MoneyCapital y similares),
      // saltamos cualquier pantalla intermedia y aterrizamos directo en el
      // unified auth (registro / login). Si además tiene `unified_consent_screen`
      // sin consents firmados aún, vamos al welcome-consent.
      try {
        await tenantStore.loadConfig()
      } catch {
        // ignore — sigue al fallback /m
      }
      const features = (tenantStore.tenant?.features ?? {}) as Record<string, boolean>
      const hasConsents = !!storage.get('consents')
      // 1) Si el tenant pide consentimiento unificado y aún no se firmó,
      //    mostrarlo primero (es la pantalla de entrada en cold-start).
      if (features.unified_consent_screen && !hasConsents) {
        return next({ path: '/m/welcome-consent', replace: true })
      }
      // 2) Tras consents, si tiene auth unificada, ir directo al login/registro.
      if (features.unified_auth_screen) {
        const slug = tenantStore.slug || detectTenantSlug() || 'demo'
        return next({ path: `/${slug}/auth`, replace: true })
      }
      return next({ path: '/m', replace: true })
    }
  }

  // Load tenant config on navigation (for public/applicant routes)
  // Skip for admin routes and noTenant routes (like /find)
  const isAdminRoute = to.path.startsWith('/admin')
  const isNoTenantRoute = to.matched.some(record => record.meta.noTenant)
  if (!isAdminRoute && !isNoTenantRoute) {
    await tenantStore.loadConfig()
  }

  // Redirect non-prefixed routes to tenant-prefixed versions
  // This handles hardcoded paths like /solicitud/paso-1 -> /demo/solicitud/paso-1
  // IMPORTANT: Only redirect if there's a tenant detected in the current URL
  const currentTenantSlug = detectTenantSlug()
  if (!isAdminRoute && !to.params.tenant && currentTenantSlug && tenantStore.slug) {
    const nonPrefixedPaths = ['/auth', '/solicitud', '/dashboard', '/correcciones', '/perfil', '/simulador', '/notificaciones']
    const matchingPath = nonPrefixedPaths.find(p => to.path.startsWith(p) || to.path === p)
    if (matchingPath) {
      const newPath = `/${tenantStore.slug}${to.path}`
      return next({ path: newPath, query: to.query, replace: true })
    }
    // Also handle root path - but only if a tenant is in the URL
    if (to.path === '/' && currentTenantSlug) {
      return next({ path: `/${tenantStore.slug}`, replace: true })
    }
  }

  // Feature flag: loan_portfolio (módulo opt-in MoneyCapital)
  if (to.matched.some(record => record.meta.requiresLoanPortfolio)) {
    const features = (tenantStore.tenant?.features ?? {}) as Record<string, boolean>
    if (!features.loan_portfolio) {
      return next({ path: '/dashboard', replace: true })
    }
  }

  // Check if route requires authentication
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth)
  const requiresStaff = to.matched.some(record => record.meta.requiresStaff)
  const isGuestOnly = to.matched.some(record => record.meta.guest)
  const isAdminGuest = to.matched.some(record => record.meta.adminGuest)

  // DEV MODE: Auto-authenticate for development
  // This allows navigating directly to any step without logging in
  // DISABLED for admin routes to test real login flow
  // SECURITY: This code only runs in DEV mode (import.meta.env.DEV is compile-time constant)
  if (import.meta.env.DEV && requiresAuth && !requiresStaff) {
    if (!storage.get(STORAGE_KEYS.AUTH_TOKEN)) {
      storage.set(STORAGE_KEYS.AUTH_TOKEN, 'dev-token-' + Date.now())
    }
    // Always re-check to update role based on current route context (admin vs user)
    await authStore.checkAuth(to.path)
  }

  // If authenticated staff trying to access admin-guest page (admin login)
  if (isAdminGuest && authStore.isAuthenticated && authStore.isStaff) {
    return next({ name: 'admin-dashboard' })
  }

  // Usuario autenticado que intenta entrar a una página guest-only (ej. login)
  if (isGuestOnly && !isAdminGuest && authStore.isAuthenticated) {
    const tenantSlug = to.params.tenant as string || tenantStore.slug

    // Si trae producto/simulación de la landing, lo mandamos a la verificación
    // del onboarding en vez del dashboard.
    const savedProduct = storage.get(STORAGE_KEYS.SELECTED_PRODUCT)
    const savedSimulation = storage.get(STORAGE_KEYS.SIMULATION)

    if (savedProduct || savedSimulation) {
      if (tenantSlug) {
        return next({ path: `/${tenantSlug}/solicitud/verificacion` })
      }
      return next({ path: '/solicitud/verificacion' })
    }

    // Sin producto guardado: al dashboard como siempre.
    if (tenantSlug) {
      return next({ name: 'tenant-dashboard', params: { tenant: tenantSlug } })
    }
    return next({ name: 'dashboard' })
  }

  // If route requires auth
  if (requiresAuth) {
    // Check if user is authenticated
    if (!authStore.isAuthenticated) {
      // Try to check auth status (e.g., validate stored token)
      const isValid = await authStore.checkAuth()
      if (!isValid) {
        // Redirect to admin login if trying to access admin routes
        if (requiresStaff) {
          return next({ name: 'admin-login', query: { redirect: to.fullPath } })
        }
        // Use tenant-prefixed route if tenant is available
        const tenantSlug = to.params.tenant as string || tenantStore.slug
        if (tenantSlug) {
          return next({ name: 'tenant-auth', params: { tenant: tenantSlug }, query: { redirect: to.fullPath } })
        }
        return next({ name: 'auth', query: { redirect: to.fullPath } })
      }
    }

    // Check if user needs to setup PIN (for applicants only, not staff).
    // Tenants white-label con `unified_auth_screen` activan PIN como
    // configuración opcional desde el perfil — no como bloqueo post-OTP.
    const tenantFeatures = (tenantStore.tenant?.features ?? {}) as Record<string, boolean>
    const skipForcedPinSetup = !!tenantFeatures.unified_auth_screen
    if (
      !requiresStaff &&
      !skipForcedPinSetup &&
      authStore.needsPinSetup &&
      to.name !== 'auth-pin-setup' &&
      to.name !== 'tenant-auth-pin-setup'
    ) {
      const tenantSlug = to.params.tenant as string || tenantStore.slug
      if (tenantSlug) {
        return next({ name: 'tenant-auth-pin-setup', params: { tenant: tenantSlug }, query: { redirect: to.fullPath } })
      }
      return next({ name: 'auth-pin-setup', query: { redirect: to.fullPath } })
    }

    // Check staff requirement (agents, analysts, admins can access admin panel)
    if (requiresStaff && !authStore.isStaff) {
      // User is logged in but not staff - redirect to user dashboard
      const tenantSlug = to.params.tenant as string || tenantStore.slug
      if (tenantSlug) {
        return next({ name: 'tenant-dashboard', params: { tenant: tenantSlug } })
      }
      return next({ name: 'dashboard' })
    }
  }

  next()
})

// Apply theme based on route (admin uses default, others use tenant branding)
router.afterEach((to) => {
  const tenantStore = useTenantStore()
  const isAdminRoute = to.path.startsWith('/admin')
  tenantStore.applyTheme(isAdminRoute)
})

export default router
