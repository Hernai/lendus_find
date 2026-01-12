import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/services/api'

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
      console.error('Failed to check KYC services:', err)
      isConfigured.value = false
      return false
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

  const validateIne = async () => {
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
        console.log('[KYC Store] OCR data received:', ocr)

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

        console.log('[KYC Store] lockedData set:', lockedData.value)

        validations.value.ine_ocr = {
          success: true,
          data: response.data.ocr_data as unknown as Record<string, unknown>
        }
      }

      // Store list validation
      if (response.data.list_validation) {
        validations.value.ine_lista_nominal = response.data.list_validation
      }

      return response.data.is_valid === true
    } catch (err: unknown) {
      console.error('Failed to validate INE:', err)
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

  const validateCurp = async (curp?: string) => {
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

      return response.data.valid
    } catch (err: unknown) {
      console.error('Failed to validate CURP:', err)
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

  const validateRfc = async (rfc: string): Promise<{ valid: boolean; razon_social?: string; error?: string }> => {
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

      return {
        valid: result.valid,
        razon_social: result.razon_social
      }
    } catch (err: unknown) {
      console.error('Failed to validate RFC:', err)
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
    console.log('[KYC Store] checkOfac called')
    const nameToCheck = name || fullNameFromIne.value

    console.log('[KYC Store] OFAC name to check:', nameToCheck)

    if (!nameToCheck) {
      console.warn('[KYC Store] No name available for OFAC check')
      error.value = 'Se requiere nombre para verificar OFAC'
      return false
    }

    isValidating.value = true
    error.value = null

    try {
      console.log('[KYC Store] Calling /kyc/ofac/check API...')
      const response = await api.post<{
        data: { found: boolean; matches: unknown[]; count: number; warning?: string }
      }>('/kyc/ofac/check', {
        name: nameToCheck,
        similarity: 80 // Similarity threshold (0-100)
      })

      console.log('[KYC Store] OFAC response:', response.data)

      validations.value.ofac = {
        found: response.data.data.found,
        matches: response.data.data.matches as unknown[],
        score: response.data.data.count || 0
      }

      // If there's a warning (service unavailable), treat as not found
      if (response.data.data.warning) {
        console.warn('OFAC warning:', response.data.data.warning)
      }

      return !response.data.data.found
    } catch (err: unknown) {
      console.error('[KYC Store] Failed to check OFAC:', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      error.value = errorResponse.response?.data?.message || 'Error al verificar OFAC'
      // Return true on error to not block validation (service might be unavailable)
      return true
    } finally {
      isValidating.value = false
    }
  }

  const checkPldBlacklists = async (name?: string, curp?: string) => {
    console.log('[KYC Store] checkPldBlacklists called')
    const nameToCheck = name || fullNameFromIne.value
    const curpToCheck = curp || lockedData.value.curp

    console.log('[KYC Store] PLD name to check:', nameToCheck)

    if (!nameToCheck) {
      console.warn('[KYC Store] No name available for PLD check')
      error.value = 'Se requiere nombre para verificar listas negras'
      return false
    }

    isValidating.value = true
    error.value = null

    try {
      console.log('[KYC Store] Calling /kyc/pld/check API...')
      const response = await api.post<{
        data: { found: boolean; matches: unknown[]; count: number; warning?: string }
      }>('/kyc/pld/check', {
        name: nameToCheck,
        curp: curpToCheck || undefined,
        similarity: 90 // Higher threshold for PLD to reduce false positives
      })

      console.log('[KYC Store] PLD response:', response.data)

      // Store in ofac field for compatibility (or create a separate pld field if needed)
      validations.value.ofac = {
        found: response.data.data.found,
        matches: response.data.data.matches as unknown[],
        score: response.data.data.count || 0
      }

      // If there's a warning (service unavailable), treat as not found
      if (response.data.data.warning) {
        console.warn('PLD warning:', response.data.data.warning)
      }

      return !response.data.data.found
    } catch (err: unknown) {
      console.error('[KYC Store] Failed to check PLD blacklists:', err)
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
      console.error('Failed to get biometric token:', err)
      const errorResponse = err as { response?: { data?: { message?: string } } }
      error.value = errorResponse.response?.data?.message || 'Error al obtener token biométrico'
      return null
    } finally {
      isLoading.value = false
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
      console.warn('[KYC Store] No applicant ID for recording verifications')
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
      console.warn('[KYC Store] No verifications to record')
      return false
    }

    try {
      console.log('[KYC Store] Recording verifications:', verifications.length)
      await api.post('/kyc/verifications', {
        applicant_id: applicantId,
        verifications
      })
      console.log('[KYC Store] Verifications recorded successfully')
      return true
    } catch (err) {
      console.error('[KYC Store] Failed to record verifications:', err)
      return false
    }
  }

  /**
   * Load verified fields from backend for an applicant.
   */
  const loadVerifications = async (applicantId: string): Promise<boolean> => {
    if (!applicantId) {
      console.warn('[KYC Store] No applicant ID for loading verifications')
      return false
    }

    try {
      const response = await api.get<VerificationsResponse>(`/kyc/verifications/${applicantId}`)
      verifiedFields.value = response.data.data.verified_fields
      verificationsSummary.value = response.data.data.summary
      verified.value = response.data.data.kyc_verified
      console.log('[KYC Store] Loaded verifications:', Object.keys(verifiedFields.value).length)
      return true
    } catch (err) {
      console.error('[KYC Store] Failed to load verifications:', err)
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
   * Get verification info for a field.
   */
  const getFieldVerification = (fieldName: string): VerifiedField | null => {
    return verifiedFields.value[fieldName] || null
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
    setIneFrontImage,
    setIneBackImage,
    setSelfieImage,
    validateIne,
    validateCurp,
    validateRfc,
    checkOfac,
    checkPldBlacklists,
    getBiometricToken,
    setFaceMatchResult,
    setLivenessResult,
    markVerified,
    recordVerifications,
    loadVerifications,
    isFieldVerified,
    getFieldVerification,
    reset
  }
})
