import type { RouteRecordRaw } from 'vue-router'
import { registerStaffAuth } from '@/services/staffAuthGateway'
import { staff } from './services'

// Registrar el auth de staff en el gateway compartido (dependency inversion): el
// store compartido `stores/auth.ts` lo consume SIN importar este módulo. Este
// archivo se importa de forma eager desde el router, así que el registro corre
// al iniciar la app, antes de cualquier login de staff.
registerStaffAuth({
  login: (p) => staff.auth.login(p),
  logout: () => staff.auth.logout(),
  getMe: () => staff.auth.getMe(),
})

// ==============================================
// ADMIN VIEWS (staff: agents, analysts, admins)
// ==============================================
const AdminLoginView = () => import('@/modules/admin/views/auth/AdminLoginView.vue')
const AdminLayout = () => import('@/modules/admin/views/panel/AdminLayout.vue')
const AdminDashboard = () => import('@/modules/admin/views/panel/AdminDashboard.vue')
const AdminApplications = () => import('@/modules/admin/views/panel/AdminApplications.vue')
const AdminApplicationDetail = () => import('@/modules/admin/views/panel/AdminApplicationDetail.vue')
const AdminUsers = () => import('@/modules/admin/views/panel/AdminUsers.vue')
const AdminProducts = () => import('@/modules/admin/views/panel/AdminProducts.vue')
const AdminTenants = () => import('@/modules/admin/views/panel/AdminTenants.vue')
const AdminSettings = () => import('@/modules/admin/views/panel/AdminSettings.vue')
const AdminIntegrations = () => import('@/modules/admin/views/settings/AdminIntegrationsView.vue')
const AdminApiLogs = () => import('@/modules/admin/views/panel/AdminApiLogs.vue')
const AdminUnderConstruction = () => import('@/modules/admin/views/panel/AdminUnderConstruction.vue')
const NotificationTemplates = () => import('@/modules/admin/views/panel/NotificationTemplates.vue')
const NotificationTemplateForm = () => import('@/modules/admin/views/panel/NotificationTemplateForm.vue')

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
        component: () => import('@/modules/admin/views/panel/AdminModulesConfig.vue'),
      },
      {
        // Motor de decisión: políticas versionadas + probador. Escritura solo
        // SUPER_ADMIN (la API valida canConfigureTenant); ADMIN consulta.
        path: 'motor-decision',
        name: 'admin-decision-engine',
        component: () => import('@/modules/admin/views/panel/AdminDecisionEngine.vue'),
      },
      {
        // Webhooks / integraciones salientes. Gestiona el ADMIN del tenant
        // (la API valida canManageProducts).
        path: 'webhooks',
        name: 'admin-webhooks',
        component: () => import('@/modules/admin/views/panel/AdminWebhooks.vue'),
      },
      {
        // Guía del integrador (webhooks + API de cartera): eventos, payloads,
        // firma HMAC y ejemplos de código. Solo lectura.
        path: 'integraciones/docs',
        name: 'admin-webhooks-docs',
        component: () => import('@/modules/admin/views/panel/AdminIntegrationDocs.vue'),
      },
      {
        path: 'prestamos',
        name: 'admin-loans',
        component: () => import('@/modules/admin/views/panel/AdminLoans.vue')
      },
      {
        path: 'prestamos/:id',
        name: 'admin-loan-detail',
        component: () => import('@/modules/admin/views/panel/AdminLoanDetail.vue')
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
        component: () => import('@/modules/admin/views/panel/NotificationPreferences.vue')
      }
    ]
  },
]
