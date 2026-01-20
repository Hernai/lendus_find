import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/services/api'
import { v2 } from '@/services/v2'
import type {
  Applicant,
  Address,
  EmploymentRecord,
  BankAccount,
  Reference
} from '@/types'
import { logger } from '@/utils/logger'
import { useAsyncAction } from '@/composables'

const log = logger.child('ApplicantStore')

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

// Simplified bank account type for V2 API responses
interface SimpleBankAccount {
  id: string
  bank_name: string
  clabe: string
  holder_name: string
  account_type: string
  is_primary: boolean
  is_verified: boolean
}

interface DocumentResponse {
  data: {
    id: string
    type: string
    url?: string
    signed_url?: string
  }
}

interface DocumentListResponse {
  data: Array<{
    id: string
    type: string
    status: string
    url?: string
    signed_url?: string
  }>
}

export const useApplicantStore = defineStore('applicant', () => {
  // State
  const applicant = ref<Applicant | null>(null)
  const references = ref<Reference[]>([])

  // Async actions
  const { execute: executeLoadApplicant, isLoading } = useAsyncAction(
    async () => {
      const response = await api.get<ApplicantResponse>('/applicant')
      applicant.value = response.data.data
      return applicant.value
    },
    {
      onError: (e) => {
        const axiosError = e as unknown as { response?: { status?: number } }
        if (axiosError.response?.status !== 404) {
          log.error('Failed to load applicant', { error: e.message })
        }
        applicant.value = null
      }
    }
  )

  const { execute: executeCreateApplicant, isLoading: isCreating } = useAsyncAction(
    async (data: Partial<Applicant>) => {
      const response = await api.post<ApplicantResponse>('/applicant', data)
      applicant.value = response.data.data
      return applicant.value
    },
    {
      onError: (e) => log.error('Failed to create applicant', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeUpdateApplicant, isLoading: isUpdating } = useAsyncAction(
    async (data: Partial<Applicant>) => {
      const response = await api.put<ApplicantResponse>('/applicant', data)
      applicant.value = response.data.data
      return applicant.value
    },
    {
      onError: (e) => log.error('Failed to update applicant', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeUpdatePersonalData } = useAsyncAction(
    async (data: {
      first_name: string
      last_name_1: string
      last_name_2?: string
      birth_date: string
      birth_state?: string
      gender: 'M' | 'F'
      nationality: string
      marital_status: string
      curp?: string
      rfc?: string
    }) => {
      const response = await api.put<ApplicantResponse>('/applicant/personal-data', data)
      applicant.value = response.data.data
      return applicant.value
    },
    {
      onError: (e) => log.error('Failed to update personal data', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeUpdateAddress } = useAsyncAction(
    async (data: Partial<Address>) => {
      const response = await api.put<ApplicantResponse>('/applicant/address', data)
      applicant.value = response.data.data
      return applicant.value
    },
    {
      onError: (e) => log.error('Failed to update address', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeLoadAddresses } = useAsyncAction(
    async () => {
      const response = await api.get<AddressListResponse>('/applicant/addresses')
      return response.data.data
    },
    {
      onError: (e) => log.error('Failed to load addresses', { error: e.message })
    }
  )

  const { execute: executeCreateAddress } = useAsyncAction(
    async (data: Partial<Address>) => {
      const response = await api.post<AddressResponse>('/applicant/addresses', data)
      return response.data.data
    },
    {
      onError: (e) => log.error('Failed to create address', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeUpdateEmployment } = useAsyncAction(
    async (data: Partial<EmploymentRecord>) => {
      const response = await api.put<ApplicantResponse>('/applicant/employment', data)
      applicant.value = response.data.data
      return applicant.value
    },
    {
      onError: (e) => log.error('Failed to update employment', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeUpdateBankAccount } = useAsyncAction(
    async (data: Partial<BankAccount>) => {
      const response = await api.put<ApplicantResponse>('/applicant/bank-account', data)
      applicant.value = response.data.data
      return applicant.value
    },
    {
      onError: (e) => log.error('Failed to update bank account', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeLoadBankAccounts } = useAsyncAction(
    async () => {
      // Use V2 profile API
      const response = await v2.applicant.profile.listBankAccounts()
      if (response.data?.bank_accounts) {
        return response.data.bank_accounts.map(ba => ({
          id: ba.id,
          bank_name: ba.bank_name,
          clabe: ba.clabe,
          holder_name: ba.holder_name,
          account_type: ba.account_type,
          is_primary: ba.is_primary,
          is_verified: ba.is_verified,
        }))
      }
      return []
    },
    {
      onError: (e) => log.error('Failed to load bank accounts', { error: e.message })
    }
  )

  const { execute: executeCreateBankAccount } = useAsyncAction(
    async (data: Partial<BankAccount>) => {
      // Use V2 profile API
      const response = await v2.applicant.profile.createBankAccount({
        clabe: data.clabe || '',
        holder_name: data.holder_name || '',
        // Account type from backend BankAccountType enum
        account_type: data.account_type,
      })
      if (response.data?.bank_account) {
        const ba = response.data.bank_account
        return {
          id: ba.id,
          bank_name: ba.bank_name,
          clabe: ba.clabe,
          holder_name: ba.holder_name,
          account_type: ba.account_type,
          is_primary: ba.is_primary,
          is_verified: ba.is_verified,
        }
      }
      throw new Error('Failed to create bank account')
    },
    {
      onError: (e) => log.error('Failed to create bank account', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeSetPrimaryBankAccount } = useAsyncAction(
    async (accountId: string) => {
      // Use V2 profile API
      await v2.applicant.profile.setPrimaryBankAccount(accountId)
    },
    {
      onError: (e) => log.error('Failed to set primary bank account', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeDeleteBankAccount } = useAsyncAction(
    async (accountId: string) => {
      // Use V2 profile API
      await v2.applicant.profile.deleteBankAccount(accountId)
    },
    {
      onError: (e) => log.error('Failed to delete bank account', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeValidateClabe } = useAsyncAction(
    async (clabe: string) => {
      // Use V2 profile API
      const response = await v2.applicant.profile.validateClabe(clabe)
      if (response.data) {
        return {
          valid: response.data.is_valid,
          bank_name: response.data.bank_name ?? undefined,
        }
      }
      return { valid: false, error: 'Error validando CLABE' }
    },
    {
      onError: (e) => log.error('Failed to validate CLABE', { error: e.message })
    }
  )

  const { execute: executeUpdateIdentification } = useAsyncAction(
    async (params: { rfc: string; curp: string; extraData?: Record<string, unknown> }) => {
      const response = await api.put<ApplicantResponse>('/applicant/personal-data', {
        curp: params.curp,
        rfc: params.rfc,
        ...params.extraData
      })
      applicant.value = response.data.data
      return applicant.value
    },
    {
      onError: (e) => log.error('Failed to update identification', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeSaveSignature } = useAsyncAction(
    async (signatureData: string) => {
      // Use V2 profile API for signature
      const response = await v2.applicant.profile.saveSignature(signatureData)
      // V2 returns { signed_at: string }, not the full applicant
      // Reload applicant to get updated state
      if (response.success) {
        await executeLoadApplicant()
      }
      return applicant.value
    },
    {
      onError: (e) => log.error('Failed to save signature', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeAddReference } = useAsyncAction(
    async (params: { applicationId: string; reference: Omit<Reference, 'id' | 'applicant_id' | 'application_id'> }) => {
      // Use V2 profile API for references
      const refType = params.reference.type === 'WORK' ? 'WORK' : 'PERSONAL'
      const response = await v2.applicant.profile.addReference({
        type: refType,
        first_name: params.reference.first_name || '',
        last_name_1: params.reference.last_name_1 || '',
        last_name_2: params.reference.last_name_2,
        phone: params.reference.phone,
        relationship: params.reference.relationship,
      })
      if (response.data) {
        const newRef: Reference = {
          id: response.data.id,
          first_name: response.data.first_name || '',
          last_name_1: response.data.last_name_1 || '',
          last_name_2: response.data.last_name_2 || '',
          full_name: response.data.full_name || '',
          phone: response.data.phone || '',
          relationship: response.data.relationship || '',
          type: response.data.type as 'PERSONAL' | 'WORK',
        }
        references.value.push(newRef)
        return newRef
      }
      throw new Error('Failed to add reference')
    },
    {
      onError: (e) => log.error('Failed to add reference', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeLoadReferences } = useAsyncAction(
    async (_applicationId: string) => {
      // Use V2 profile API for references
      const response = await v2.applicant.profile.listReferences()
      if (response.data) {
        references.value = response.data.map(ref => ({
          id: ref.id,
          first_name: ref.first_name || '',
          last_name_1: ref.last_name_1 || '',
          last_name_2: ref.last_name_2 || '',
          full_name: ref.full_name || '',
          phone: ref.phone || '',
          relationship: ref.relationship || '',
          type: ref.type as 'PERSONAL' | 'WORK',
        }))
      }
      return references.value
    },
    {
      onError: (e) => log.error('Failed to load references', { error: e.message })
    }
  )

  const { execute: executeUploadProfilePhoto } = useAsyncAction(
    async (params: { applicationId: string; file: File }) => {
      // Use V2 API - applicationId kept for metadata reference
      const response = await v2.applicant.document.upload(params.file, 'SELFIE', {
        metadata: { application_id: params.applicationId }
      })

      // V2 doesn't return signed URL directly, return empty string
      // Caller should use download endpoint if URL is needed
      return response.data?.document?.id || ''
    },
    {
      onError: (e) => log.error('Failed to upload profile photo', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeGetProfilePhotoUrl } = useAsyncAction(
    async (_applicationId: string) => {
      // Use V2 API to list documents
      const listResponse = await v2.applicant.document.list({ type: 'SELFIE' })
      const selfieDoc = listResponse.data?.documents?.find(doc => doc.type === 'SELFIE')
      if (selfieDoc) {
        // Stream document content directly through API (no external storage URL)
        const blob = await v2.applicant.document.stream(selfieDoc.id)
        const isVerified = selfieDoc.status === 'APPROVED'
        // NOTE: Caller is responsible for calling URL.revokeObjectURL when done
        return { url: URL.createObjectURL(blob), isVerified }
      }
      return { url: null, isVerified: false }
    },
    {
      onError: (e) => log.error('Failed to get profile photo', { error: e.message })
    }
  )

  // Combined saving state for backwards compatibility
  const isSaving = computed(() =>
    isCreating.value ||
    isUpdating.value
  )

  // Getters
  const fullName = computed(() => {
    if (!applicant.value) return ''
    return applicant.value.full_name || `${applicant.value.first_name} ${applicant.value.last_name_1} ${applicant.value.last_name_2 || ''}`.trim()
  })

  const isKycVerified = computed(() => applicant.value?.kyc_status === 'VERIFIED')

  const hasMinimumReferences = computed(() => {
    const familyTypes = ['PARENT', 'SIBLING', 'SPOUSE', 'CHILD', 'UNCLE_AUNT', 'COUSIN', 'GRANDPARENT']
    const familyRef = references.value.some(r => familyTypes.includes(r.relationship))
    const nonFamilyRef = references.value.some(r => !familyTypes.includes(r.relationship))
    return familyRef && nonFamilyRef && references.value.length >= 2
  })

  // Public API wrappers
  const loadApplicant = async () => executeLoadApplicant()

  const createApplicant = async (data: Partial<Applicant>) => {
    const result = await executeCreateApplicant(data)
    if (!result) throw new Error('Failed to create applicant')
    return result
  }

  const updateApplicant = async (data: Partial<Applicant>) => {
    const result = await executeUpdateApplicant(data)
    if (!result) throw new Error('Failed to update applicant')
    return result
  }

  const updatePersonalData = async (data: Parameters<typeof executeUpdatePersonalData>[0]) => {
    const result = await executeUpdatePersonalData(data)
    if (!result) throw new Error('Failed to update personal data')
    return result
  }

  const updateAddress = async (data: Partial<Address>) => {
    const result = await executeUpdateAddress(data)
    if (!result) throw new Error('Failed to update address')
    return result
  }

  const loadAddresses = async (): Promise<Address[]> => {
    const result = await executeLoadAddresses()
    return result ?? []
  }

  const createAddress = async (data: Partial<Address>): Promise<Address> => {
    const result = await executeCreateAddress(data)
    if (!result) throw new Error('Failed to create address')
    return result
  }

  const updateEmployment = async (data: Partial<EmploymentRecord>) => {
    const result = await executeUpdateEmployment(data)
    if (!result) throw new Error('Failed to update employment')
    return result
  }

  const updateBankAccount = async (data: Partial<BankAccount>) => {
    const result = await executeUpdateBankAccount(data)
    if (!result) throw new Error('Failed to update bank account')
    return result
  }

  const loadBankAccounts = async (): Promise<SimpleBankAccount[]> => {
    const result = await executeLoadBankAccounts()
    return result ?? []
  }

  const createBankAccount = async (data: Partial<BankAccount>): Promise<SimpleBankAccount> => {
    const result = await executeCreateBankAccount(data)
    if (!result) throw new Error('Failed to create bank account')
    return result
  }

  const setPrimaryBankAccount = async (accountId: string): Promise<void> => {
    await executeSetPrimaryBankAccount(accountId)
  }

  const deleteBankAccount = async (accountId: string): Promise<void> => {
    await executeDeleteBankAccount(accountId)
  }

  const validateClabe = async (clabe: string): Promise<{ valid: boolean; bank_name?: string; error?: string }> => {
    const result = await executeValidateClabe(clabe)
    return result ?? { valid: false, error: 'Error validando CLABE' }
  }

  const updateIdentification = async (rfc: string, curp: string, extraData?: Record<string, unknown>) => {
    const result = await executeUpdateIdentification({ rfc, curp, extraData })
    if (!result) throw new Error('Failed to update identification')
    return result
  }

  const saveSignature = async (signatureData: string) => {
    const result = await executeSaveSignature(signatureData)
    if (!result) throw new Error('Failed to save signature')
    return result
  }

  const addReference = async (applicationId: string, reference: Omit<Reference, 'id' | 'applicant_id' | 'application_id'>) => {
    const result = await executeAddReference({ applicationId, reference })
    if (!result) throw new Error('Failed to add reference')
    return result
  }

  const loadReferences = async (applicationId: string) => {
    const result = await executeLoadReferences(applicationId)
    return result ?? []
  }

  const uploadProfilePhoto = async (applicationId: string, file: File): Promise<string> => {
    const result = await executeUploadProfilePhoto({ applicationId, file })
    if (!result) throw new Error('Failed to upload profile photo')
    return result
  }

  const getProfilePhotoUrl = async (applicationId: string): Promise<{ url: string | null; isVerified: boolean }> => {
    const result = await executeGetProfilePhotoUrl(applicationId)
    return result ?? { url: null, isVerified: false }
  }

  const reset = () => {
    applicant.value = null
    references.value = []
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
    setPrimaryBankAccount,
    deleteBankAccount,
    validateClabe,
    updateIdentification,
    saveSignature,
    addReference,
    loadReferences,
    uploadProfilePhoto,
    getProfilePhotoUrl,
    reset
  }
})
