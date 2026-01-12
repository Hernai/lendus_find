import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useTenantStore } from '@/stores/tenant'
import { detectTenantSlug } from '@/utils/tenant'

// ==============================================
// PUBLIC VIEWS (no authentication required)
// ==============================================
const LandingView = () => import('@/views/public/LandingView.vue')
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

// Applicant Onboarding (8-step wizard + KYC verification)
const OnboardingLayout = () => import('@/views/applicant/onboarding/OnboardingLayout.vue')
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

// ==============================================
// ADMIN VIEWS (staff: agents, analysts, admins)
// ==============================================

// Admin Auth (email/password)
const AdminLoginView = () => import('@/views/admin/auth/AdminLoginView.vue')

// Admin Panel
const AdminLayout = () => import('@/views/admin/panel/AdminLayout.vue')
const AdminDashboard = () => import('@/views/admin/panel/AdminDashboard.vue')
const AdminApplications = () => import('@/views/admin/panel/AdminApplications.vue')
const AdminApplicationDetail = () => import('@/views/admin/panel/AdminApplicationDetail.vue')
const AdminUsers = () => import('@/views/admin/panel/AdminUsers.vue')
const AdminProducts = () => import('@/views/admin/panel/AdminProducts.vue')
const AdminTenants = () => import('@/views/admin/panel/AdminTenants.vue')
const AdminSettings = () => import('@/views/admin/panel/AdminSettings.vue')
const AdminUnderConstruction = () => import('@/views/admin/panel/AdminUnderConstruction.vue')

// Reserved paths that are NOT tenant slugs (must match tenant.ts)
const RESERVED_PATHS = ['auth', 'admin', 'solicitud', 'dashboard', 'simulador', 'perfil', 'correcciones', 'find']

// Helper to check if a path segment is a tenant slug
const isTenantSlug = (segment: string): boolean => {
  return !!segment && !RESERVED_PATHS.includes(segment.toLowerCase())
}

const routes: RouteRecordRaw[] = [
  // ==============================================
  // TENANT-PREFIXED ROUTES (e.g., /demo/simulador)
  // These must come BEFORE the non-prefixed routes
  // ==============================================
  {
    path: '/:tenant',
    name: 'tenant-landing',
    component: LandingView,
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
      { path: '', redirect: (to) => `/${to.params.tenant}/solicitud/verificacion` },
      { path: 'verificacion', name: 'tenant-onboarding-kyc', component: StepKycVerification, meta: { step: 0, title: 'Verificación de identidad' } },
      { path: 'paso-1', name: 'tenant-onboarding-step-1', component: Step1PersonalData, meta: { step: 1, title: '¿Cómo te llamas?' } },
      { path: 'paso-2', name: 'tenant-onboarding-step-2', component: Step2Identification, meta: { step: 2, title: 'Tu identificación' } },
      { path: 'paso-3', name: 'tenant-onboarding-step-3', component: Step3Address, meta: { step: 3, title: '¿Dónde vives?' } },
      { path: 'paso-4', name: 'tenant-onboarding-step-4', component: Step4Employment, meta: { step: 4, title: '¿A qué te dedicas?' } },
      { path: 'paso-5', name: 'tenant-onboarding-step-5', component: Step5LoanDetails, meta: { step: 5, title: 'Tu crédito' } },
      { path: 'paso-6', name: 'tenant-onboarding-step-6', component: Step6Documents, meta: { step: 6, title: 'Documentos' } },
      { path: 'paso-7', name: 'tenant-onboarding-step-7', component: Step7References, meta: { step: 7, title: 'Referencias' } },
      { path: 'paso-8', name: 'tenant-onboarding-step-8', component: Step8Review, meta: { step: 8, title: 'Revisión y firma' } }
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

  // Public routes
  {
    path: '/',
    name: 'landing',
    component: LandingView,
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
        redirect: '/solicitud/verificacion'
      },
      {
        path: 'verificacion',
        name: 'onboarding-kyc',
        component: StepKycVerification,
        meta: { step: 0, title: 'Verificación de identidad' }
      },
      {
        path: 'paso-1',
        name: 'onboarding-step-1',
        component: Step1PersonalData,
        meta: { step: 1, title: '¿Cómo te llamas?' }
      },
      {
        path: 'paso-2',
        name: 'onboarding-step-2',
        component: Step2Identification,
        meta: { step: 2, title: 'Tu identificación' }
      },
      {
        path: 'paso-3',
        name: 'onboarding-step-3',
        component: Step3Address,
        meta: { step: 3, title: '¿Dónde vives?' }
      },
      {
        path: 'paso-4',
        name: 'onboarding-step-4',
        component: Step4Employment,
        meta: { step: 4, title: '¿A qué te dedicas?' }
      },
      {
        path: 'paso-5',
        name: 'onboarding-step-5',
        component: Step5LoanDetails,
        meta: { step: 5, title: 'Tu crédito' }
      },
      {
        path: 'paso-6',
        name: 'onboarding-step-6',
        component: Step6Documents,
        meta: { step: 6, title: 'Documentos' }
      },
      {
        path: 'paso-7',
        name: 'onboarding-step-7',
        component: Step7References,
        meta: { step: 7, title: 'Referencias' }
      },
      {
        path: 'paso-8',
        name: 'onboarding-step-8',
        component: Step8Review,
        meta: { step: 8, title: 'Revisión y firma' }
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

  // Admin login (public, guest only)
  {
    path: '/admin/login',
    name: 'admin-login',
    component: AdminLoginView,
    meta: { public: true, guest: true, adminGuest: true }
  },

  // Admin routes (protected, staff only - agents, analysts, admins)
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true, requiresStaff: true },
    children: [
      {
        path: '',
        name: 'admin-dashboard',
        component: AdminDashboard
      },
      {
        path: 'solicitudes',
        name: 'admin-applications',
        component: AdminApplications
      },
      {
        path: 'solicitudes/:id',
        name: 'admin-application-detail',
        component: AdminApplicationDetail
      },
      {
        path: 'productos',
        name: 'admin-products',
        component: AdminProducts
      },
      {
        path: 'usuarios',
        name: 'admin-users',
        component: AdminUsers
      },
      {
        path: 'reportes',
        name: 'admin-reports',
        component: AdminUnderConstruction
      },
      {
        path: 'configuracion',
        name: 'admin-settings',
        component: AdminSettings
      },
      {
        path: 'tenants',
        name: 'admin-tenants',
        component: AdminTenants
      }
    ]
  },

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
    const nonPrefixedPaths = ['/auth', '/solicitud', '/dashboard', '/correcciones', '/perfil', '/simulador']
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

  // Check if route requires authentication
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth)
  const requiresStaff = to.matched.some(record => record.meta.requiresStaff)
  const isGuestOnly = to.matched.some(record => record.meta.guest)
  const isAdminGuest = to.matched.some(record => record.meta.adminGuest)

  // DEV MODE: Auto-authenticate for development
  // This allows navigating directly to any step without logging in
  // DISABLED for admin routes to test real login flow
  if (import.meta.env.DEV && requiresAuth && !requiresStaff) {
    if (!localStorage.getItem('auth_token')) {
      localStorage.setItem('auth_token', 'dev-token-' + Date.now())
    }
    // Always re-check to update role based on current route context (admin vs user)
    await authStore.checkAuth(to.path)
  }

  // If authenticated staff trying to access admin-guest page (admin login)
  if (isAdminGuest && authStore.isAuthenticated && authStore.isStaff) {
    return next({ name: 'admin-dashboard' })
  }

  // If authenticated regular user trying to access guest-only page (like login)
  if (isGuestOnly && !isAdminGuest && authStore.isAuthenticated) {
    const tenantSlug = to.params.tenant as string || tenantStore.slug
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

    // Check if user needs to setup PIN (for applicants only, not staff)
    if (!requiresStaff && authStore.needsPinSetup && to.name !== 'auth-pin-setup' && to.name !== 'tenant-auth-pin-setup') {
      // Use tenant-prefixed route if tenant is available
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
