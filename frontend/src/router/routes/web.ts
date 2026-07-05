import type { RouteRecordRaw } from 'vue-router'

// ==============================================
// PUBLIC VIEWS (no authentication required)
// ==============================================
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
const StepBankAccount = () => import('@/views/applicant/onboarding/StepBankAccount.vue')

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

/**
 * Rutas web (SPA): tenant-prefijadas (`/:tenant/*`) y no-prefijadas (default
 * tenant por subdominio/env). Las prefijadas van ANTES que las no-prefijadas.
 */
export const webRoutes: RouteRecordRaw[] = [
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
      { path: 'cuenta-bancaria', name: 'tenant-onboarding-bank', component: StepBankAccount, meta: { step: 9, title: 'Cuenta bancaria' } },
      { path: 'paso-8', name: 'tenant-onboarding-step-8', component: Step8Review, meta: { step: 10, title: 'Revisión y firma' } }
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
        path: 'cuenta-bancaria',
        name: 'onboarding-bank',
        component: StepBankAccount,
        meta: { step: 9, title: 'Cuenta bancaria' }
      },
      {
        path: 'paso-8',
        name: 'onboarding-step-8',
        component: Step8Review,
        meta: { step: 10, title: 'Revisión y firma' }
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
    component: () => import('@/views/applicant/NotificationPreferences.vue'),
    meta: { requiresAuth: true }
  },
]
