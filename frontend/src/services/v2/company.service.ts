/**
 * V2 Company Service
 *
 * Handles Company (Persona Moral) CRUD and nested resources.
 * All endpoints are under /api/companies
 */

import { api } from '../api'
import type {
  V2ApiResponse,
  V2PaginatedResponse,
  V2Company,
  V2CompanyCreatePayload,
  V2Address,
  V2AddressPayload,
  V2CompanyMember,
  V2CompanyMemberPayload,
} from '@/types/v2'

const BASE_PATH = '/companies'

// =====================================================
// Company CRUD (Applicant Routes)
// =====================================================

/**
 * List user's companies.
 */
export async function list(): Promise<V2ApiResponse<V2Company[]>> {
  const response = await api.get<V2ApiResponse<V2Company[]>>(BASE_PATH)
  return response.data
}

/**
 * Create a new company.
 */
export async function create(payload: V2CompanyCreatePayload): Promise<V2ApiResponse<V2Company>> {
  const response = await api.post<V2ApiResponse<V2Company>>(BASE_PATH, payload)
  return response.data
}

/**
 * Find company by RFC.
 */
export async function findByRfc(rfc: string): Promise<V2ApiResponse<V2Company | null>> {
  const response = await api.post<V2ApiResponse<V2Company | null>>(`${BASE_PATH}/find-by-rfc`, { rfc })
  return response.data
}

/**
 * Get company by ID.
 */
export async function get(id: string): Promise<V2ApiResponse<V2Company>> {
  const response = await api.get<V2ApiResponse<V2Company>>(`${BASE_PATH}/${id}`)
  return response.data
}

/**
 * Update company.
 */
export async function update(
  id: string,
  payload: Partial<V2CompanyCreatePayload>
): Promise<V2ApiResponse<V2Company>> {
  const response = await api.put<V2ApiResponse<V2Company>>(`${BASE_PATH}/${id}`, payload)
  return response.data
}

// =====================================================
// Company Addresses
// =====================================================

export const addresses = {
  /**
   * List company addresses.
   */
  async list(companyId: string): Promise<V2ApiResponse<V2Address[]>> {
    const response = await api.get<V2ApiResponse<V2Address[]>>(`${BASE_PATH}/${companyId}/addresses`)
    return response.data
  },

  /**
   * Add address to company.
   */
  async create(companyId: string, payload: V2AddressPayload): Promise<V2ApiResponse<V2Address>> {
    const response = await api.post<V2ApiResponse<V2Address>>(
      `${BASE_PATH}/${companyId}/addresses`,
      payload
    )
    return response.data
  },

  /**
   * Get address by ID.
   */
  async get(companyId: string, addressId: string): Promise<V2ApiResponse<V2Address>> {
    const response = await api.get<V2ApiResponse<V2Address>>(
      `${BASE_PATH}/${companyId}/addresses/${addressId}`
    )
    return response.data
  },

  /**
   * Update address.
   */
  async update(
    companyId: string,
    addressId: string,
    payload: Partial<V2AddressPayload>
  ): Promise<V2ApiResponse<V2Address>> {
    const response = await api.put<V2ApiResponse<V2Address>>(
      `${BASE_PATH}/${companyId}/addresses/${addressId}`,
      payload
    )
    return response.data
  },

  /**
   * Delete address.
   */
  async remove(companyId: string, addressId: string): Promise<V2ApiResponse<{ message: string }>> {
    const response = await api.delete<V2ApiResponse<{ message: string }>>(
      `${BASE_PATH}/${companyId}/addresses/${addressId}`
    )
    return response.data
  },
}

// =====================================================
// Company Members
// =====================================================

export const members = {
  /**
   * List company members.
   */
  async list(companyId: string): Promise<V2ApiResponse<V2CompanyMember[]>> {
    const response = await api.get<V2ApiResponse<V2CompanyMember[]>>(`${BASE_PATH}/${companyId}/members`)
    return response.data
  },

  /**
   * Add member to company.
   */
  async create(companyId: string, payload: V2CompanyMemberPayload): Promise<V2ApiResponse<V2CompanyMember>> {
    const response = await api.post<V2ApiResponse<V2CompanyMember>>(
      `${BASE_PATH}/${companyId}/members`,
      payload
    )
    return response.data
  },

  /**
   * Get member by ID.
   */
  async get(companyId: string, memberId: string): Promise<V2ApiResponse<V2CompanyMember>> {
    const response = await api.get<V2ApiResponse<V2CompanyMember>>(
      `${BASE_PATH}/${companyId}/members/${memberId}`
    )
    return response.data
  },

  /**
   * Update member.
   */
  async update(
    companyId: string,
    memberId: string,
    payload: Partial<V2CompanyMemberPayload>
  ): Promise<V2ApiResponse<V2CompanyMember>> {
    const response = await api.put<V2ApiResponse<V2CompanyMember>>(
      `${BASE_PATH}/${companyId}/members/${memberId}`,
      payload
    )
    return response.data
  },

  /**
   * Delete member.
   */
  async remove(companyId: string, memberId: string): Promise<V2ApiResponse<{ message: string }>> {
    const response = await api.delete<V2ApiResponse<{ message: string }>>(
      `${BASE_PATH}/${companyId}/members/${memberId}`
    )
    return response.data
  },

  /**
   * Accept membership invitation.
   */
  async accept(companyId: string, memberId: string): Promise<V2ApiResponse<V2CompanyMember>> {
    const response = await api.post<V2ApiResponse<V2CompanyMember>>(
      `${BASE_PATH}/${companyId}/members/${memberId}/accept`
    )
    return response.data
  },

  /**
   * Suspend member.
   */
  async suspend(companyId: string, memberId: string): Promise<V2ApiResponse<V2CompanyMember>> {
    const response = await api.post<V2ApiResponse<V2CompanyMember>>(
      `${BASE_PATH}/${companyId}/members/${memberId}/suspend`
    )
    return response.data
  },

  /**
   * Transfer ownership.
   */
  async transferOwnership(
    companyId: string,
    memberId: string
  ): Promise<V2ApiResponse<V2CompanyMember>> {
    const response = await api.post<V2ApiResponse<V2CompanyMember>>(
      `${BASE_PATH}/${companyId}/members/${memberId}/transfer-ownership`
    )
    return response.data
  },
}

// =====================================================
// Admin Company Operations
// =====================================================

export const admin = {
  /**
   * List all companies (admin).
   */
  async list(params?: {
    search?: string
    status?: string
    kyb_status?: string
    page?: number
    per_page?: number
  }): Promise<V2PaginatedResponse<V2Company>> {
    const response = await api.get<V2PaginatedResponse<V2Company>>('/admin/companies', { params })
    return response.data
  },

  /**
   * Find company by RFC (admin).
   */
  async findByRfc(rfc: string): Promise<V2ApiResponse<V2Company | null>> {
    const response = await api.post<V2ApiResponse<V2Company | null>>('/admin/companies/find-by-rfc', { rfc })
    return response.data
  },

  /**
   * Get company details (admin).
   */
  async get(id: string): Promise<V2ApiResponse<V2Company>> {
    const response = await api.get<V2ApiResponse<V2Company>>(`/admin/companies/${id}`)
    return response.data
  },

  /**
   * Update company (admin).
   */
  async update(id: string, payload: Partial<V2CompanyCreatePayload>): Promise<V2ApiResponse<V2Company>> {
    const response = await api.put<V2ApiResponse<V2Company>>(`/admin/companies/${id}`, payload)
    return response.data
  },

  /**
   * Verify company (KYB).
   */
  async verify(id: string): Promise<V2ApiResponse<V2Company>> {
    const response = await api.post<V2ApiResponse<V2Company>>(`/admin/companies/${id}/verify`)
    return response.data
  },

  /**
   * Reject KYB verification.
   */
  async rejectKyb(id: string, reason: string): Promise<V2ApiResponse<V2Company>> {
    const response = await api.post<V2ApiResponse<V2Company>>(
      `/admin/companies/${id}/reject-kyb`,
      { reason }
    )
    return response.data
  },

  /**
   * Suspend company.
   */
  async suspend(id: string, reason?: string): Promise<V2ApiResponse<V2Company>> {
    const response = await api.post<V2ApiResponse<V2Company>>(
      `/admin/companies/${id}/suspend`,
      reason ? { reason } : undefined
    )
    return response.data
  },

  /**
   * Reactivate company.
   */
  async reactivate(id: string): Promise<V2ApiResponse<V2Company>> {
    const response = await api.post<V2ApiResponse<V2Company>>(`/admin/companies/${id}/reactivate`)
    return response.data
  },

  /**
   * Close company.
   */
  async close(id: string, reason?: string): Promise<V2ApiResponse<V2Company>> {
    const response = await api.post<V2ApiResponse<V2Company>>(
      `/admin/companies/${id}/close`,
      reason ? { reason } : undefined
    )
    return response.data
  },

  // Admin address operations
  addresses: {
    /**
     * List company addresses (admin).
     */
    async list(companyId: string): Promise<V2ApiResponse<V2Address[]>> {
      const response = await api.get<V2ApiResponse<V2Address[]>>(
        `/admin/companies/${companyId}/addresses`
      )
      return response.data
    },

    /**
     * Get address by ID (admin).
     */
    async get(companyId: string, addressId: string): Promise<V2ApiResponse<V2Address>> {
      const response = await api.get<V2ApiResponse<V2Address>>(
        `/admin/companies/${companyId}/addresses/${addressId}`
      )
      return response.data
    },

    /**
     * Verify address.
     */
    async verify(companyId: string, addressId: string): Promise<V2ApiResponse<V2Address>> {
      const response = await api.post<V2ApiResponse<V2Address>>(
        `/admin/companies/${companyId}/addresses/${addressId}/verify`
      )
      return response.data
    },

    /**
     * Reject address.
     */
    async reject(
      companyId: string,
      addressId: string,
      reason: string
    ): Promise<V2ApiResponse<V2Address>> {
      const response = await api.post<V2ApiResponse<V2Address>>(
        `/admin/companies/${companyId}/addresses/${addressId}/reject`,
        { reason }
      )
      return response.data
    },
  },

  // Admin member operations
  members: {
    /**
     * List company members (admin).
     */
    async list(companyId: string): Promise<V2ApiResponse<V2CompanyMember[]>> {
      const response = await api.get<V2ApiResponse<V2CompanyMember[]>>(
        `/admin/companies/${companyId}/members`
      )
      return response.data
    },

    /**
     * Get member by ID (admin).
     */
    async get(companyId: string, memberId: string): Promise<V2ApiResponse<V2CompanyMember>> {
      const response = await api.get<V2ApiResponse<V2CompanyMember>>(
        `/admin/companies/${companyId}/members/${memberId}`
      )
      return response.data
    },

    /**
     * Verify member.
     */
    async verify(companyId: string, memberId: string): Promise<V2ApiResponse<V2CompanyMember>> {
      const response = await api.post<V2ApiResponse<V2CompanyMember>>(
        `/admin/companies/${companyId}/members/${memberId}/verify`
      )
      return response.data
    },

    /**
     * Suspend member (admin).
     */
    async suspend(companyId: string, memberId: string): Promise<V2ApiResponse<V2CompanyMember>> {
      const response = await api.post<V2ApiResponse<V2CompanyMember>>(
        `/admin/companies/${companyId}/members/${memberId}/suspend`
      )
      return response.data
    },
  },
}

export default {
  list,
  create,
  findByRfc,
  get,
  update,
  addresses,
  members,
  admin,
}
