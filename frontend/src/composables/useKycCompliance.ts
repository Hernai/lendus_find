import { ref, computed } from 'vue'
import { api } from '@/services/api'
import { logger } from '@/utils/logger'

const kycLogger = logger.child('KYC:Compliance')

export interface ComplianceMatch {
  name?: string
  score?: number
  list?: string
  [key: string]: unknown
}

export interface ComplianceResult {
  found: boolean
  matches: ComplianceMatch[]
  count: number
  warning?: string
}

export interface UseKycComplianceReturn {
  /** Whether compliance check is in progress */
  isChecking: import('vue').Ref<boolean>
  /** Error message from compliance operations */
  error: import('vue').Ref<string | null>
  /** OFAC check result */
  ofacResult: import('vue').Ref<ComplianceResult | null>
  /** PLD check result */
  pldResult: import('vue').Ref<ComplianceResult | null>
  /** Whether person is clear of OFAC sanctions */
  isOfacClear: import('vue').ComputedRef<boolean>
  /** Whether person is clear of PLD blacklists */
  isPldClear: import('vue').ComputedRef<boolean>
  /** Check OFAC (US sanctions) lists */
  checkOfac: (name: string, similarity?: number) => Promise<boolean>
  /** Check PLD (Mexican) blacklists */
  checkPldBlacklists: (name: string, curp?: string, similarity?: number) => Promise<boolean>
  /** Run all compliance checks */
  runAllChecks: (name: string, curp?: string) => Promise<{ ofacClear: boolean; pldClear: boolean }>
  /** Reset compliance state */
  reset: () => void
}

/**
 * Composable for KYC compliance checks (OFAC and PLD blacklists).
 * Handles anti-money laundering (AML) and sanctions screening.
 */
export function useKycCompliance(): UseKycComplianceReturn {
  const isChecking = ref(false)
  const error = ref<string | null>(null)
  const ofacResult = ref<ComplianceResult | null>(null)
  const pldResult = ref<ComplianceResult | null>(null)

  /**
   * Whether person is clear of OFAC sanctions.
   */
  const isOfacClear = computed(() => {
    return ofacResult.value?.found === false
  })

  /**
   * Whether person is clear of PLD blacklists.
   */
  const isPldClear = computed(() => {
    return pldResult.value?.found === false
  })

  /**
   * Check OFAC (US sanctions) lists.
   * Returns true if person is NOT found in sanctions lists.
   */
  const checkOfac = async (name: string, similarity: number = 80): Promise<boolean> => {
    kycLogger.debug('checkOfac called', { name })

    if (!name) {
      kycLogger.warn('No name available for OFAC check')
      error.value = 'Se requiere nombre para verificar OFAC'
      return false
    }

    isChecking.value = true
    error.value = null

    try {
      kycLogger.debug('Calling /kyc/ofac/check API...')
      const response = await api.post<{
        data: { found: boolean; matches: ComplianceMatch[]; count: number; warning?: string }
      }>('/kyc/ofac/check', {
        name,
        similarity
      })

      kycLogger.debug('OFAC response:', response.data)

      ofacResult.value = {
        found: response.data.data.found,
        matches: response.data.data.matches,
        count: response.data.data.count || 0,
        warning: response.data.data.warning
      }

      // If there's a warning (service unavailable), treat as not found
      if (response.data.data.warning) {
        kycLogger.warn('OFAC warning', { warning: response.data.data.warning })
      }

      return !response.data.data.found
    } catch (err: unknown) {
      kycLogger.error('Failed to check OFAC', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      error.value = errorResponse.response?.data?.message || 'Error al verificar OFAC'
      // Return true on error to not block validation (service might be unavailable)
      return true
    } finally {
      isChecking.value = false
    }
  }

  /**
   * Check PLD (Mexican) blacklists including PGR, PGJ, PEPs, SAT, etc.
   * Returns true if person is NOT found in blacklists.
   */
  const checkPldBlacklists = async (name: string, curp?: string, similarity: number = 90): Promise<boolean> => {
    kycLogger.debug('checkPldBlacklists called', { name, curp })

    if (!name) {
      kycLogger.warn('No name available for PLD check')
      error.value = 'Se requiere nombre para verificar listas negras'
      return false
    }

    isChecking.value = true
    error.value = null

    try {
      kycLogger.debug('Calling /kyc/pld/check API...')
      const response = await api.post<{
        data: { found: boolean; matches: ComplianceMatch[]; count: number; warning?: string }
      }>('/kyc/pld/check', {
        name,
        curp: curp || undefined,
        similarity // Higher threshold for PLD to reduce false positives
      })

      kycLogger.debug('PLD response:', response.data)

      pldResult.value = {
        found: response.data.data.found,
        matches: response.data.data.matches,
        count: response.data.data.count || 0,
        warning: response.data.data.warning
      }

      // If there's a warning (service unavailable), treat as not found
      if (response.data.data.warning) {
        kycLogger.warn('PLD warning', { warning: response.data.data.warning })
      }

      return !response.data.data.found
    } catch (err: unknown) {
      kycLogger.error('Failed to check PLD blacklists', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      error.value = errorResponse.response?.data?.message || 'Error al verificar listas negras'
      // Return true on error to not block validation (service might be unavailable)
      return true
    } finally {
      isChecking.value = false
    }
  }

  /**
   * Run all compliance checks (OFAC and PLD).
   */
  const runAllChecks = async (name: string, curp?: string): Promise<{ ofacClear: boolean; pldClear: boolean }> => {
    kycLogger.debug('Running all compliance checks', { name, curp })

    // Run both checks in parallel
    const [pldClear, ofacClear] = await Promise.all([
      checkPldBlacklists(name, curp),
      checkOfac(name)
    ])

    return { ofacClear, pldClear }
  }

  /**
   * Reset compliance state.
   */
  const reset = (): void => {
    isChecking.value = false
    error.value = null
    ofacResult.value = null
    pldResult.value = null
  }

  return {
    isChecking,
    error,
    ofacResult,
    pldResult,
    isOfacClear,
    isPldClear,
    checkOfac,
    checkPldBlacklists,
    runAllChecks,
    reset
  }
}
