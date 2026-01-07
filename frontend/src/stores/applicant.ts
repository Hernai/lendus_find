import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/services/api'
import type {
  Applicant,
  Address,
  EmploymentRecord,
  BankAccount,
  Reference
} from '@/types'

interface ApplicantResponse {
  data: Applicant
}

interface AddressResponse {
  data: Address
}

interface AddressListResponse {
  data: Address[]
}

interface BankAccountResponse {
  data: BankAccount
}

interface BankAccountListResponse {
  data: BankAccount[]
}

export const useApplicantStore = defineStore('applicant', () => {
  // State
  const applicant = ref<Applicant | null>(null)
  const references = ref<Reference[]>([])
  const isLoading = ref(false)
  const isSaving = ref(false)

  // Getters
  const fullName = computed(() => {
    if (!applicant.value) return ''
    return applicant.value.full_name || `${applicant.value.first_name} ${applicant.value.last_name_1} ${applicant.value.last_name_2 || ''}`.trim()
  })

  const isKycVerified = computed(() => applicant.value?.kyc_status === 'VERIFIED')

  const hasMinimumReferences = computed(() => {
    const familyTypes = ['PADRE_MADRE', 'HERMANO', 'CONYUGE', 'HIJO', 'TIO', 'PRIMO', 'ABUELO']
    const familyRef = references.value.some(r => familyTypes.includes(r.relationship))
    const nonFamilyRef = references.value.some(r => !familyTypes.includes(r.relationship))
    return familyRef && nonFamilyRef && references.value.length >= 2
  })

  // Actions
  const loadApplicant = async () => {
    isLoading.value = true

    try {
      const response = await api.get<ApplicantResponse>('/applicant')
      applicant.value = response.data.data
    } catch (error: unknown) {
      // 404 means no applicant exists yet - that's ok
      if ((error as { response?: { status?: number } })?.response?.status !== 404) {
        console.error('Failed to load applicant:', error)
      }
      applicant.value = null
    } finally {
      isLoading.value = false
    }
  }

  const createApplicant = async (data: Partial<Applicant>) => {
    isSaving.value = true

    try {
      const response = await api.post<ApplicantResponse>('/applicant', data)
      applicant.value = response.data.data
      return applicant.value
    } catch (error) {
      console.error('Failed to create applicant:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  const updateApplicant = async (data: Partial<Applicant>) => {
    isSaving.value = true

    try {
      const response = await api.put<ApplicantResponse>('/applicant', data)
      applicant.value = response.data.data
      return applicant.value
    } catch (error) {
      console.error('Failed to update applicant:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  const updatePersonalData = async (data: {
    first_name: string
    last_name_1: string
    last_name_2?: string
    birth_date: string
    gender: 'M' | 'F'
    nationality: string
    marital_status: string
    curp?: string
    rfc?: string
  }) => {
    isSaving.value = true

    try {
      const response = await api.put<ApplicantResponse>('/applicant/personal-data', data)
      applicant.value = response.data.data
      return applicant.value
    } catch (error) {
      console.error('Failed to update personal data:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  const updateAddress = async (data: Partial<Address>) => {
    isSaving.value = true

    try {
      const response = await api.put<ApplicantResponse>('/applicant/address', data)
      applicant.value = response.data.data
      return applicant.value
    } catch (error) {
      console.error('Failed to update address:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  const loadAddresses = async (): Promise<Address[]> => {
    try {
      const response = await api.get<AddressListResponse>('/applicant/addresses')
      return response.data.data
    } catch (error) {
      console.error('Failed to load addresses:', error)
      return []
    }
  }

  const createAddress = async (data: Partial<Address>): Promise<Address> => {
    isSaving.value = true
    try {
      const response = await api.post<AddressResponse>('/applicant/addresses', data)
      return response.data.data
    } catch (error) {
      console.error('Failed to create address:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  const updateEmployment = async (data: Partial<EmploymentRecord>) => {
    isSaving.value = true

    try {
      const response = await api.put<ApplicantResponse>('/applicant/employment', data)
      applicant.value = response.data.data
      return applicant.value
    } catch (error) {
      console.error('Failed to update employment:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  const updateBankAccount = async (data: Partial<BankAccount>) => {
    isSaving.value = true

    try {
      const response = await api.put<ApplicantResponse>('/applicant/bank-account', data)
      applicant.value = response.data.data
      return applicant.value
    } catch (error) {
      console.error('Failed to update bank account:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  const loadBankAccounts = async (): Promise<BankAccount[]> => {
    try {
      const response = await api.get<BankAccountListResponse>('/applicant/bank-accounts')
      return response.data.data
    } catch (error) {
      console.error('Failed to load bank accounts:', error)
      return []
    }
  }

  const createBankAccount = async (data: Partial<BankAccount>): Promise<BankAccount> => {
    isSaving.value = true
    try {
      const response = await api.post<BankAccountResponse>('/applicant/bank-accounts', data)
      return response.data.data
    } catch (error) {
      console.error('Failed to create bank account:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  const validateClabe = async (clabe: string): Promise<{ valid: boolean; bank_name?: string; error?: string }> => {
    try {
      const response = await api.post<{ data: { valid: boolean; bank_name?: string; error?: string } }>(
        '/applicant/validate-clabe',
        { clabe }
      )
      return response.data.data
    } catch (error) {
      console.error('Failed to validate CLABE:', error)
      return { valid: false, error: 'Error validando CLABE' }
    }
  }

  const updateIdentification = async (rfc: string, curp: string, extraData?: Record<string, unknown>) => {
    isSaving.value = true

    try {
      const response = await api.put<ApplicantResponse>('/applicant/personal-data', {
        // Only send identification fields (personal-data is flexible)
        curp,
        rfc,
        // Include any extra INE/passport data
        ...extraData
      })
      applicant.value = response.data.data
      return applicant.value
    } catch (error) {
      console.error('Failed to update identification:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  const saveSignature = async (signatureData: string) => {
    isSaving.value = true

    try {
      const response = await api.post<ApplicantResponse>('/applicant/signature', {
        signature: signatureData
      })
      applicant.value = response.data.data
      return applicant.value
    } catch (error) {
      console.error('Failed to save signature:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  const addReference = async (applicationId: string, reference: Omit<Reference, 'id' | 'applicant_id' | 'application_id'>) => {
    isSaving.value = true

    try {
      const response = await api.post<{ data: Reference }>(`/applications/${applicationId}/references`, reference)
      references.value.push(response.data.data)
      return response.data.data
    } catch (error) {
      console.error('Failed to add reference:', error)
      throw error
    } finally {
      isSaving.value = false
    }
  }

  const loadReferences = async (applicationId: string) => {
    try {
      const response = await api.get<{ data: Reference[] }>(`/applications/${applicationId}/references`)
      references.value = response.data.data
      return references.value
    } catch (error) {
      console.error('Failed to load references:', error)
      return []
    }
  }

  const reset = () => {
    applicant.value = null
    references.value = []
    isLoading.value = false
    isSaving.value = false
  }

  return {
    // State
    applicant,
    references,
    isLoading,
    isSaving,
    // Getters
    fullName,
    isKycVerified,
    hasMinimumReferences,
    // Actions
    loadApplicant,
    createApplicant,
    updateApplicant,
    updatePersonalData,
    updateAddress,
    loadAddresses,
    createAddress,
    updateEmployment,
    updateBankAccount,
    loadBankAccounts,
    createBankAccount,
    validateClabe,
    updateIdentification,
    saveSignature,
    addReference,
    loadReferences,
    reset
  }
})
