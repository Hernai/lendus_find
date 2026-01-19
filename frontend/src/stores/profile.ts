/**
 * V2 Applicant Profile Store
 *
 * Manages the authenticated applicant's profile using the V2 API.
 * Uses Pinia composition API with proper TypeScript types.
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import profileService from '@/services/v2/profile.service'
import type {
  V2Profile,
  V2ProfileSummary,
  V2PersonalData,
  V2Identifications,
  V2ProfileAddress,
  V2ProfileEmployment,
  V2ProfileBankAccount,
  V2ProfileReference,
  V2ClabeValidation,
} from '@/types/v2'
import type {
  UpdatePersonalDataPayload,
  UpdateIdentificationsPayload,
  UpdateAddressPayload,
  UpdateEmploymentPayload,
  UpdateBankAccountPayload,
  StoreReferencePayload,
  UpdateReferencePayload,
} from '@/services/v2/profile.service'

export const useProfileStore = defineStore('profile', () => {
  // =====================================================
  // State
  // =====================================================

  const profile = ref<V2Profile | null>(null)
  const summary = ref<V2ProfileSummary | null>(null)
  const isLoading = ref(false)
  const isSaving = ref(false)
  const error = ref<string | null>(null)

  // =====================================================
  // Getters
  // =====================================================

  const personalData = computed(() => profile.value?.personal_data ?? null)
  const identifications = computed(() => profile.value?.identifications ?? null)
  const address = computed(() => profile.value?.address ?? null)
  const employment = computed(() => profile.value?.employment ?? null)
  const bankAccount = computed(() => profile.value?.bank_account ?? null)
  const references = computed(() => profile.value?.references ?? [])

  const fullName = computed(() => profile.value?.personal_data.full_name ?? '')
  const profileCompleteness = computed(() => profile.value?.profile_completeness ?? 0)
  const missingData = computed(() => profile.value?.missing_data ?? [])
  const kycStatus = computed(() => profile.value?.kyc_status ?? 'PENDING')
  const isKycVerified = computed(() => profile.value?.is_kyc_verified ?? false)

  const isProfileComplete = computed(() => profileCompleteness.value >= 100)
  const hasAddress = computed(() => !!profile.value?.address)
  const hasEmployment = computed(() => !!profile.value?.employment)
  const hasBankAccount = computed(() => !!profile.value?.bank_account)

  const personalReferences = computed(() =>
    references.value.filter(r => r.type === 'PERSONAL')
  )
  const workReferences = computed(() =>
    references.value.filter(r => r.type === 'WORK')
  )
  const hasMinimumReferences = computed(() =>
    personalReferences.value.length >= 1 && workReferences.value.length >= 1
  )

  // =====================================================
  // Actions - Profile Loading
  // =====================================================

  async function loadProfile(): Promise<V2Profile | null> {
    isLoading.value = true
    error.value = null

    try {
      const response = await profileService.getProfile()
      if (response.success && response.data) {
        profile.value = response.data
        return response.data
      }
      error.value = response.message ?? 'Error al cargar el perfil'
      return null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function loadSummary(): Promise<V2ProfileSummary | null> {
    isLoading.value = true
    error.value = null

    try {
      const response = await profileService.getProfileSummary()
      if (response.success && response.data) {
        summary.value = response.data
        return response.data
      }
      error.value = response.message ?? 'Error al cargar el resumen'
      return null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      return null
    } finally {
      isLoading.value = false
    }
  }

  // =====================================================
  // Actions - Personal Data
  // =====================================================

  async function updatePersonalData(
    data: UpdatePersonalDataPayload
  ): Promise<V2PersonalData | null> {
    isSaving.value = true
    error.value = null

    try {
      const response = await profileService.updatePersonalData(data)
      if (response.success && response.data) {
        if (profile.value) {
          profile.value.personal_data = response.data.personal_data
          profile.value.profile_completeness = response.data.profile_completeness
        }
        return response.data.personal_data
      }
      error.value = response.message ?? 'Error al actualizar datos personales'
      return null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      throw e
    } finally {
      isSaving.value = false
    }
  }

  // =====================================================
  // Actions - Identifications
  // =====================================================

  async function updateIdentifications(
    data: UpdateIdentificationsPayload
  ): Promise<V2Identifications | null> {
    isSaving.value = true
    error.value = null

    try {
      const response = await profileService.updateIdentifications(data)
      if (response.success && response.data) {
        if (profile.value) {
          profile.value.identifications = response.data.identifications
          profile.value.profile_completeness = response.data.profile_completeness
        }
        return response.data.identifications
      }
      error.value = response.message ?? 'Error al actualizar identificaciones'
      return null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      throw e
    } finally {
      isSaving.value = false
    }
  }

  // =====================================================
  // Actions - Address
  // =====================================================

  async function getAddress(): Promise<V2ProfileAddress | null> {
    isLoading.value = true
    error.value = null

    try {
      const response = await profileService.getAddress()
      if (response.success) {
        if (profile.value) {
          profile.value.address = response.data ?? null
        }
        return response.data ?? null
      }
      error.value = response.message ?? 'Error al cargar dirección'
      return null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function updateAddress(
    data: UpdateAddressPayload
  ): Promise<V2ProfileAddress | null> {
    isSaving.value = true
    error.value = null

    try {
      const response = await profileService.updateAddress(data)
      if (response.success && response.data) {
        if (profile.value) {
          profile.value.address = response.data.address
          profile.value.profile_completeness = response.data.profile_completeness
        }
        return response.data.address
      }
      error.value = response.message ?? 'Error al actualizar dirección'
      return null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      throw e
    } finally {
      isSaving.value = false
    }
  }

  // =====================================================
  // Actions - Employment
  // =====================================================

  async function getEmployment(): Promise<V2ProfileEmployment | null> {
    isLoading.value = true
    error.value = null

    try {
      const response = await profileService.getEmployment()
      if (response.success) {
        if (profile.value) {
          profile.value.employment = response.data ?? null
        }
        return response.data ?? null
      }
      error.value = response.message ?? 'Error al cargar empleo'
      return null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function updateEmployment(
    data: UpdateEmploymentPayload
  ): Promise<V2ProfileEmployment | null> {
    isSaving.value = true
    error.value = null

    try {
      const response = await profileService.updateEmployment(data)
      if (response.success && response.data) {
        if (profile.value) {
          profile.value.employment = response.data.employment
          profile.value.profile_completeness = response.data.profile_completeness
        }
        return response.data.employment
      }
      error.value = response.message ?? 'Error al actualizar empleo'
      return null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      throw e
    } finally {
      isSaving.value = false
    }
  }

  // =====================================================
  // Actions - Bank Account
  // =====================================================

  async function getBankAccount(): Promise<V2ProfileBankAccount | null> {
    isLoading.value = true
    error.value = null

    try {
      const response = await profileService.getBankAccount()
      if (response.success) {
        if (profile.value) {
          profile.value.bank_account = response.data ?? null
        }
        return response.data ?? null
      }
      error.value = response.message ?? 'Error al cargar cuenta bancaria'
      return null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      return null
    } finally {
      isLoading.value = false
    }
  }

  async function updateBankAccount(
    data: UpdateBankAccountPayload
  ): Promise<{ id: string; bank_name: string; clabe_masked: string } | null> {
    isSaving.value = true
    error.value = null

    try {
      const response = await profileService.updateBankAccount(data)
      if (response.success && response.data) {
        // Reload full profile to get updated bank account
        await loadProfile()
        return response.data.bank_account
      }
      error.value = response.message ?? 'Error al actualizar cuenta bancaria'
      return null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      throw e
    } finally {
      isSaving.value = false
    }
  }

  async function validateClabe(clabe: string): Promise<V2ClabeValidation | null> {
    try {
      const response = await profileService.validateClabe(clabe)
      if (response.success && response.data) {
        return response.data
      }
      return null
    } catch {
      return null
    }
  }

  // =====================================================
  // Actions - References
  // =====================================================

  async function loadReferences(): Promise<V2ProfileReference[]> {
    isLoading.value = true
    error.value = null

    try {
      const response = await profileService.listReferences()
      if (response.success && response.data) {
        if (profile.value) {
          profile.value.references = response.data
        }
        return response.data
      }
      error.value = response.message ?? 'Error al cargar referencias'
      return []
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      return []
    } finally {
      isLoading.value = false
    }
  }

  async function addReference(
    data: StoreReferencePayload
  ): Promise<V2ProfileReference | null> {
    isSaving.value = true
    error.value = null

    try {
      const response = await profileService.addReference(data)
      if (response.success && response.data) {
        if (profile.value) {
          profile.value.references.push(response.data)
        }
        return response.data
      }
      error.value = response.message ?? 'Error al agregar referencia'
      return null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      throw e
    } finally {
      isSaving.value = false
    }
  }

  async function updateReference(
    referenceId: string,
    data: UpdateReferencePayload
  ): Promise<V2ProfileReference | null> {
    isSaving.value = true
    error.value = null

    try {
      const response = await profileService.updateReference(referenceId, data)
      if (response.success && response.data) {
        if (profile.value) {
          const index = profile.value.references.findIndex(r => r.id === referenceId)
          if (index !== -1) {
            profile.value.references[index] = response.data
          }
        }
        return response.data
      }
      error.value = response.message ?? 'Error al actualizar referencia'
      return null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      throw e
    } finally {
      isSaving.value = false
    }
  }

  async function deleteReference(referenceId: string): Promise<boolean> {
    isSaving.value = true
    error.value = null

    try {
      const response = await profileService.deleteReference(referenceId)
      if (response.success) {
        if (profile.value) {
          profile.value.references = profile.value.references.filter(
            r => r.id !== referenceId
          )
        }
        return true
      }
      error.value = response.message ?? 'Error al eliminar referencia'
      return false
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      throw e
    } finally {
      isSaving.value = false
    }
  }

  // =====================================================
  // Actions - Signature
  // =====================================================

  async function saveSignature(signature: string): Promise<string | null> {
    isSaving.value = true
    error.value = null

    try {
      const response = await profileService.saveSignature(signature)
      if (response.success && response.data) {
        return response.data.signed_at
      }
      error.value = response.message ?? 'Error al guardar firma'
      return null
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Error desconocido'
      throw e
    } finally {
      isSaving.value = false
    }
  }

  // =====================================================
  // Actions - Reset
  // =====================================================

  function reset(): void {
    profile.value = null
    summary.value = null
    error.value = null
    isLoading.value = false
    isSaving.value = false
  }

  // =====================================================
  // Return Store
  // =====================================================

  return {
    // State
    profile,
    summary,
    isLoading,
    isSaving,
    error,

    // Getters
    personalData,
    identifications,
    address,
    employment,
    bankAccount,
    references,
    fullName,
    profileCompleteness,
    missingData,
    kycStatus,
    isKycVerified,
    isProfileComplete,
    hasAddress,
    hasEmployment,
    hasBankAccount,
    personalReferences,
    workReferences,
    hasMinimumReferences,

    // Actions
    loadProfile,
    loadSummary,
    updatePersonalData,
    updateIdentifications,
    getAddress,
    updateAddress,
    getEmployment,
    updateEmployment,
    getBankAccount,
    updateBankAccount,
    validateClabe,
    loadReferences,
    addReference,
    updateReference,
    deleteReference,
    saveSignature,
    reset,
  }
})
