import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type {
  Applicant,
  PersonalData,
  ContactInfo,
  Address,
  EmploymentInfo,
  Reference
} from '@/types'

export const useApplicantStore = defineStore('applicant', () => {
  // State
  const applicant = ref<Applicant | null>(null)
  const references = ref<Reference[]>([])
  const isLoading = ref(false)
  const isSaving = ref(false)

  // Getters
  const fullName = computed(() => {
    if (!applicant.value?.personal_data) return ''
    const { first_name, middle_name, last_name, second_last_name } = applicant.value.personal_data
    return [first_name, middle_name, last_name, second_last_name].filter(Boolean).join(' ')
  })

  const isKycVerified = computed(() => applicant.value?.kyc_status === 'VERIFIED')

  const hasMinimumReferences = computed(() => {
    const familyRef = references.value.some(r => r.relationship === 'FAMILY')
    const nonFamilyRef = references.value.some(r => r.relationship !== 'FAMILY')
    return familyRef && nonFamilyRef && references.value.length >= 2
  })

  // Actions
  const loadApplicant = async () => {
    isLoading.value = true

    try {
      // TODO: Replace with actual API call
      // const response = await api.get<Applicant>('/api/applicants/me')
      // applicant.value = response.data

      // Mock - no applicant data initially
      applicant.value = null
    } catch (error) {
      console.error('Failed to load applicant:', error)
    } finally {
      isLoading.value = false
    }
  }

  const updatePersonalData = async (data: PersonalData) => {
    isSaving.value = true

    try {
      // TODO: Replace with actual API call
      await new Promise(resolve => setTimeout(resolve, 300))

      if (!applicant.value) {
        applicant.value = {
          id: 'applicant-' + Date.now(),
          tenant_id: 'tenant-001',
          user_id: 'user-001',
          type: 'PERSONA_FISICA',
          rfc: '',
          curp: null,
          personal_data: data,
          contact_info: { phone: '', email: '' },
          address: {
            street: '',
            ext_number: '',
            neighborhood: '',
            postal_code: '',
            municipality: '',
            city: '',
            state: '',
            country: 'MX',
            housing_type: 'RENTADA',
            years_living: 0
          },
          employment_info: {
            employment_status: 'EMPLEADO',
            monthly_income: 0
          },
          kyc_status: 'PENDING',
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        }
      } else {
        applicant.value.personal_data = data
        applicant.value.updated_at = new Date().toISOString()
      }
    } finally {
      isSaving.value = false
    }
  }

  const updateContactInfo = async (data: ContactInfo) => {
    if (!applicant.value) return

    isSaving.value = true

    try {
      await new Promise(resolve => setTimeout(resolve, 300))
      applicant.value.contact_info = data
      applicant.value.updated_at = new Date().toISOString()
    } finally {
      isSaving.value = false
    }
  }

  const updateAddress = async (data: Address) => {
    if (!applicant.value) return

    isSaving.value = true

    try {
      await new Promise(resolve => setTimeout(resolve, 300))
      applicant.value.address = data
      applicant.value.updated_at = new Date().toISOString()
    } finally {
      isSaving.value = false
    }
  }

  const updateEmploymentInfo = async (data: EmploymentInfo) => {
    if (!applicant.value) return

    isSaving.value = true

    try {
      await new Promise(resolve => setTimeout(resolve, 300))
      applicant.value.employment_info = data
      applicant.value.updated_at = new Date().toISOString()
    } finally {
      isSaving.value = false
    }
  }

  const updateIdentification = async (rfc: string, curp: string) => {
    if (!applicant.value) return

    isSaving.value = true

    try {
      await new Promise(resolve => setTimeout(resolve, 300))
      applicant.value.rfc = rfc
      applicant.value.curp = curp
      applicant.value.updated_at = new Date().toISOString()
    } finally {
      isSaving.value = false
    }
  }

  const addReference = async (reference: Omit<Reference, 'id' | 'applicant_id'>) => {
    isSaving.value = true

    try {
      await new Promise(resolve => setTimeout(resolve, 300))

      const newReference: Reference = {
        id: 'ref-' + Date.now(),
        applicant_id: applicant.value?.id ?? '',
        ...reference
      }

      references.value.push(newReference)
    } finally {
      isSaving.value = false
    }
  }

  const removeReference = async (id: string) => {
    isSaving.value = true

    try {
      await new Promise(resolve => setTimeout(resolve, 300))
      references.value = references.value.filter(r => r.id !== id)
    } finally {
      isSaving.value = false
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
    updatePersonalData,
    updateContactInfo,
    updateAddress,
    updateEmploymentInfo,
    updateIdentification,
    addReference,
    removeReference,
    reset
  }
})
