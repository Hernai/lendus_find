import type { RouteRecordRaw } from 'vue-router'

// ==============================================
// ADMIN VIEWS (staff: agents, analysts, admins)
// ==============================================
const AdminLoginView = () => import('@/views/admin/auth/AdminLoginView.vue')
const AdminLayout = () => import('@/views/admin/panel/AdminLayout.vue')
const AdminDashboard = () => import('@/views/admin/panel/AdminDashboard.vue')
const AdminApplications = () => import('@/views/admin/panel/AdminApplications.vue')
const AdminApplicationDetail = () => import('@/views/admin/panel/AdminApplicationDetail.vue')
const AdminUsers = () => import('@/views/admin/panel/AdminUsers.vue')
const AdminProducts = () => import('@/views/admin/panel/AdminProducts.vue')
const AdminTenants = () => import('@/views/admin/panel/AdminTenants.vue')
const AdminSettings = () => import('@/views/admin/panel/AdminSettings.vue')
const AdminIntegrations = () => import('@/views/admin/settings/AdminIntegrationsView.vue')
const AdminApiLogs = () => import('@/views/admin/panel/AdminApiLogs.vue')
const AdminUnderConstruction = () => import('@/views/admin/panel/AdminUnderConstruction.vue')
const NotificationTemplates = () => import('@/views/admin/panel/NotificationTemplates.vue')
const NotificationTemplateForm = () => import('@/views/admin/panel/NotificationTemplateForm.vue')

/**
 * Rutas del panel de administración (staff), prefijo `/admin`.
 * Requieren `requiresStaff`; el guard global (router/index.ts) las protege.
 */
export const adminRoutes: RouteRecordRaw[] = [
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
        path: 'integraciones',
        name: 'admin-integrations',
        component: AdminIntegrations
      },
      {
        path: 'api-logs',
        name: 'admin-api-logs',
        component: AdminApiLogs
      },
      {
        path: 'tenants',
        name: 'admin-tenants',
        component: AdminTenants
      },
      {
        // Solo SUPER_ADMIN global. La validación efectiva la hace el endpoint
        // backend; aquí solo controlamos visibilidad del sidebar.
        path: 'configuracion/modulos',
        name: 'admin-modules-config',
        component: () => import('@/views/admin/panel/AdminModulesConfig.vue'),
      },
      {
        path: 'prestamos',
        name: 'admin-loans',
        component: () => import('@/views/admin/panel/AdminLoans.vue')
      },
      {
        path: 'prestamos/:id',
        name: 'admin-loan-detail',
        component: () => import('@/views/admin/panel/AdminLoanDetail.vue')
      },
      {
        path: 'notificaciones',
        name: 'admin-notification-templates',
        component: NotificationTemplates
      },
      {
        path: 'notificaciones/nueva',
        name: 'admin-notification-template-create',
        component: NotificationTemplateForm
      },
      {
        path: 'notificaciones/:id/editar',
        name: 'admin-notification-template-edit',
        component: NotificationTemplateForm
      },
      {
        path: 'mis-notificaciones',
        name: 'admin-notification-preferences',
        component: () => import('@/views/admin/panel/NotificationPreferences.vue')
      }
    ]
  },
]
