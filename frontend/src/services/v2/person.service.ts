/**
 * V2 Person Service
 *
 * Handles Person CRUD and nested resources (identifications, addresses, employments, references, bank accounts).
 * All endpoints are under /api/persons
 */

import { api } from '../api'
import type {
  V2ApiResponse,
  V2PaginatedResponse,
  V2Person,
  V2PersonCreatePayload,
  V2PersonUpdatePayload,
  V2Identification,
  V2IdentificationPayload,
  V2IdentificationType,
  V2Address,
  V2AddressPayload,
  V2AddressType,
  V2Employment,
  V2EmploymentPayload,
  V2Reference,
  V2ReferencePayload,
  V2ReferenceType,
  V2BankAccount,
  V2BankAccountPayload,
  V2ClabeValidationResult,
} from '@/types/v2'

const BASE_PATH = '/persons'

// =====================================================
// Person CRUD
// =====================================================

/**
 * List all persons.
 */
export async function list(params?: {
  search?: string
  kyc_status?: string
  page?: number
  per_page?: number
}): Promise<V2PaginatedResponse<V2Person>> {
  const response = await api.get<V2PaginatedResponse<V2Person>>(BASE_PATH, { params })
  return response.data
}

/**
 * Create a new person.
 */
export async function create(payload: V2PersonCreatePayload): Promise<V2ApiResponse<V2Person>> {
  const response = await api.post<V2ApiResponse<V2Person>>(BASE_PATH, payload)
  return response.data
}

/**
 * Get person by ID.
 */
export async function get(id: string): Promise<V2ApiResponse<V2Person>> {
  const response = await api.get<V2ApiResponse<V2Person>>(`${BASE_PATH}/${id}`)
  return response.data
}

/**
 * Update person.
 */
export async function update(id: string, payload: V2PersonUpdatePayload): Promise<V2ApiResponse<V2Person>> {
  const response = await api.put<V2ApiResponse<V2Person>>(`${BASE_PATH}/${id}`, payload)
  return response.data
}

/**
 * Delete person.
 */
export async function remove(id: string): Promise<V2ApiResponse<{ message: string }>> {
  const response = await api.delete<V2ApiResponse<{ message: string }>>(`${BASE_PATH}/${id}`)
  return response.data
}

/**
 * Get person statistics.
 */
export async function getStatistics(): Promise<V2ApiResponse<Record<string, number>>> {
  const response = await api.get<V2ApiResponse<Record<string, number>>>(`${BASE_PATH}/statistics`)
  return response.data
}

/**
 * Get person summary.
 */
export async function getSummary(id: string): Promise<V2ApiResponse<Record<string, unknown>>> {
  const response = await api.get<V2ApiResponse<Record<string, unknown>>>(`${BASE_PATH}/${id}/summary`)
  return response.data
}

/**
 * Find person by CURP.
 */
export async function findByCurp(curp: string): Promise<V2ApiResponse<V2Person | null>> {
  const response = await api.post<V2ApiResponse<V2Person | null>>(`${BASE_PATH}/find-by-curp`, { curp })
  return response.data
}

/**
 * Find person by RFC.
 */
export async function findByRfc(rfc: string): Promise<V2ApiResponse<V2Person | null>> {
  const response = await api.post<V2ApiResponse<V2Person | null>>(`${BASE_PATH}/find-by-rfc`, { rfc })
  return response.data
}

/**
 * Update KYC status.
 */
export async function updateKycStatus(
  id: string,
  status: 'PENDING' | 'VERIFIED' | 'REJECTED' | 'EXPIRED'
): Promise<V2ApiResponse<V2Person>> {
  const response = await api.post<V2ApiResponse<V2Person>>(`${BASE_PATH}/${id}/kyc-status`, { status })
  return response.data
}

/**
 * Recalculate data completeness.
 */
export async function recalculateCompleteness(id: string): Promise<V2ApiResponse<{ completeness: number }>> {
  const response = await api.post<V2ApiResponse<{ completeness: number }>>(
    `${BASE_PATH}/${id}/recalculate-completeness`
  )
  return response.data
}

// =====================================================
// Identifications
// =====================================================

export const identifications = {
  /**
   * List person identifications.
   */
  async list(personId: string): Promise<V2ApiResponse<V2Identification[]>> {
    const response = await api.get<V2ApiResponse<V2Identification[]>>(
      `${BASE_PATH}/${personId}/identifications`
    )
    return response.data
  },

  /**
   * Add identification.
   */
  async create(personId: string, payload: V2IdentificationPayload): Promise<V2ApiResponse<V2Identification>> {
    const response = await api.post<V2ApiResponse<V2Identification>>(
      `${BASE_PATH}/${personId}/identifications`,
      payload
    )
    return response.data
  },

  /**
   * Get current identification.
   */
  async getCurrent(personId: string): Promise<V2ApiResponse<V2Identification | null>> {
    const response = await api.get<V2ApiResponse<V2Identification | null>>(
      `${BASE_PATH}/${personId}/identifications/current`
    )
    return response.data
  },

  /**
   * Get current identification by type.
   */
  async getCurrentByType(personId: string, type: V2IdentificationType): Promise<V2ApiResponse<V2Identification | null>> {
    const response = await api.get<V2ApiResponse<V2Identification | null>>(
      `${BASE_PATH}/${personId}/identifications/current/${type}`
    )
    return response.data
  },

  /**
   * Get pending identifications.
   */
  async getPending(personId: string): Promise<V2ApiResponse<V2Identification[]>> {
    const response = await api.get<V2ApiResponse<V2Identification[]>>(
      `${BASE_PATH}/${personId}/identifications/pending`
    )
    return response.data
  },

  /**
   * Check if type is verified.
   */
  async hasVerified(personId: string, type: V2IdentificationType): Promise<V2ApiResponse<{ verified: boolean }>> {
    const response = await api.get<V2ApiResponse<{ verified: boolean }>>(
      `${BASE_PATH}/${personId}/identifications/has-verified/${type}`
    )
    return response.data
  },

  /**
   * Set CURP identification.
   */
  async setCurp(personId: string, curp: string): Promise<V2ApiResponse<V2Identification>> {
    const response = await api.post<V2ApiResponse<V2Identification>>(
      `${BASE_PATH}/${personId}/identifications/curp`,
      { curp }
    )
    return response.data
  },

  /**
   * Set RFC identification.
   */
  async setRfc(personId: string, rfc: string): Promise<V2ApiResponse<V2Identification>> {
    const response = await api.post<V2ApiResponse<V2Identification>>(
      `${BASE_PATH}/${personId}/identifications/rfc`,
      { rfc }
    )
    return response.data
  },

  /**
   * Set INE identification.
   */
  async setIne(personId: string, ine: string, metadata?: Record<string, unknown>): Promise<V2ApiResponse<V2Identification>> {
    const response = await api.post<V2ApiResponse<V2Identification>>(
      `${BASE_PATH}/${personId}/identifications/ine`,
      { ine, metadata }
    )
    return response.data
  },

  /**
   * Get identification by ID.
   */
  async get(personId: string, identificationId: string): Promise<V2ApiResponse<V2Identification>> {
    const response = await api.get<V2ApiResponse<V2Identification>>(
      `${BASE_PATH}/${personId}/identifications/${identificationId}`
    )
    return response.data
  },

  /**
   * Update identification.
   */
  async update(
    personId: string,
    identificationId: string,
    payload: Partial<V2IdentificationPayload>
  ): Promise<V2ApiResponse<V2Identification>> {
    const response = await api.put<V2ApiResponse<V2Identification>>(
      `${BASE_PATH}/${personId}/identifications/${identificationId}`,
      payload
    )
    return response.data
  },

  /**
   * Delete identification.
   */
  async remove(personId: string, identificationId: string): Promise<V2ApiResponse<{ message: string }>> {
    const response = await api.delete<V2ApiResponse<{ message: string }>>(
      `${BASE_PATH}/${personId}/identifications/${identificationId}`
    )
    return response.data
  },

  /**
   * Verify identification.
   */
  async verify(personId: string, identificationId: string): Promise<V2ApiResponse<V2Identification>> {
    const response = await api.post<V2ApiResponse<V2Identification>>(
      `${BASE_PATH}/${personId}/identifications/${identificationId}/verify`
    )
    return response.data
  },

  /**
   * Reject identification.
   */
  async reject(personId: string, identificationId: string, reason: string): Promise<V2ApiResponse<V2Identification>> {
    const response = await api.post<V2ApiResponse<V2Identification>>(
      `${BASE_PATH}/${personId}/identifications/${identificationId}/reject`,
      { reason }
    )
    return response.data
  },
}

// =====================================================
// Addresses
// =====================================================

export const addresses = {
  /**
   * List person addresses.
   */
  async list(personId: string): Promise<V2ApiResponse<V2Address[]>> {
    const response = await api.get<V2ApiResponse<V2Address[]>>(`${BASE_PATH}/${personId}/addresses`)
    return response.data
  },

  /**
   * Add address.
   */
  async create(personId: string, payload: V2AddressPayload): Promise<V2ApiResponse<V2Address>> {
    const response = await api.post<V2ApiResponse<V2Address>>(
      `${BASE_PATH}/${personId}/addresses`,
      payload
    )
    return response.data
  },

  /**
   * Get current address.
   */
  async getCurrent(personId: string): Promise<V2ApiResponse<V2Address | null>> {
    const response = await api.get<V2ApiResponse<V2Address | null>>(
      `${BASE_PATH}/${personId}/addresses/current`
    )
    return response.data
  },

  /**
   * Get current home address.
   */
  async getCurrentHome(personId: string): Promise<V2ApiResponse<V2Address | null>> {
    const response = await api.get<V2ApiResponse<V2Address | null>>(
      `${BASE_PATH}/${personId}/addresses/current-home`
    )
    return response.data
  },

  /**
   * Set home address.
   */
  async setHome(personId: string, payload: V2AddressPayload): Promise<V2ApiResponse<V2Address>> {
    const response = await api.post<V2ApiResponse<V2Address>>(
      `${BASE_PATH}/${personId}/addresses/home`,
      payload
    )
    return response.data
  },

  /**
   * Get address history by type.
   */
  async getHistory(personId: string, type: V2AddressType): Promise<V2ApiResponse<V2Address[]>> {
    const response = await api.get<V2ApiResponse<V2Address[]>>(
      `${BASE_PATH}/${personId}/addresses/history/${type}`
    )
    return response.data
  },

  /**
   * Check if address type is verified.
   */
  async hasVerified(personId: string, type: V2AddressType): Promise<V2ApiResponse<{ verified: boolean }>> {
    const response = await api.get<V2ApiResponse<{ verified: boolean }>>(
      `${BASE_PATH}/${personId}/addresses/has-verified/${type}`
    )
    return response.data
  },

  /**
   * Get address by ID.
   */
  async get(personId: string, addressId: string): Promise<V2ApiResponse<V2Address>> {
    const response = await api.get<V2ApiResponse<V2Address>>(
      `${BASE_PATH}/${personId}/addresses/${addressId}`
    )
    return response.data
  },

  /**
   * Update address.
   */
  async update(personId: string, addressId: string, payload: Partial<V2AddressPayload>): Promise<V2ApiResponse<V2Address>> {
    const response = await api.put<V2ApiResponse<V2Address>>(
      `${BASE_PATH}/${personId}/addresses/${addressId}`,
      payload
    )
    return response.data
  },

  /**
   * Delete address.
   */
  async remove(personId: string, addressId: string): Promise<V2ApiResponse<{ message: string }>> {
    const response = await api.delete<V2ApiResponse<{ message: string }>>(
      `${BASE_PATH}/${personId}/addresses/${addressId}`
    )
    return response.data
  },

  /**
   * Verify address.
   */
  async verify(personId: string, addressId: string): Promise<V2ApiResponse<V2Address>> {
    const response = await api.post<V2ApiResponse<V2Address>>(
      `${BASE_PATH}/${personId}/addresses/${addressId}/verify`
    )
    return response.data
  },

  /**
   * Reject address.
   */
  async reject(personId: string, addressId: string, reason: string): Promise<V2ApiResponse<V2Address>> {
    const response = await api.post<V2ApiResponse<V2Address>>(
      `${BASE_PATH}/${personId}/addresses/${addressId}/reject`,
      { reason }
    )
    return response.data
  },

  /**
   * Set geolocation data.
   */
  async setGeolocation(
    personId: string,
    addressId: string,
    latitude: number,
    longitude: number
  ): Promise<V2ApiResponse<V2Address>> {
    const response = await api.post<V2ApiResponse<V2Address>>(
      `${BASE_PATH}/${personId}/addresses/${addressId}/geolocation`,
      { latitude, longitude }
    )
    return response.data
  },
}

// =====================================================
// Employments
// =====================================================

export const employments = {
  /**
   * List person employments.
   */
  async list(personId: string): Promise<V2ApiResponse<V2Employment[]>> {
    const response = await api.get<V2ApiResponse<V2Employment[]>>(`${BASE_PATH}/${personId}/employments`)
    return response.data
  },

  /**
   * Add employment.
   */
  async create(personId: string, payload: V2EmploymentPayload): Promise<V2ApiResponse<V2Employment>> {
    const response = await api.post<V2ApiResponse<V2Employment>>(
      `${BASE_PATH}/${personId}/employments`,
      payload
    )
    return response.data
  },

  /**
   * Get current employment.
   */
  async getCurrent(personId: string): Promise<V2ApiResponse<V2Employment | null>> {
    const response = await api.get<V2ApiResponse<V2Employment | null>>(
      `${BASE_PATH}/${personId}/employments/current`
    )
    return response.data
  },

  /**
   * Set current employment.
   */
  async setCurrent(personId: string, payload: V2EmploymentPayload): Promise<V2ApiResponse<V2Employment>> {
    const response = await api.post<V2ApiResponse<V2Employment>>(
      `${BASE_PATH}/${personId}/employments/current`,
      payload
    )
    return response.data
  },

  /**
   * Get income summary.
   */
  async getIncomeSummary(personId: string): Promise<V2ApiResponse<{ monthly_income: number; annual_income: number }>> {
    const response = await api.get<V2ApiResponse<{ monthly_income: number; annual_income: number }>>(
      `${BASE_PATH}/${personId}/employments/income-summary`
    )
    return response.data
  },

  /**
   * Calculate debt-to-income ratio.
   */
  async calculateDti(personId: string, monthlyDebt: number): Promise<V2ApiResponse<{ dti: number }>> {
    const response = await api.post<V2ApiResponse<{ dti: number }>>(
      `${BASE_PATH}/${personId}/employments/calculate-dti`,
      { monthly_debt: monthlyDebt }
    )
    return response.data
  },

  /**
   * Check if current employment is verified.
   */
  async hasVerifiedCurrent(personId: string): Promise<V2ApiResponse<{ verified: boolean }>> {
    const response = await api.get<V2ApiResponse<{ verified: boolean }>>(
      `${BASE_PATH}/${personId}/employments/has-verified-current`
    )
    return response.data
  },

  /**
   * Check if income is verified.
   */
  async hasVerifiedIncome(personId: string): Promise<V2ApiResponse<{ verified: boolean }>> {
    const response = await api.get<V2ApiResponse<{ verified: boolean }>>(
      `${BASE_PATH}/${personId}/employments/has-verified-income`
    )
    return response.data
  },

  /**
   * Get employment by ID.
   */
  async get(personId: string, employmentId: string): Promise<V2ApiResponse<V2Employment>> {
    const response = await api.get<V2ApiResponse<V2Employment>>(
      `${BASE_PATH}/${personId}/employments/${employmentId}`
    )
    return response.data
  },

  /**
   * Update employment.
   */
  async update(
    personId: string,
    employmentId: string,
    payload: Partial<V2EmploymentPayload>
  ): Promise<V2ApiResponse<V2Employment>> {
    const response = await api.put<V2ApiResponse<V2Employment>>(
      `${BASE_PATH}/${personId}/employments/${employmentId}`,
      payload
    )
    return response.data
  },

  /**
   * Delete employment.
   */
  async remove(personId: string, employmentId: string): Promise<V2ApiResponse<{ message: string }>> {
    const response = await api.delete<V2ApiResponse<{ message: string }>>(
      `${BASE_PATH}/${personId}/employments/${employmentId}`
    )
    return response.data
  },

  /**
   * Verify employment.
   */
  async verify(personId: string, employmentId: string): Promise<V2ApiResponse<V2Employment>> {
    const response = await api.post<V2ApiResponse<V2Employment>>(
      `${BASE_PATH}/${personId}/employments/${employmentId}/verify`
    )
    return response.data
  },

  /**
   * Verify income.
   */
  async verifyIncome(personId: string, employmentId: string): Promise<V2ApiResponse<V2Employment>> {
    const response = await api.post<V2ApiResponse<V2Employment>>(
      `${BASE_PATH}/${personId}/employments/${employmentId}/verify-income`
    )
    return response.data
  },

  /**
   * Reject employment.
   */
  async reject(personId: string, employmentId: string, reason: string): Promise<V2ApiResponse<V2Employment>> {
    const response = await api.post<V2ApiResponse<V2Employment>>(
      `${BASE_PATH}/${personId}/employments/${employmentId}/reject`,
      { reason }
    )
    return response.data
  },

  /**
   * End employment.
   */
  async end(personId: string, employmentId: string, endDate: string): Promise<V2ApiResponse<V2Employment>> {
    const response = await api.post<V2ApiResponse<V2Employment>>(
      `${BASE_PATH}/${personId}/employments/${employmentId}/end`,
      { end_date: endDate }
    )
    return response.data
  },
}

// =====================================================
// References
// =====================================================

export const references = {
  /**
   * List person references.
   */
  async list(personId: string): Promise<V2ApiResponse<V2Reference[]>> {
    const response = await api.get<V2ApiResponse<V2Reference[]>>(`${BASE_PATH}/${personId}/references`)
    return response.data
  },

  /**
   * Add reference.
   */
  async create(personId: string, payload: V2ReferencePayload): Promise<V2ApiResponse<V2Reference>> {
    const response = await api.post<V2ApiResponse<V2Reference>>(
      `${BASE_PATH}/${personId}/references`,
      payload
    )
    return response.data
  },

  /**
   * Add personal reference.
   */
  async addPersonal(personId: string, payload: Omit<V2ReferencePayload, 'type'>): Promise<V2ApiResponse<V2Reference>> {
    const response = await api.post<V2ApiResponse<V2Reference>>(
      `${BASE_PATH}/${personId}/references/personal`,
      payload
    )
    return response.data
  },

  /**
   * Add work reference.
   */
  async addWork(personId: string, payload: Omit<V2ReferencePayload, 'type'>): Promise<V2ApiResponse<V2Reference>> {
    const response = await api.post<V2ApiResponse<V2Reference>>(
      `${BASE_PATH}/${personId}/references/work`,
      payload
    )
    return response.data
  },

  /**
   * Get references by type.
   */
  async getByType(personId: string, type: V2ReferenceType): Promise<V2ApiResponse<V2Reference[]>> {
    const response = await api.get<V2ApiResponse<V2Reference[]>>(
      `${BASE_PATH}/${personId}/references/type/${type}`
    )
    return response.data
  },

  /**
   * Get verified references.
   */
  async getVerified(personId: string): Promise<V2ApiResponse<V2Reference[]>> {
    const response = await api.get<V2ApiResponse<V2Reference[]>>(
      `${BASE_PATH}/${personId}/references/verified`
    )
    return response.data
  },

  /**
   * Get pending references.
   */
  async getPending(personId: string): Promise<V2ApiResponse<V2Reference[]>> {
    const response = await api.get<V2ApiResponse<V2Reference[]>>(
      `${BASE_PATH}/${personId}/references/pending`
    )
    return response.data
  },

  /**
   * Get reference summary.
   */
  async getSummary(personId: string): Promise<V2ApiResponse<Record<string, number>>> {
    const response = await api.get<V2ApiResponse<Record<string, number>>>(
      `${BASE_PATH}/${personId}/references/summary`
    )
    return response.data
  },

  /**
   * Check if phone exists in references.
   */
  async phoneExists(personId: string, phone: string): Promise<V2ApiResponse<{ exists: boolean }>> {
    const response = await api.post<V2ApiResponse<{ exists: boolean }>>(
      `${BASE_PATH}/${personId}/references/phone-exists`,
      { phone }
    )
    return response.data
  },

  /**
   * Check if has required references.
   */
  async hasRequired(
    personId: string,
    required: { personal: number; work: number }
  ): Promise<V2ApiResponse<{ met: boolean; missing: { personal: number; work: number } }>> {
    const response = await api.post<V2ApiResponse<{ met: boolean; missing: { personal: number; work: number } }>>(
      `${BASE_PATH}/${personId}/references/has-required`,
      required
    )
    return response.data
  },

  /**
   * Bulk verify references.
   */
  async bulkVerify(personId: string, referenceIds: string[]): Promise<V2ApiResponse<{ verified: number }>> {
    const response = await api.post<V2ApiResponse<{ verified: number }>>(
      `${BASE_PATH}/${personId}/references/bulk-verify`,
      { reference_ids: referenceIds }
    )
    return response.data
  },

  /**
   * Get reference by ID.
   */
  async get(personId: string, referenceId: string): Promise<V2ApiResponse<V2Reference>> {
    const response = await api.get<V2ApiResponse<V2Reference>>(
      `${BASE_PATH}/${personId}/references/${referenceId}`
    )
    return response.data
  },

  /**
   * Update reference.
   */
  async update(
    personId: string,
    referenceId: string,
    payload: Partial<V2ReferencePayload>
  ): Promise<V2ApiResponse<V2Reference>> {
    const response = await api.put<V2ApiResponse<V2Reference>>(
      `${BASE_PATH}/${personId}/references/${referenceId}`,
      payload
    )
    return response.data
  },

  /**
   * Delete reference.
   */
  async remove(personId: string, referenceId: string): Promise<V2ApiResponse<{ message: string }>> {
    const response = await api.delete<V2ApiResponse<{ message: string }>>(
      `${BASE_PATH}/${personId}/references/${referenceId}`
    )
    return response.data
  },

  /**
   * Verify reference.
   */
  async verify(personId: string, referenceId: string, notes?: string): Promise<V2ApiResponse<V2Reference>> {
    const response = await api.post<V2ApiResponse<V2Reference>>(
      `${BASE_PATH}/${personId}/references/${referenceId}/verify`,
      notes ? { notes } : undefined
    )
    return response.data
  },

  /**
   * Reject reference.
   */
  async reject(personId: string, referenceId: string, reason: string): Promise<V2ApiResponse<V2Reference>> {
    const response = await api.post<V2ApiResponse<V2Reference>>(
      `${BASE_PATH}/${personId}/references/${referenceId}/reject`,
      { reason }
    )
    return response.data
  },

  /**
   * Log contact attempt.
   */
  async logContactAttempt(
    personId: string,
    referenceId: string,
    notes?: string
  ): Promise<V2ApiResponse<V2Reference>> {
    const response = await api.post<V2ApiResponse<V2Reference>>(
      `${BASE_PATH}/${personId}/references/${referenceId}/contact-attempt`,
      notes ? { notes } : undefined
    )
    return response.data
  },
}

// =====================================================
// Bank Accounts
// =====================================================

export const bankAccounts = {
  /**
   * List person bank accounts.
   */
  async list(personId: string): Promise<V2ApiResponse<V2BankAccount[]>> {
    const response = await api.get<V2ApiResponse<V2BankAccount[]>>(`${BASE_PATH}/${personId}/bank-accounts`)
    return response.data
  },

  /**
   * Add bank account.
   */
  async create(personId: string, payload: V2BankAccountPayload): Promise<V2ApiResponse<V2BankAccount>> {
    const response = await api.post<V2ApiResponse<V2BankAccount>>(
      `${BASE_PATH}/${personId}/bank-accounts`,
      payload
    )
    return response.data
  },

  /**
   * Get primary bank account.
   */
  async getPrimary(personId: string): Promise<V2ApiResponse<V2BankAccount | null>> {
    const response = await api.get<V2ApiResponse<V2BankAccount | null>>(
      `${BASE_PATH}/${personId}/bank-accounts/primary`
    )
    return response.data
  },

  /**
   * Set primary bank account.
   */
  async setPrimary(personId: string, accountId: string): Promise<V2ApiResponse<V2BankAccount>> {
    const response = await api.post<V2ApiResponse<V2BankAccount>>(
      `${BASE_PATH}/${personId}/bank-accounts/primary`,
      { account_id: accountId }
    )
    return response.data
  },

  /**
   * Get accounts eligible for disbursement.
   */
  async getForDisbursement(personId: string): Promise<V2ApiResponse<V2BankAccount[]>> {
    const response = await api.get<V2ApiResponse<V2BankAccount[]>>(
      `${BASE_PATH}/${personId}/bank-accounts/for-disbursement`
    )
    return response.data
  },

  /**
   * Check if can receive disbursement.
   */
  async canReceiveDisbursement(personId: string): Promise<V2ApiResponse<{ can_receive: boolean }>> {
    const response = await api.get<V2ApiResponse<{ can_receive: boolean }>>(
      `${BASE_PATH}/${personId}/bank-accounts/can-receive-disbursement`
    )
    return response.data
  },

  /**
   * Get bank account summary.
   */
  async getSummary(personId: string): Promise<V2ApiResponse<Record<string, unknown>>> {
    const response = await api.get<V2ApiResponse<Record<string, unknown>>>(
      `${BASE_PATH}/${personId}/bank-accounts/summary`
    )
    return response.data
  },

  /**
   * Check if has verified accounts.
   */
  async hasVerified(personId: string): Promise<V2ApiResponse<{ verified: boolean }>> {
    const response = await api.get<V2ApiResponse<{ verified: boolean }>>(
      `${BASE_PATH}/${personId}/bank-accounts/has-verified`
    )
    return response.data
  },

  /**
   * Get bank account by ID.
   */
  async get(personId: string, accountId: string): Promise<V2ApiResponse<V2BankAccount>> {
    const response = await api.get<V2ApiResponse<V2BankAccount>>(
      `${BASE_PATH}/${personId}/bank-accounts/${accountId}`
    )
    return response.data
  },

  /**
   * Update bank account.
   */
  async update(
    personId: string,
    accountId: string,
    payload: Partial<V2BankAccountPayload>
  ): Promise<V2ApiResponse<V2BankAccount>> {
    const response = await api.put<V2ApiResponse<V2BankAccount>>(
      `${BASE_PATH}/${personId}/bank-accounts/${accountId}`,
      payload
    )
    return response.data
  },

  /**
   * Delete bank account.
   */
  async remove(personId: string, accountId: string): Promise<V2ApiResponse<{ message: string }>> {
    const response = await api.delete<V2ApiResponse<{ message: string }>>(
      `${BASE_PATH}/${personId}/bank-accounts/${accountId}`
    )
    return response.data
  },

  /**
   * Set as primary.
   */
  async setAsPrimary(personId: string, accountId: string): Promise<V2ApiResponse<V2BankAccount>> {
    const response = await api.post<V2ApiResponse<V2BankAccount>>(
      `${BASE_PATH}/${personId}/bank-accounts/${accountId}/set-primary`
    )
    return response.data
  },

  /**
   * Verify bank account.
   */
  async verify(personId: string, accountId: string): Promise<V2ApiResponse<V2BankAccount>> {
    const response = await api.post<V2ApiResponse<V2BankAccount>>(
      `${BASE_PATH}/${personId}/bank-accounts/${accountId}/verify`
    )
    return response.data
  },

  /**
   * Unverify bank account.
   */
  async unverify(personId: string, accountId: string): Promise<V2ApiResponse<V2BankAccount>> {
    const response = await api.post<V2ApiResponse<V2BankAccount>>(
      `${BASE_PATH}/${personId}/bank-accounts/${accountId}/unverify`
    )
    return response.data
  },

  /**
   * Deactivate bank account.
   */
  async deactivate(personId: string, accountId: string): Promise<V2ApiResponse<V2BankAccount>> {
    const response = await api.post<V2ApiResponse<V2BankAccount>>(
      `${BASE_PATH}/${personId}/bank-accounts/${accountId}/deactivate`
    )
    return response.data
  },

  /**
   * Reactivate bank account.
   */
  async reactivate(personId: string, accountId: string): Promise<V2ApiResponse<V2BankAccount>> {
    const response = await api.post<V2ApiResponse<V2BankAccount>>(
      `${BASE_PATH}/${personId}/bank-accounts/${accountId}/reactivate`
    )
    return response.data
  },

  /**
   * Close bank account.
   */
  async close(personId: string, accountId: string): Promise<V2ApiResponse<V2BankAccount>> {
    const response = await api.post<V2ApiResponse<V2BankAccount>>(
      `${BASE_PATH}/${personId}/bank-accounts/${accountId}/close`
    )
    return response.data
  },

  /**
   * Freeze bank account.
   */
  async freeze(personId: string, accountId: string): Promise<V2ApiResponse<V2BankAccount>> {
    const response = await api.post<V2ApiResponse<V2BankAccount>>(
      `${BASE_PATH}/${personId}/bank-accounts/${accountId}/freeze`
    )
    return response.data
  },
}

// =====================================================
// Utilities
// =====================================================

/**
 * Validate CLABE account number.
 */
export async function validateClabe(clabe: string): Promise<V2ApiResponse<V2ClabeValidationResult>> {
  const response = await api.post<V2ApiResponse<V2ClabeValidationResult>>(
    `${BASE_PATH}/validate-clabe`,
    { clabe }
  )
  return response.data
}

/**
 * Get bank name by code.
 */
export async function getBankName(code: string): Promise<V2ApiResponse<{ name: string }>> {
  const response = await api.get<V2ApiResponse<{ name: string }>>(`${BASE_PATH}/bank/${code}`)
  return response.data
}

export default {
  list,
  create,
  get,
  update,
  remove,
  getStatistics,
  getSummary,
  findByCurp,
  findByRfc,
  updateKycStatus,
  recalculateCompleteness,
  identifications,
  addresses,
  employments,
  references,
  bankAccounts,
  validateClabe,
  getBankName,
}
