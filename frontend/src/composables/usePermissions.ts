import { computed } from 'vue'
import { useAuthStore } from '@/stores'

/**
 * Permission names available in the system.
 */
export type PermissionName =
  | 'canViewAllApplications'
  | 'canReviewDocuments'
  | 'canVerifyReferences'
  | 'canChangeApplicationStatus'
  | 'canApproveRejectApplications'
  | 'canAssignApplications'
  | 'canManageProducts'
  | 'canManageUsers'
  | 'canViewReports'
  | 'canConfigureTenant'

/**
 * Composable for checking user permissions in admin views.
 *
 * Eliminates repeated permission computed properties across admin components.
 *
 * @example
 * ```typescript
 * const { can, isStaff, isAdmin, isSuperAdmin } = usePermissions()
 *
 * // In template
 * <button v-if="can.assignApplications">Asignar</button>
 * <button v-if="can.approveRejectApplications">Aprobar</button>
 * ```
 */
export function usePermissions() {
  const authStore = useAuthStore()

  /**
   * Check if user is staff (any admin role).
   */
  const isStaff = computed(() => authStore.isStaff)

  /**
   * Check if user is admin or higher.
   */
  const isAdmin = computed(() => authStore.isAdmin)

  /**
   * Check if user is super admin.
   */
  const isSuperAdmin = computed(() => authStore.isSuperAdmin)

  /**
   * Check if user is supervisor.
   */
  const isSupervisor = computed(() => authStore.isSupervisor)

  /**
   * Check if user is analyst.
   */
  const isAnalyst = computed(() => authStore.isAnalyst)

  /**
   * Permission checker object with boolean getters.
   */
  const can = computed(() => {
    const permissions = authStore.permissions
    return {
      viewAllApplications: permissions?.canViewAllApplications ?? false,
      reviewDocuments: permissions?.canReviewDocuments ?? false,
      verifyReferences: permissions?.canVerifyReferences ?? false,
      changeApplicationStatus: permissions?.canChangeApplicationStatus ?? false,
      approveRejectApplications: permissions?.canApproveRejectApplications ?? false,
      assignApplications: permissions?.canAssignApplications ?? false,
      manageProducts: permissions?.canManageProducts ?? false,
      manageUsers: permissions?.canManageUsers ?? false,
      viewReports: permissions?.canViewReports ?? false,
      configureTenant: permissions?.canConfigureTenant ?? false,
    }
  })

  /**
   * Check a specific permission by name.
   */
  const hasPermission = (permission: PermissionName): boolean => {
    return authStore.permissions?.[permission] ?? false
  }

  /**
   * Check if user has any of the specified permissions.
   */
  const hasAnyPermission = (...permissions: PermissionName[]): boolean => {
    return permissions.some((p) => hasPermission(p))
  }

  /**
   * Check if user has all of the specified permissions.
   */
  const hasAllPermissions = (...permissions: PermissionName[]): boolean => {
    return permissions.every((p) => hasPermission(p))
  }

  /**
   * Get user's role display name.
   */
  const roleLabel = computed(() => {
    const user = authStore.user
    if (!user) return ''

    const labels: Record<string, string> = {
      SUPER_ADMIN: 'Super Admin',
      ADMIN: 'Administrador',
      SUPERVISOR: 'Supervisor',
      ANALYST: 'Analista',
      APPLICANT: 'Solicitante',
    }

    return labels[user.role] || user.role || ''
  })

  return {
    // Role checks
    isStaff,
    isAdmin,
    isSuperAdmin,
    isSupervisor,
    isAnalyst,
    roleLabel,

    // Permission object (use as can.assignApplications)
    can,

    // Permission functions
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
  }
}
