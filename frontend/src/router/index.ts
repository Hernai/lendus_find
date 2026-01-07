import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// Lazy load views
const LandingView = () => import('@/views/LandingView.vue')
const SimulatorView = () => import('@/views/SimulatorView.vue')

// Auth views
const AuthMethodView = () => import('@/views/auth/AuthMethodView.vue')
const AuthPhoneView = () => import('@/views/auth/AuthPhoneView.vue')
const AuthEmailView = () => import('@/views/auth/AuthEmailView.vue')
const AuthOtpView = () => import('@/views/auth/AuthOtpView.vue')

// Onboarding views
const OnboardingLayout = () => import('@/views/onboarding/OnboardingLayout.vue')
const Step1PersonalData = () => import('@/views/onboarding/Step1PersonalData.vue')
const Step2Identification = () => import('@/views/onboarding/Step2Identification.vue')
const Step3Address = () => import('@/views/onboarding/Step3Address.vue')
const Step4Employment = () => import('@/views/onboarding/Step4Employment.vue')
const Step5LoanDetails = () => import('@/views/onboarding/Step5LoanDetails.vue')
const Step6Documents = () => import('@/views/onboarding/Step6Documents.vue')
const Step7References = () => import('@/views/onboarding/Step7References.vue')
const Step8Review = () => import('@/views/onboarding/Step8Review.vue')

// Dashboard views
const DashboardView = () => import('@/views/dashboard/DashboardView.vue')
const ApplicationStatusView = () => import('@/views/dashboard/ApplicationStatusView.vue')

// Admin views
const AdminLayout = () => import('@/views/admin/AdminLayout.vue')
const AdminDashboard = () => import('@/views/admin/AdminDashboard.vue')
const AdminApplications = () => import('@/views/admin/AdminApplications.vue')

const routes: RouteRecordRaw[] = [
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

  // Onboarding wizard (protected)
  {
    path: '/solicitud',
    component: OnboardingLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/solicitud/paso-1'
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

  // Admin routes (protected, admin only)
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true, requiresAdmin: true },
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

  // Check if route requires authentication
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth)
  const requiresAdmin = to.matched.some(record => record.meta.requiresAdmin)
  const isGuestOnly = to.matched.some(record => record.meta.guest)

  // If authenticated and trying to access guest-only page (like login)
  if (isGuestOnly && authStore.isAuthenticated) {
    return next({ name: 'dashboard' })
  }

  // If route requires auth
  if (requiresAuth) {
    // Check if user is authenticated
    if (!authStore.isAuthenticated) {
      // Try to check auth status (e.g., validate stored token)
      const isValid = await authStore.checkAuth()
      if (!isValid) {
        return next({ name: 'auth', query: { redirect: to.fullPath } })
      }
    }

    // Check admin requirement
    if (requiresAdmin && !authStore.isAdmin) {
      return next({ name: 'dashboard' })
    }
  }

  next()
})

export default router
