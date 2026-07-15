/**
 * Catálogo único de módulos del backoffice.
 *
 * Es la **fuente de verdad** para qué módulos existen, qué label se les
 * muestra y qué roles los ven por default. El sidebar (`AdminLayout`) y la
 * matriz de override (`AdminModulesConfig`) consumen este mismo objeto —
 * agregar/quitar un módulo en producción es modificar este archivo.
 *
 * Cómo se decide la visibilidad efectiva (en runtime):
 *
 *   1. SUPER_ADMIN global: TODO visible, sin importar overrides.
 *   2. Si el módulo tiene `requiresFeature`, el tenant debe tener esa
 *      feature en `Tenant.features` (legacy) — si no, oculto.
 *   3. Si existe un override (tenant × rol × module) → gana ese.
 *   4. Sino → gana `defaultRoles.includes(currentRole)`.
 *
 * SUPER_ADMIN no aparece en `defaultRoles` ni en la matriz: siempre se
 * incluye por short-circuit en el step 1. Los overrides solo aplican a
 * ANALYST, SUPERVISOR y ADMIN.
 */

import type { UserRole } from '@/types'

export type AdminModuleKey =
  | 'dashboard'
  | 'applications'
  | 'loans'
  | 'products'
  | 'users'
  | 'reports'
  | 'notifications'
  | 'decision_engine'
  | 'tenants'
  | 'settings'
  | 'admin_modules_config'

/**
 * Sección a la que pertenece el módulo. La usan:
 *  - `AdminLayout`: los módulos de `configuration` se colapsan en un
 *    dropdown "Configuración" del top-nav (los demás van planos).
 *  - `AdminModulesConfig`: agrupa las filas de la matriz con un header
 *    por sección.
 */
export type AdminModuleCategory = 'operations' | 'administration' | 'configuration'

export const ADMIN_MODULE_CATEGORIES: { key: AdminModuleCategory; label: string }[] = [
  { key: 'operations', label: 'Operaciones' },
  { key: 'administration', label: 'Administración' },
  { key: 'configuration', label: 'Configuración' },
]

export interface AdminModule {
  /** Llave estable que viaja al backend (no traducir). */
  key: AdminModuleKey
  /** Etiqueta visible en el sidebar y en la matriz de overrides. */
  label: string
  /** Path de Vue Router al que navega el item del sidebar. */
  path: string
  /** SVG path para el ícono del item (usa `currentColor` para heredar tema). */
  icon: string
  /** Sección del menú/matriz a la que pertenece. */
  category: AdminModuleCategory
  /** Roles que ven el módulo por DEFAULT (cuando no hay override). */
  defaultRoles: Exclude<UserRole, 'APPLICANT'>[]
  /**
   * Si se setea, el módulo solo se muestra cuando `Tenant.features[X]` es
   * true. Útil para mantener la compatibilidad con flags legacy
   * (`loan_portfolio`, etc.) sin migrarlas a la tabla de overrides.
   */
  requiresFeature?: string
  /**
   * Solo SUPER_ADMIN global lo ve. No aparece en la matriz de overrides
   * (no tiene sentido permitir/quitar a otros roles).
   */
  superAdminOnly?: boolean
}

export const ADMIN_MODULES: AdminModule[] = [
  {
    key: 'dashboard',
    category: 'operations',
    label: 'Dashboard',
    path: '/admin',
    icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    defaultRoles: ['ANALYST', 'SUPERVISOR', 'ADMIN', 'SUPER_ADMIN'],
  },
  {
    key: 'applications',
    category: 'operations',
    label: 'Solicitudes',
    path: '/admin/solicitudes',
    icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    defaultRoles: ['ANALYST', 'SUPERVISOR', 'ADMIN', 'SUPER_ADMIN'],
  },
  {
    key: 'loans',
    category: 'operations',
    label: 'Préstamos',
    path: '/admin/prestamos',
    icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    defaultRoles: ['SUPERVISOR', 'ADMIN', 'SUPER_ADMIN'],
    requiresFeature: 'loan_portfolio',
  },
  {
    key: 'products',
    category: 'administration',
    label: 'Productos',
    path: '/admin/productos',
    icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
    defaultRoles: ['ADMIN', 'SUPER_ADMIN'],
  },
  {
    key: 'users',
    category: 'administration',
    label: 'Usuarios',
    path: '/admin/usuarios',
    icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    defaultRoles: ['ADMIN', 'SUPER_ADMIN'],
  },
  {
    key: 'reports',
    category: 'administration',
    label: 'Reportes',
    path: '/admin/reportes',
    icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    defaultRoles: ['SUPERVISOR', 'ADMIN', 'SUPER_ADMIN'],
  },
  {
    key: 'notifications',
    category: 'configuration',
    label: 'Templates Notif.',
    path: '/admin/notificaciones',
    icon: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
    defaultRoles: ['ADMIN', 'SUPER_ADMIN'],
  },
  {
    // Configurador del motor de decisión (Matriz MoneyCapital): políticas
    // versionadas, modos off/shadow/active y probador. Edita SUPER_ADMIN
    // (canConfigureTenant, enforced por la API); ADMIN consulta.
    key: 'decision_engine',
    category: 'configuration',
    label: 'Motor de decisión',
    path: '/admin/motor-decision',
    icon: 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
    defaultRoles: ['ADMIN', 'SUPER_ADMIN'],
  },
  {
    key: 'tenants',
    category: 'configuration',
    label: 'Tenants',
    path: '/admin/tenants',
    icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    defaultRoles: ['SUPER_ADMIN'],
    superAdminOnly: true,
  },
  {
    key: 'settings',
    category: 'configuration',
    label: 'Configuración',
    path: '/admin/configuracion',
    icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    defaultRoles: ['ADMIN', 'SUPER_ADMIN'],
  },
  {
    // Solo super admin global edita módulos visibles por (tenant × rol).
    key: 'admin_modules_config',
    category: 'configuration',
    label: 'Módulos',
    path: '/admin/configuracion/modulos',
    icon: 'M4 6h16M4 10h16M4 14h16M4 18h16',
    defaultRoles: ['SUPER_ADMIN'],
    superAdminOnly: true,
  },
]

/**
 * Subset de módulos visibles en la matriz de overrides — los `superAdminOnly`
 * no aplican porque siempre son visibles solo al super admin.
 */
export const OVERRIDABLE_MODULES = ADMIN_MODULES.filter((m) => !m.superAdminOnly)

/** Roles que el super admin puede editar en la matriz (no SUPER_ADMIN). */
export const OVERRIDABLE_ROLES = ['ANALYST', 'SUPERVISOR', 'ADMIN'] as const
export type OverridableRole = (typeof OVERRIDABLE_ROLES)[number]

/**
 * Decide si un módulo debe mostrarse para el usuario actual.
 *
 * @param module          Item del catálogo
 * @param role            Rol del staff autenticado
 * @param tenantFeatures  Map de `Tenant.features` (legacy)
 * @param overrides       Map "ROLE.module_key" → enabled (de tenant_role_module_overrides)
 */
export function isModuleVisible(
  module: AdminModule,
  role: UserRole | null | undefined,
  tenantFeatures: Record<string, boolean> | null | undefined,
  overrides: Record<string, boolean> | null | undefined,
): boolean {
  if (!role) return false

  // Step 1: super admin global ve todo.
  if (role === 'SUPER_ADMIN') return true

  // Step 2: superAdminOnly bloquea a todos los demás.
  if (module.superAdminOnly) return false

  // Step 3: requiresFeature (legacy `Tenant.features`).
  if (module.requiresFeature) {
    const flag = tenantFeatures?.[module.requiresFeature]
    if (!flag) return false
  }

  // Step 4: override explícito gana sobre default.
  const overrideKey = `${role}.${module.key}`
  if (overrides && overrideKey in overrides) {
    return !!overrides[overrideKey]
  }

  // Step 5: fallback al default del catálogo.
  return module.defaultRoles.includes(role as Exclude<UserRole, 'APPLICANT'>)
}
