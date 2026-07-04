import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useTenantStore } from '@/stores/tenant'
import { detectTenantSlug } from '@/utils/tenant'
import { storage, STORAGE_KEYS } from '@/utils/storage'
import { platform } from '@/platform'
import { mobileRoutes } from './routes/mobile'
import { adminRoutes } from './routes/admin'
import { webRoutes } from './routes/web'

const routes: RouteRecordRaw[] = [
  // Rutas del entry-point móvil nativo (/m/*) — ver ./routes/mobile.ts
  ...mobileRoutes,

  // Rutas web (tenant-prefijadas + no-prefijadas) — ver ./routes/web.ts
  ...webRoutes,
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
