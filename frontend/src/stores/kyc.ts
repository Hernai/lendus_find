import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/services/api'
import { logger } from '@/utils/logger'

const kycLogger = logger.child('KYC')

// Types for KYC data
export interface KycLockedData {
  nombres: string | null
  apellido_paterno: string | null
  apellido_materno: string | null
  fecha_nacimiento: string | null
  sexo: 'H' | 'M' | null
  curp: string | null
  clave_elector: string | null
  vigencia: string | null
  entidad_nacimiento: string | null
  // INE additional fields
  ocr: string | null // Número OCR (13 dígitos)
  cic: string | null // CIC code
  identificador_ciudadano: string | null // ID Ciudadano
  direccion_ine: {
    calle: string | null
    colonia: string | null
    cp: string | null
    localidad: string | null
    ciudad: string | null
    municipio: string | null
    estado: string | null
  }
}

export interface KycValidations {
  ine_ocr: {
    success: boolean
    data?: Record<string, unknown>
    error?: string
  } | null
  ine_lista_nominal: {
    valid: boolean
    code?: string
    message?: string
  } | null
  curp_renapo: {
    valid: boolean
    data?: Record<string, unknown>
  } | null
  face_match: {
    score: number
    match: boolean
  } | null
  liveness: {
    passed: boolean
    score?: number
  } | null
  ofac: {
    found: boolean
    matches: unknown[]
    score: number
  } | null
}

export interface KycServicesResponse {
  data: {
    nubarium: {
      configured: boolean
      services: string[]
    }
  }
  birth_states: Record<string, string>
}

export interface IneValidationResponse {
  message: string
  ocr_data?: {
    // Note: Nubarium returns 'nombres' (plural) not 'nombre'
    nombres: string
    apellido_paterno: string
    apellido_materno: string
    curp: string
    fecha_nacimiento: string
    sexo: string
    calle: string
    colonia: string
    cp?: string
    localidad?: string
    ciudad?: string
    municipio?: string
    estado?: string
    clave_elector: string
    vigencia: string
    // Additional fields from Nubarium OCR
    ocr?: string // Número OCR (13 dígitos)
    cic?: string
    identificador_ciudadano?: string
    subtipo?: string
  }
  list_validation?: {
    valid: boolean
    code: string
    message: string
  }
  is_valid?: boolean
  validation_code?: string
}

export interface BiometricTokenResponse {
  message: string
  data: {
    token: string
    expires_in: number
    transaction_id: string
  }
}

export interface RfcValidationResponse {
  message: string
  valid: boolean // Valid flag at root level
  data: {
    rfc: string
    mensaje?: string
    informacion_adicional?: string
    razon_social?: string
    tipo_persona: 'M' | 'F' // M = Moral, F = Física
    tipo_persona_label: string
  }
}

// Verified field information
export interface VerifiedField {
  value: string
  method: string
  method_label: string
  verified_at: string
  metadata?: Record<string, unknown> | null
  is_locked?: boolean
}

// API response for verifications
export interface VerificationsResponse {
  data: {
    verifications: Array<{
      field: string
      field_label: string
      value: string
      method: string
      method_label: string
      is_verified: boolean
      is_locked: boolean
      status: string
      verified_at: string
      metadata?: Record<string, unknown> | null
      notes?: string | null
    }>
    verified_fields: Record<string, VerifiedField>
    summary: {
      personal_data: Record<string, VerifiedField>
      contact: Record<string, VerifiedField>
      address: Record<string, VerifiedField>
      kyc: Record<string, VerifiedField>
    }
    kyc_verified: boolean
    kyc_verified_at: string | null
  }
}

export const useKycStore = defineStore('kyc', () => {
  // State
  const verified = ref(false)
  const isConfigured = ref(false)
  const availableServices = ref<string[]>([])
  const birthStates = ref<Record<string, string>>({})
  const isLoading = ref(false)
  const isValidating = ref(false)
  const error = ref<string | null>(null)

  // Verified fields from backend
  const verifiedFields = ref<Record<string, VerifiedField>>({})
  const verificationsSummary = ref<{
    personal_data: Record<string, VerifiedField>
    contact: Record<string, VerifiedField>
    address: Record<string, VerifiedField>
    kyc: Record<string, VerifiedField>
  }>({
    personal_data: {},
    contact: {},
    address: {},
    kyc: {}
  })

  // Captured images (base64)
  const ineFrontImage = ref<string | null>(null)
  const ineBackImage = ref<string | null>(null)
  const selfieImage = ref<string | null>(null)

  // Locked data from INE OCR
  const lockedData = ref<KycLockedData>({
    nombres: null,
    apellido_paterno: null,
    apellido_materno: null,
    fecha_nacimiento: null,
    sexo: null,
    curp: null,
    clave_elector: null,
    vigencia: null,
    entidad_nacimiento: null,
    ocr: null,
    cic: null,
    identificador_ciudadano: null,
    direccion_ine: {
      calle: null,
      colonia: null,
      cp: null,
      localidad: null,
      ciudad: null,
      municipio: null,
      estado: null,
    }
  })

  // Validation results
  const validations = ref<KycValidations>({
    ine_ocr: null,
    ine_lista_nominal: null,
    curp_renapo: null,
    face_match: null,
    liveness: null,
    ofac: null,
  })

  // RFC validation result
  const rfcValidation = ref<{
    valid: boolean
    rfc: string
    razon_social?: string
    tipo_persona?: string
    error?: string
  } | null>(null)

  // Getters
  const hasNubarium = computed(() => isConfigured.value)

  const fullNameFromIne = computed(() => {
    if (!lockedData.value.nombres) return null
    const parts = [
      lockedData.value.nombres,
      lockedData.value.apellido_paterno,
      lockedData.value.apellido_materno
    ].filter(Boolean)
    return parts.join(' ')
  })

  const addressFromIne = computed(() => {
    const addr = lockedData.value.direccion_ine
    if (!addr.calle) return null
    // Build address with available fields, using localidad/ciudad as fallbacks
    const parts = [
      addr.calle,
      addr.colonia,
      addr.cp,
      addr.localidad || addr.ciudad, // localidad or ciudad as fallback
      addr.municipio,
      addr.estado
    ].filter(part => part && part.trim() !== '')
    return parts.length > 0 ? parts.join(', ') : null
  })

  const isIneValid = computed(() => {
    return validations.value.ine_lista_nominal?.valid === true
  })

  const isCurpValid = computed(() => {
    return validations.value.curp_renapo?.valid === true
  })

  const isFaceMatched = computed(() => {
    return validations.value.face_match?.match === true
  })

  const isLivenessPassed = computed(() => {
    return validations.value.liveness?.passed === true
  })

  const isOfacClear = computed(() => {
    return validations.value.ofac?.found === false
  })

  const allValidationsPassed = computed(() => {
    return isIneValid.value && isCurpValid.value && isOfacClear.value
  })

  const validationProgress = computed(() => {
    const checks = [
      { name: 'ine_ocr', label: 'Extrayendo datos (OCR)', done: validations.value.ine_ocr !== null },
      { name: 'ine_lista_nominal', label: 'Verificando lista nominal', done: validations.value.ine_lista_nominal !== null },
      { name: 'curp_renapo', label: 'Validando CURP con RENAPO', done: validations.value.curp_renapo !== null },
      { name: 'face_match', label: 'Comparando rostros', done: validations.value.face_match !== null },
      { name: 'liveness', label: 'Verificando prueba de vida', done: validations.value.liveness !== null },
      { name: 'ofac', label: 'Verificando OFAC', done: validations.value.ofac !== null },
    ]
    return checks
  })

  // Actions
  const checkServices = async () => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.get<KycServicesResponse>('/kyc/services')
      isConfigured.value = response.data.data.nubarium?.configured || false
      availableServices.value = response.data.data.nubarium?.services || []
      birthStates.value = response.data.birth_states || {}
      return isConfigured.value
    } catch (err) {
      kycLogger.error('Failed to check KYC services', err)
      isConfigured.value = false
      return false
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Test connection to the KYC service (Nubarium).
   * Forces a token refresh and validates credentials.
   */
  const testConnection = async (): Promise<{ success: boolean; message: string }> => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.post<{ success: boolean; message: string; configured: boolean }>('/kyc/test-connection')
      return {
        success: response.data.success,
        message: response.data.message
      }
    } catch (err: unknown) {
      kycLogger.error('Failed to test KYC connection', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      const message = errorResponse.response?.data?.message || 'Error al probar conexión'
      error.value = message
      return {
        success: false,
        message
      }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Force refresh the Nubarium JWT token.
   * Useful when the token has expired or API calls are failing with 401.
   */
  const refreshToken = async (): Promise<{ success: boolean; message: string }> => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.post<{ success: boolean; message: string }>('/kyc/refresh-token')
      return {
        success: response.data.success,
        message: response.data.message
      }
    } catch (err: unknown) {
      kycLogger.error('Failed to refresh KYC token', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      const message = errorResponse.response?.data?.message || 'Error al renovar token'
      error.value = message
      return {
        success: false,
        message
      }
    } finally {
      isLoading.value = false
    }
  }

  const setIneFrontImage = (image: string) => {
    ineFrontImage.value = image
  }

  const setIneBackImage = (image: string) => {
    ineBackImage.value = image
  }

  const setSelfieImage = (image: string) => {
    selfieImage.value = image
  }

  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const validateIne = async (_applicantId?: string) => {
    if (!ineFrontImage.value) {
      error.value = 'Se requiere la imagen frontal del INE'
      return false
    }

    isValidating.value = true
    error.value = null

    try {
      const response = await api.post<IneValidationResponse>('/kyc/ine/validate', {
        front_image: ineFrontImage.value,
        back_image: ineBackImage.value,
        validate_list: true
      })

      // Store OCR data
      if (response.data.ocr_data) {
        const ocr = response.data.ocr_data
        kycLogger.debug('OCR data received:', ocr)

        // Clean CURP - remove spaces if present
        const cleanCurp = ocr.curp ? ocr.curp.replace(/\s+/g, '') : null

        // Extract entidad de nacimiento from CURP (positions 12-13, 0-indexed: 11-12)
        // CURP format: AAAA YYMMDD SEXO EE XXX C
        // EE = Estado de nacimiento (2 chars at position 11-12)
        let entidadNacimiento: string | null = null
        if (cleanCurp && cleanCurp.length >= 13) {
          entidadNacimiento = cleanCurp.substring(11, 13).toUpperCase()
        }

        lockedData.value = {
          nombres: ocr.nombres || null, // Nubarium returns 'nombres' (plural)
          apellido_paterno: ocr.apellido_paterno || null,
          apellido_materno: ocr.apellido_materno || null,
          fecha_nacimiento: ocr.fecha_nacimiento || null,
          sexo: (ocr.sexo === 'H' || ocr.sexo === 'M') ? ocr.sexo : null,
          curp: cleanCurp,
          clave_elector: ocr.clave_elector || null,
          vigencia: ocr.vigencia || null,
          entidad_nacimiento: entidadNacimiento,
          // INE additional fields
          ocr: ocr.ocr || null,
          cic: ocr.cic || null,
          identificador_ciudadano: ocr.identificador_ciudadano || null,
          direccion_ine: {
            calle: ocr.calle || null,
            colonia: ocr.colonia || null,
            cp: ocr.cp || null,
            localidad: ocr.localidad || null,
            ciudad: ocr.ciudad || null,
            municipio: ocr.municipio || null,
            estado: ocr.estado || null,
          }
        }

        kycLogger.debug('lockedData set:', lockedData.value)

        validations.value.ine_ocr = {
          success: true,
          data: response.data.ocr_data as unknown as Record<string, unknown>
        }

        // NOTE: Verifications are now handled automatically by the backend
        // when calling /kyc/ine/validate - no need to call recordSingleVerification here
        kycLogger.debug('INE validated - backend handles verification records automatically')
      }

      // Store list validation
      if (response.data.list_validation) {
        validations.value.ine_lista_nominal = response.data.list_validation
        // NOTE: Backend handles this verification automatically
      }

      return response.data.is_valid === true
    } catch (err: unknown) {
      kycLogger.error('Failed to validate INE', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      error.value = errorResponse.response?.data?.message || 'Error al validar INE'
      validations.value.ine_ocr = {
        success: false,
        error: error.value
      }
      return false
    } finally {
      isValidating.value = false
    }
  }

  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const validateCurp = async (curp?: string, _applicantId?: string) => {
    const curpToValidate = curp || lockedData.value.curp
    if (!curpToValidate) {
      error.value = 'Se requiere CURP para validar'
      return false
    }

    isValidating.value = true
    error.value = null

    try {
      const response = await api.post<{ valid: boolean; data?: Record<string, unknown> }>('/kyc/curp/validate', {
        curp: curpToValidate
      })

      validations.value.curp_renapo = {
        valid: response.data.valid,
        data: response.data.data
      }

      // IMPORTANT: Use RENAPO data as source of truth for names
      // RENAPO is the official government registry and has accurate data
      // OCR from INE can have errors (e.g., "HERNAJ" instead of correct name)
      if (response.data.valid && response.data.data) {
        const renapoData = response.data.data as {
          nombres?: string
          apellido_paterno?: string
          apellido_materno?: string
          fecha_nacimiento?: string
          sexo?: string
        }

        kycLogger.debug('RENAPO data received:', renapoData)
        kycLogger.debug('Previous OCR data:', {
          nombres: lockedData.value.nombres,
          apellido_paterno: lockedData.value.apellido_paterno,
          apellido_materno: lockedData.value.apellido_materno
        })

        // Override OCR data with RENAPO data (official government source)
        if (renapoData.nombres) {
          lockedData.value.nombres = renapoData.nombres
          kycLogger.debug('Updated nombres from RENAPO:', renapoData.nombres)
        }
        if (renapoData.apellido_paterno) {
          lockedData.value.apellido_paterno = renapoData.apellido_paterno
          kycLogger.debug('Updated apellido_paterno from RENAPO:', renapoData.apellido_paterno)
        }
        if (renapoData.apellido_materno) {
          lockedData.value.apellido_materno = renapoData.apellido_materno
          kycLogger.debug('Updated apellido_materno from RENAPO:', renapoData.apellido_materno)
        }
        if (renapoData.fecha_nacimiento) {
          lockedData.value.fecha_nacimiento = renapoData.fecha_nacimiento
          kycLogger.debug('Updated fecha_nacimiento from RENAPO:', renapoData.fecha_nacimiento)
        }
        if (renapoData.sexo) {
          // RENAPO returns 'HOMBRE'/'MUJER', convert to 'H'/'M'
          const sexoNormalized = renapoData.sexo.toUpperCase().startsWith('H') ? 'H' : 'M'
          lockedData.value.sexo = sexoNormalized as 'H' | 'M'
          kycLogger.debug('Updated sexo from RENAPO:', sexoNormalized)
        }

        kycLogger.debug('Updated lockedData with RENAPO (official) data:', {
          nombres: lockedData.value.nombres,
          apellido_paterno: lockedData.value.apellido_paterno,
          apellido_materno: lockedData.value.apellido_materno
        })
      }

      // NOTE: Verifications are now handled automatically by the backend
      // when calling /kyc/curp/validate - no need to call recordSingleVerification here
      kycLogger.debug('CURP validated - backend handles verification records automatically')

      return response.data.valid
    } catch (err: unknown) {
      kycLogger.error('Failed to validate CURP', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      error.value = errorResponse.response?.data?.message || 'Error al validar CURP'
      validations.value.curp_renapo = {
        valid: false
      }
      return false
    } finally {
      isValidating.value = false
    }
  }

  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const validateRfc = async (rfc: string, _applicantId?: string): Promise<{ valid: boolean; razon_social?: string; error?: string }> => {
    if (!rfc || rfc.length < 12) {
      return { valid: false, error: 'RFC debe tener al menos 12 caracteres' }
    }

    isValidating.value = true
    error.value = null

    try {
      const response = await api.post<RfcValidationResponse>('/kyc/rfc/validate', {
        rfc: rfc.toUpperCase()
      })

      // valid is at root level, razon_social might be in data or use mensaje as fallback
      const isValid = response.data.valid
      const razonSocial = response.data.data.razon_social || response.data.data.mensaje || response.data.data.informacion_adicional

      const result = {
        valid: isValid,
        rfc: response.data.data.rfc,
        razon_social: razonSocial,
        tipo_persona: response.data.data.tipo_persona_label
      }

      rfcValidation.value = result

      // NOTE: Verifications are now handled automatically by the backend
      // when calling /kyc/rfc/validate - no need to call recordSingleVerification here
      kycLogger.debug('RFC validated - backend handles verification records automatically')

      return {
        valid: result.valid,
        razon_social: result.razon_social
      }
    } catch (err: unknown) {
      kycLogger.error('Failed to validate RFC', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      const errorMsg = errorResponse.response?.data?.message || 'Error al validar RFC'
      error.value = errorMsg

      rfcValidation.value = {
        valid: false,
        rfc: rfc,
        error: errorMsg
      }

      return { valid: false, error: errorMsg }
    } finally {
      isValidating.value = false
    }
  }

  const checkOfac = async (name?: string) => {
    kycLogger.debug('checkOfac called')
    const nameToCheck = name || fullNameFromIne.value

    kycLogger.debug('OFAC name to check:', nameToCheck)

    if (!nameToCheck) {
      kycLogger.warn('No name available for OFAC check')
      error.value = 'Se requiere nombre para verificar OFAC'
      return false
    }

    isValidating.value = true
    error.value = null

    try {
      kycLogger.debug('Calling /kyc/ofac/check API...')
      const response = await api.post<{
        data: { found: boolean; matches: unknown[]; count: number; warning?: string }
      }>('/kyc/ofac/check', {
        name: nameToCheck,
        similarity: 80 // Similarity threshold (0-100)
      })

      kycLogger.debug('OFAC response:', response.data)

      validations.value.ofac = {
        found: response.data.data.found,
        matches: response.data.data.matches as unknown[],
        score: response.data.data.count || 0
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
      isValidating.value = false
    }
  }

  const checkPldBlacklists = async (name?: string, curp?: string) => {
    kycLogger.debug('checkPldBlacklists called')
    const nameToCheck = name || fullNameFromIne.value
    const curpToCheck = curp || lockedData.value.curp

    kycLogger.debug('PLD name to check:', nameToCheck)

    if (!nameToCheck) {
      kycLogger.warn('No name available for PLD check')
      error.value = 'Se requiere nombre para verificar listas negras'
      return false
    }

    isValidating.value = true
    error.value = null

    try {
      kycLogger.debug('Calling /kyc/pld/check API...')
      const response = await api.post<{
        data: { found: boolean; matches: unknown[]; count: number; warning?: string }
      }>('/kyc/pld/check', {
        name: nameToCheck,
        curp: curpToCheck || undefined,
        similarity: 90 // Higher threshold for PLD to reduce false positives
      })

      kycLogger.debug('PLD response:', response.data)

      // Store in ofac field for compatibility (or create a separate pld field if needed)
      validations.value.ofac = {
        found: response.data.data.found,
        matches: response.data.data.matches as unknown[],
        score: response.data.data.count || 0
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
      isValidating.value = false
    }
  }

  const getBiometricToken = async (applicationId?: string) => {
    isLoading.value = true
    error.value = null

    try {
      const response = await api.post<BiometricTokenResponse>('/kyc/biometric/token', {
        application_id: applicationId
      })

      return response.data.data
    } catch (err: unknown) {
      kycLogger.error('Failed to get biometric token', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      error.value = errorResponse.response?.data?.message || 'Error al obtener token biométrico'
      return null
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Validate face match between selfie and INE photo.
   * Compares the captured selfie with the face on the INE to verify identity.
   */
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const validateFaceMatch = async (_applicantId?: string): Promise<boolean> => {
    kycLogger.debug('validateFaceMatch called')

    if (!selfieImage.value) {
      error.value = 'Se requiere la imagen de selfie'
      return false
    }

    if (!ineFrontImage.value) {
      error.value = 'Se requiere la imagen frontal del INE'
      return false
    }

    isValidating.value = true
    error.value = null

    try {
      kycLogger.debug('Calling /kyc/biometric/face-match API...')
      const response = await api.post<{
        message: string
        match: boolean
        score: number
        threshold: number
        validation_code?: string
      }>('/kyc/biometric/face-match', {
        selfie_image: selfieImage.value,
        ine_image: ineFrontImage.value,
        threshold: 80 // 80% similarity threshold
      })

      kycLogger.debug('Face match response:', response.data)

      const match = response.data.match
      const score = response.data.score

      // Store the result
      validations.value.face_match = { score, match }

      // NOTE: Verifications and document approval are now handled automatically by the backend
      // when calling /kyc/biometric/face-match - no need to call recordSingleVerification here
      kycLogger.debug('Face match validated - backend handles verification records and document approval automatically')

      return match
    } catch (err: unknown) {
      kycLogger.error('Failed to validate face match', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      error.value = errorResponse.response?.data?.message || 'Error en comparación facial'
      validations.value.face_match = { score: 0, match: false }
      return false
    } finally {
      isValidating.value = false
    }
  }

  /**
   * Validate liveness detection from selfie image.
   * Verifies that the captured face belongs to a real, present person (anti-spoofing).
   */
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const validateLiveness = async (_applicantId?: string): Promise<boolean> => {
    kycLogger.debug('validateLiveness called')

    if (!selfieImage.value) {
      error.value = 'Se requiere la imagen de selfie'
      return false
    }

    isValidating.value = true
    error.value = null

    try {
      kycLogger.debug('Calling /kyc/biometric/liveness API...')
      const response = await api.post<{
        message: string
        passed: boolean
        score: number
        validation_code?: string
      }>('/kyc/biometric/liveness', {
        face_image: selfieImage.value
      })

      kycLogger.debug('Liveness response:', response.data)

      const passed = response.data.passed
      const score = response.data.score

      // Store the result
      validations.value.liveness = { passed, score }

      // NOTE: Verifications are now handled automatically by the backend
      // when calling /kyc/biometric/liveness - no need to call recordSingleVerification here
      kycLogger.debug('Liveness validated - backend handles verification records automatically')

      return passed
    } catch (err: unknown) {
      kycLogger.error('Failed to validate liveness', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      error.value = errorResponse.response?.data?.message || 'Error en prueba de vida'
      validations.value.liveness = { passed: false, score: 0 }
      return false
    } finally {
      isValidating.value = false
    }
  }

  const setFaceMatchResult = (score: number, match: boolean) => {
    validations.value.face_match = { score, match }
  }

  const setLivenessResult = (passed: boolean, score?: number) => {
    validations.value.liveness = { passed, score }
  }

  const markVerified = () => {
    verified.value = true
  }

  /**
   * Record KYC verifications to the backend.
   * Should be called after KYC is completed with all verified data.
   */
  const recordVerifications = async (applicantId: string): Promise<boolean> => {
    if (!applicantId) {
      kycLogger.warn('No applicant ID for recording verifications')
      return false
    }

    const verifications: Array<{
      field: string
      value: unknown
      method: string
      verified: boolean
      metadata?: Record<string, unknown>
    }> = []

    // Record personal data from INE OCR
    if (lockedData.value.nombres) {
      verifications.push({
        field: 'first_name',
        value: lockedData.value.nombres,
        method: 'KYC_INE_OCR',
        verified: true,
        metadata: { source: 'ine_ocr' }
      })
    }

    if (lockedData.value.apellido_paterno) {
      verifications.push({
        field: 'last_name_1',
        value: lockedData.value.apellido_paterno,
        method: 'KYC_INE_OCR',
        verified: true,
        metadata: { source: 'ine_ocr' }
      })
    }

    if (lockedData.value.apellido_materno) {
      verifications.push({
        field: 'last_name_2',
        value: lockedData.value.apellido_materno,
        method: 'KYC_INE_OCR',
        verified: true,
        metadata: { source: 'ine_ocr' }
      })
    }

    if (lockedData.value.fecha_nacimiento) {
      verifications.push({
        field: 'birth_date',
        value: lockedData.value.fecha_nacimiento,
        method: 'KYC_INE_OCR',
        verified: true,
        metadata: { source: 'ine_ocr' }
      })
    }

    if (lockedData.value.sexo) {
      verifications.push({
        field: 'gender',
        value: lockedData.value.sexo,
        method: 'KYC_INE_OCR',
        verified: true,
        metadata: { source: 'ine_ocr' }
      })
    }

    if (lockedData.value.entidad_nacimiento) {
      verifications.push({
        field: 'birth_state',
        value: lockedData.value.entidad_nacimiento,
        method: 'KYC_INE_OCR',
        verified: true,
        metadata: { source: 'curp_extraction' }
      })
    }

    // CURP validation
    if (lockedData.value.curp && validations.value.curp_renapo?.valid) {
      verifications.push({
        field: 'curp',
        value: lockedData.value.curp,
        method: 'KYC_CURP_RENAPO',
        verified: true,
        metadata: validations.value.curp_renapo.data as Record<string, unknown> || {}
      })
    }

    // INE Clave de Elector
    if (lockedData.value.clave_elector) {
      verifications.push({
        field: 'ine_clave',
        value: lockedData.value.clave_elector,
        method: validations.value.ine_lista_nominal?.valid ? 'KYC_INE_LIST' : 'KYC_INE_OCR',
        verified: validations.value.ine_lista_nominal?.valid || false,
        metadata: validations.value.ine_lista_nominal || {}
      })
    }

    // INE OCR number
    if (lockedData.value.ocr) {
      verifications.push({
        field: 'ine_ocr',
        value: lockedData.value.ocr,
        method: 'KYC_INE_OCR',
        verified: true,
        metadata: { source: 'ine_ocr' }
      })
    }

    // Address from INE
    const addr = lockedData.value.direccion_ine
    if (addr.calle) {
      verifications.push({
        field: 'address_street',
        value: addr.calle,
        method: 'KYC_INE_OCR',
        verified: true,
        metadata: { source: 'ine_address' }
      })
    }
    if (addr.colonia) {
      verifications.push({
        field: 'address_neighborhood',
        value: addr.colonia,
        method: 'KYC_INE_OCR',
        verified: true,
        metadata: { source: 'ine_address' }
      })
    }
    if (addr.cp) {
      verifications.push({
        field: 'address_postal_code',
        value: addr.cp,
        method: 'KYC_INE_OCR',
        verified: true,
        metadata: { source: 'ine_address' }
      })
    }
    if (addr.municipio || addr.ciudad) {
      verifications.push({
        field: 'address_city',
        value: addr.municipio || addr.ciudad,
        method: 'KYC_INE_OCR',
        verified: true,
        metadata: { source: 'ine_address' }
      })
    }
    if (addr.estado) {
      verifications.push({
        field: 'address_state',
        value: addr.estado,
        method: 'KYC_INE_OCR',
        verified: true,
        metadata: { source: 'ine_address' }
      })
    }

    // KYC validation results
    if (validations.value.face_match) {
      verifications.push({
        field: 'face_match',
        value: validations.value.face_match.match ? 'passed' : 'failed',
        method: 'KYC_FACE_MATCH',
        verified: validations.value.face_match.match,
        metadata: { score: validations.value.face_match.score }
      })
    }

    if (validations.value.liveness) {
      verifications.push({
        field: 'liveness',
        value: validations.value.liveness.passed ? 'passed' : 'failed',
        method: 'KYC_LIVENESS',
        verified: validations.value.liveness.passed,
        metadata: { score: validations.value.liveness.score }
      })
    }

    if (validations.value.ofac) {
      verifications.push({
        field: 'ofac_clear',
        value: validations.value.ofac.found ? 'found' : 'clear',
        method: 'KYC_OFAC',
        verified: !validations.value.ofac.found,
        metadata: { matches: validations.value.ofac.matches, count: validations.value.ofac.score }
      })
    }

    // Document verifications
    if (ineFrontImage.value) {
      verifications.push({
        field: 'ine_document',
        value: 'captured',
        method: 'KYC_INE_OCR',
        verified: true,
        metadata: { has_front: true, has_back: !!ineBackImage.value }
      })
    }

    if (selfieImage.value) {
      verifications.push({
        field: 'selfie',
        value: 'captured',
        method: 'KYC_LIVENESS',
        verified: validations.value.liveness?.passed || validations.value.face_match?.match || false,
        metadata: {}
      })
    }

    // RFC if validated
    if (rfcValidation.value?.valid) {
      verifications.push({
        field: 'rfc',
        value: rfcValidation.value.rfc,
        method: 'KYC_RFC_SAT',
        verified: true,
        metadata: { razon_social: rfcValidation.value.razon_social, tipo_persona: rfcValidation.value.tipo_persona }
      })
    }

    if (verifications.length === 0) {
      kycLogger.warn('No verifications to record')
      return false
    }

    try {
      kycLogger.debug('Recording verifications:', verifications.length)
      await api.post('/kyc/verifications', {
        applicant_id: applicantId,
        verifications
      })
      kycLogger.debug('Verifications recorded successfully')
      return true
    } catch (err) {
      kycLogger.error('Failed to record verifications', err)
      return false
    }
  }

  // Batching system for verifications to reduce API calls
  const pendingVerifications = ref<Array<{
    applicantId: string
    field: string
    value: unknown
    method: string
    verified: boolean
    metadata?: Record<string, unknown>
  }>>([])
  let batchTimeout: ReturnType<typeof setTimeout> | null = null
  const BATCH_DELAY_MS = 100 // Wait 100ms to collect all verifications before sending

  /**
   * Flush pending verifications to the backend in a single batch.
   */
  const flushPendingVerifications = async (): Promise<void> => {
    if (pendingVerifications.value.length === 0) return

    // Group verifications by applicantId
    const grouped = new Map<string, typeof pendingVerifications.value>()
    for (const v of pendingVerifications.value) {
      const existing = grouped.get(v.applicantId) || []
      existing.push(v)
      grouped.set(v.applicantId, existing)
    }

    // Clear pending verifications before sending
    pendingVerifications.value = []

    // Send batch for each applicant
    for (const [applicantId, verifications] of grouped) {
      try {
        kycLogger.debug(`Batch sending ${verifications.length} verifications for applicant`, { applicantId })
        await api.post('/kyc/verifications', {
          applicant_id: applicantId,
          verifications: verifications.map(v => ({
            field: v.field,
            value: v.value,
            method: v.method,
            verified: v.verified,
            metadata: v.metadata
          }))
        })
        kycLogger.debug(`Batch of ${verifications.length} verifications recorded successfully`)
      } catch (err) {
        kycLogger.error('Failed to batch record verifications', err)
      }
    }
  }

  /**
   * Record a single verification with batching (auto-record after validation).
   * Verifications are queued and sent together after a short delay to reduce API calls.
   */
  const recordSingleVerification = async (
    applicantId: string,
    field: string,
    value: unknown,
    method: string,
    verified: boolean,
    metadata?: Record<string, unknown>
  ): Promise<boolean> => {
    if (!applicantId) {
      kycLogger.warn('No applicant ID for recording verification')
      return false
    }

    // Add to pending batch
    pendingVerifications.value.push({
      applicantId,
      field,
      value,
      method,
      verified,
      metadata
    })

    // Clear existing timeout and set new one
    if (batchTimeout) {
      clearTimeout(batchTimeout)
    }

    // Flush after delay
    batchTimeout = setTimeout(() => {
      flushPendingVerifications()
      batchTimeout = null
    }, BATCH_DELAY_MS)

    return true
  }

  /**
   * Load verified fields from backend for an applicant.
   * This also reconstructs lockedData from the verified fields so that
   * the KYC state is properly restored when returning to onboarding.
   */
  const loadVerifications = async (applicantId: string): Promise<boolean> => {
    if (!applicantId) {
      kycLogger.warn('No applicant ID for loading verifications')
      return false
    }

    try {
      kycLogger.debug('Loading verifications for applicant:', applicantId)
      const response = await api.get<VerificationsResponse>(`/kyc/verifications/${applicantId}`)
      kycLogger.debug('API response:', response.data)

      verifiedFields.value = response.data.data.verified_fields || {}
      verificationsSummary.value = response.data.data.summary || {
        personal_data: {},
        contact: {},
        address: {},
        kyc: {}
      }

      // Reconstruct lockedData from verified fields
      // This is important when returning to onboarding after KYC was already done
      const fields = verifiedFields.value
      kycLogger.debug('verified_fields from API:', fields)

      // Check if we have KYC data (CURP is the key indicator)
      const hasCurp = !!fields.curp?.value
      kycLogger.debug('hasCurp check', { hasCurp, value: fields.curp?.value })

      if (fields.curp?.value) {
        lockedData.value.curp = fields.curp.value
      }
      if (fields.first_name?.value) {
        lockedData.value.nombres = fields.first_name.value
      }
      if (fields.last_name_1?.value) {
        lockedData.value.apellido_paterno = fields.last_name_1.value
      }
      if (fields.last_name_2?.value) {
        lockedData.value.apellido_materno = fields.last_name_2.value
      }
      if (fields.birth_date?.value) {
        lockedData.value.fecha_nacimiento = fields.birth_date.value
      }
      if (fields.gender?.value) {
        // Convert M/F to H/M format used internally
        const genderValue = fields.gender.value.toUpperCase()
        lockedData.value.sexo = (genderValue === 'H' || genderValue === 'M') ? genderValue as 'H' | 'M' : null
      }
      if (fields.birth_state?.value) {
        lockedData.value.entidad_nacimiento = fields.birth_state.value
      }
      if (fields.ine_clave?.value) {
        lockedData.value.clave_elector = fields.ine_clave.value
      }
      if (fields.ine_ocr?.value) {
        lockedData.value.ocr = fields.ine_ocr.value
      }
      if (fields.ine_folio?.value || fields.ine_cic?.value) {
        lockedData.value.cic = fields.ine_folio?.value || fields.ine_cic?.value || null
      }

      // Reconstruct direccion_ine from address fields
      if (fields.address_street?.value) {
        lockedData.value.direccion_ine.calle = fields.address_street.value
      }
      if (fields.address_neighborhood?.value) {
        lockedData.value.direccion_ine.colonia = fields.address_neighborhood.value
      }
      if (fields.address_postal_code?.value) {
        lockedData.value.direccion_ine.cp = fields.address_postal_code.value
      }
      if (fields.address_city?.value) {
        lockedData.value.direccion_ine.municipio = fields.address_city.value
      }
      if (fields.address_state?.value) {
        lockedData.value.direccion_ine.estado = fields.address_state.value
      }

      // Set verified based on backend flag OR if we have CURP data (from backend or existing in memory)
      // This ensures KYC state is restored even if kyc_verified flag is not set
      // Also preserve verified state if lockedData already has a CURP from current session
      const hasExistingCurp = !!lockedData.value.curp
      verified.value = response.data.data.kyc_verified || hasCurp || hasExistingCurp

      kycLogger.debug('Loaded verifications:', Object.keys(verifiedFields.value).length)
      kycLogger.debug('hasCurp from API', { hasCurp, hasExistingCurp })
      kycLogger.debug('Reconstructed lockedData:', {
        curp: lockedData.value.curp,
        nombres: lockedData.value.nombres,
        clave_elector: lockedData.value.clave_elector,
        sexo: lockedData.value.sexo,
        entidad_nacimiento: lockedData.value.entidad_nacimiento,
        direccion_ine: lockedData.value.direccion_ine
      })
      kycLogger.debug('verified flag:', verified.value)

      return true
    } catch (err) {
      kycLogger.error('Failed to load verifications', err)
      return false
    }
  }

  /**
   * Check if a specific field is verified.
   */
  const isFieldVerified = (fieldName: string): boolean => {
    return !!verifiedFields.value[fieldName]
  }

  /**
   * Check if a specific field is locked (cannot be modified).
   */
  const isFieldLocked = (fieldName: string): boolean => {
    return verifiedFields.value[fieldName]?.is_locked === true
  }

  /**
   * Get verification info for a field.
   */
  const getFieldVerification = (fieldName: string): VerifiedField | null => {
    return verifiedFields.value[fieldName] || null
  }

  /**
   * Upload INE documents with KYC validation metadata.
   * Should be called after INE validation is successful.
   */
  const uploadIneDocuments = async (applicationId: string): Promise<{ front: boolean; back: boolean }> => {
    if (!applicationId) {
      kycLogger.warn('No application ID for uploading INE documents')
      return { front: false, back: false }
    }

    const result = { front: false, back: false }

    // Prepare KYC metadata
    const kycMetadata = {
      kyc_validated: true,
      source: 'kyc',
      nubarium_validated: true,
      validation_method: 'KYC_INE_OCR',
      ine_ocr: true,
      validated_at: new Date().toISOString(),
      ine_valid: validations.value.ine_lista_nominal?.valid || false,
      ocr_data: lockedData.value
    }

    try {
      // Upload INE_FRONT if available
      if (ineFrontImage.value) {
        kycLogger.debug('Uploading INE_FRONT with KYC metadata')
        // Convert base64 to File
        const frontBlob = await fetch(ineFrontImage.value).then(r => r.blob())
        const frontFile = new File([frontBlob], 'ine_front.jpg', { type: 'image/jpeg' })

        const applicationService = (await import('@/services/application.service')).default
        await applicationService.uploadDocument(applicationId, 'INE_FRONT', frontFile, kycMetadata)
        result.front = true
        kycLogger.debug('INE_FRONT uploaded with KYC metadata')
      }

      // Upload INE_BACK if available
      if (ineBackImage.value) {
        kycLogger.debug('Uploading INE_BACK with KYC metadata')
        // Convert base64 to File
        const backBlob = await fetch(ineBackImage.value).then(r => r.blob())
        const backFile = new File([backBlob], 'ine_back.jpg', { type: 'image/jpeg' })

        const applicationService = (await import('@/services/application.service')).default
        await applicationService.uploadDocument(applicationId, 'INE_BACK', backFile, kycMetadata)
        result.back = true
        kycLogger.debug('INE_BACK uploaded with KYC metadata')
      }

      return result
    } catch (err) {
      kycLogger.error('Failed to upload INE documents', err)
      return result
    }
  }

  /**
   * Upload selfie document with face match validation metadata.
   * Should be called after face match validation is successful.
   */
  const uploadSelfieDocument = async (applicationId: string): Promise<boolean> => {
    if (!applicationId) {
      kycLogger.warn('No application ID for uploading selfie document')
      return false
    }

    if (!selfieImage.value) {
      kycLogger.warn('No selfie image to upload')
      return false
    }

    // Prepare metadata with face match validation info
    const selfieMetadata = {
      kyc_validated: true,
      source: 'kyc',
      nubarium_validated: true,
      validation_method: 'KYC_FACE_MATCH',
      face_match: true,
      validated_at: new Date().toISOString(),
      face_match_score: validations.value.face_match?.score || null,
      face_match_passed: validations.value.face_match?.match || false,
      liveness_passed: validations.value.liveness?.passed || null,
      liveness_score: validations.value.liveness?.score || null
    }

    try {
      kycLogger.debug('Uploading SELFIE with face match metadata')

      // Convert base64 to File
      // selfieImage is stored as base64 without data URI prefix
      const base64Data = selfieImage.value.startsWith('data:')
        ? selfieImage.value
        : `data:image/jpeg;base64,${selfieImage.value}`
      const selfieBlob = await fetch(base64Data).then(r => r.blob())
      const selfieFile = new File([selfieBlob], 'selfie.jpg', { type: 'image/jpeg' })

      const applicationService = (await import('@/services/application.service')).default
      await applicationService.uploadDocument(applicationId, 'SELFIE', selfieFile, selfieMetadata)

      kycLogger.debug('SELFIE uploaded with face match metadata')
      return true
    } catch (err) {
      kycLogger.error('Failed to upload selfie document', err)
      return false
    }
  }

  const reset = () => {
    verified.value = false
    isConfigured.value = false
    availableServices.value = []
    isLoading.value = false
    isValidating.value = false
    error.value = null
    ineFrontImage.value = null
    ineBackImage.value = null
    selfieImage.value = null
    lockedData.value = {
      nombres: null,
      apellido_paterno: null,
      apellido_materno: null,
      fecha_nacimiento: null,
      sexo: null,
      curp: null,
      clave_elector: null,
      vigencia: null,
      entidad_nacimiento: null,
      ocr: null,
      cic: null,
      identificador_ciudadano: null,
      direccion_ine: {
        calle: null,
        colonia: null,
        cp: null,
        localidad: null,
        ciudad: null,
        municipio: null,
        estado: null,
      }
    }
    validations.value = {
      ine_ocr: null,
      ine_lista_nominal: null,
      curp_renapo: null,
      face_match: null,
      liveness: null,
      ofac: null,
    }
    rfcValidation.value = null
    verifiedFields.value = {}
    verificationsSummary.value = {
      personal_data: {},
      contact: {},
      address: {},
      kyc: {}
    }
  }

  return {
    // State
    verified,
    isConfigured,
    availableServices,
    birthStates,
    isLoading,
    isValidating,
    error,
    ineFrontImage,
    ineBackImage,
    selfieImage,
    lockedData,
    validations,
    rfcValidation,
    verifiedFields,
    verificationsSummary,
    // Getters
    hasNubarium,
    fullNameFromIne,
    addressFromIne,
    isIneValid,
    isCurpValid,
    isFaceMatched,
    isLivenessPassed,
    isOfacClear,
    allValidationsPassed,
    validationProgress,
    // Actions
    checkServices,
    testConnection,
    refreshToken,
    setIneFrontImage,
    setIneBackImage,
    setSelfieImage,
    validateIne,
    validateCurp,
    validateRfc,
    checkOfac,
    checkPldBlacklists,
    getBiometricToken,
    validateFaceMatch,
    validateLiveness,
    setFaceMatchResult,
    setLivenessResult,
    markVerified,
    recordVerifications,
    recordSingleVerification,
    loadVerifications,
    isFieldVerified,
    isFieldLocked,
    getFieldVerification,
    uploadIneDocuments,
    uploadSelfieDocument,
    reset
  }
})
