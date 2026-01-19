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
      const response = await api.get<BankAccountListResponse>('/applicant/bank-accounts')
      return response.data.data
    },
    {
      onError: (e) => log.error('Failed to load bank accounts', { error: e.message })
    }
  )

  const { execute: executeCreateBankAccount } = useAsyncAction(
    async (data: Partial<BankAccount>) => {
      const response = await api.post<BankAccountResponse>('/applicant/bank-accounts', data)
      return response.data.data
    },
    {
      onError: (e) => log.error('Failed to create bank account', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeSetPrimaryBankAccount } = useAsyncAction(
    async (accountId: string) => {
      await api.patch(`/applicant/bank-accounts/${accountId}/primary`)
    },
    {
      onError: (e) => log.error('Failed to set primary bank account', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeDeleteBankAccount } = useAsyncAction(
    async (accountId: string) => {
      await api.delete(`/applicant/bank-accounts/${accountId}`)
    },
    {
      onError: (e) => log.error('Failed to delete bank account', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeValidateClabe } = useAsyncAction(
    async (clabe: string) => {
      const response = await api.post<{ data: { valid: boolean; bank_name?: string; error?: string } }>(
        '/validate-clabe',
        { clabe }
      )
      return response.data.data
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
      const response = await api.post<ApplicantResponse>('/applicant/signature', {
        signature: signatureData
      })
      applicant.value = response.data.data
      return applicant.value
    },
    {
      onError: (e) => log.error('Failed to save signature', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeAddReference } = useAsyncAction(
    async (params: { applicationId: string; reference: Omit<Reference, 'id' | 'applicant_id' | 'application_id'> }) => {
      const response = await api.post<{ data: Reference }>(
        `/applications/${params.applicationId}/references`,
        params.reference
      )
      references.value.push(response.data.data)
      return response.data.data
    },
    {
      onError: (e) => log.error('Failed to add reference', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeLoadReferences } = useAsyncAction(
    async (applicationId: string) => {
      const response = await api.get<{ data: Reference[] }>(`/applications/${applicationId}/references`)
      references.value = response.data.data
      return references.value
    },
    {
      onError: (e) => log.error('Failed to load references', { error: e.message })
    }
  )

  const { execute: executeUploadProfilePhoto } = useAsyncAction(
    async (params: { applicationId: string; file: File }) => {
      const formData = new FormData()
      formData.append('file', params.file)
      formData.append('type', 'SELFIE')

      const response = await api.post<DocumentResponse>(
        `/applications/${params.applicationId}/documents`,
        formData
      )

      return response.data.data.signed_url || response.data.data.url || ''
    },
    {
      onError: (e) => log.error('Failed to upload profile photo', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeGetProfilePhotoUrl } = useAsyncAction(
    async (applicationId: string) => {
      const response = await api.get<DocumentListResponse>(`/applications/${applicationId}/documents`)
      const selfieDoc = response.data.data.find(doc => doc.type === 'SELFIE')
      if (selfieDoc) {
        const imageResponse = await api.get(`/documents/${selfieDoc.id}/download`, {
          responseType: 'blob'
        })
        const blob = new Blob([imageResponse.data as BlobPart], {
          type: imageResponse.headers['content-type'] || 'image/jpeg'
        })
        const isVerified = selfieDoc.status === 'APPROVED'
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

  const loadBankAccounts = async (): Promise<BankAccount[]> => {
    const result = await executeLoadBankAccounts()
    return result ?? []
  }

  const createBankAccount = async (data: Partial<BankAccount>): Promise<BankAccount> => {
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
