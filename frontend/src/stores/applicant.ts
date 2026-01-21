import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { v2 } from '@/services/v2'
import type { BankAccount, Reference } from '@/types'
import { logger } from '@/utils/logger'
import { useAsyncAction } from '@/composables'

const log = logger.child('ApplicantStore')

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

export const useApplicantStore = defineStore('applicant', () => {
  // State
  const references = ref<Reference[]>([])

  // Bank account operations (all use V2)
  const { execute: executeLoadBankAccounts } = useAsyncAction(
    async () => {
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
      const response = await v2.applicant.profile.createBankAccount({
        clabe: data.clabe || '',
        holder_name: data.holder_name || '',
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
      await v2.applicant.profile.setPrimaryBankAccount(accountId)
    },
    {
      onError: (e) => log.error('Failed to set primary bank account', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeDeleteBankAccount } = useAsyncAction(
    async (accountId: string) => {
      await v2.applicant.profile.deleteBankAccount(accountId)
    },
    {
      onError: (e) => log.error('Failed to delete bank account', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeValidateClabe } = useAsyncAction(
    async (clabe: string) => {
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

  // Profile photo operations (all use V2)
  const { execute: executeUploadProfilePhoto } = useAsyncAction(
    async (params: { applicationId: string; file: File }) => {
      const response = await v2.applicant.document.upload(params.file, 'SELFIE', {
        metadata: { application_id: params.applicationId }
      })
      return response.data?.document?.id || ''
    },
    {
      onError: (e) => log.error('Failed to upload profile photo', { error: e.message }),
      rethrow: true
    }
  )

  const { execute: executeGetProfilePhotoUrl } = useAsyncAction(
    async (_applicationId: string) => {
      const listResponse = await v2.applicant.document.list({ type: 'SELFIE' })
      const selfieDoc = listResponse.data?.documents?.find(doc => doc.type === 'SELFIE')
      if (selfieDoc) {
        const blob = await v2.applicant.document.stream(selfieDoc.id)
        const isVerified = selfieDoc.status === 'APPROVED'
        return { url: URL.createObjectURL(blob), isVerified }
      }
      return { url: null, isVerified: false }
    },
    {
      onError: (e) => log.error('Failed to get profile photo', { error: e.message })
    }
  )

  // Reference operations (all use V2)
  const { execute: executeAddReference } = useAsyncAction(
    async (params: { applicationId: string; reference: Omit<Reference, 'id' | 'applicant_id' | 'application_id'> }) => {
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

  // Getters
  const hasMinimumReferences = computed(() => {
    const familyTypes = ['PARENT', 'SIBLING', 'SPOUSE', 'CHILD', 'UNCLE_AUNT', 'COUSIN', 'GRANDPARENT']
    const familyRef = references.value.some(r => familyTypes.includes(r.relationship))
    const nonFamilyRef = references.value.some(r => !familyTypes.includes(r.relationship))
    return familyRef && nonFamilyRef && references.value.length >= 2
  })

  // Public API wrappers
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
    references.value = []
  }

  return {
    // State
    references,
    // Getters
    hasMinimumReferences,
    // Actions
    loadBankAccounts,
    createBankAccount,
    setPrimaryBankAccount,
    deleteBankAccount,
    validateClabe,
    addReference,
    loadReferences,
    uploadProfilePhoto,
    getProfilePhotoUrl,
    reset
  }
})
