/**
 * KYC Verification Method Constants
 *
 * These constants define the verification methods used throughout the KYC process.
 * Using constants instead of magic strings improves type safety and maintainability.
 */

/**
 * Verification methods for KYC validations.
 * These map to the backend VerificationMethod enum.
 */
export const KYC_METHODS = {
  // Document-based verifications
  INE_OCR: 'KYC_INE_OCR',
  INE_LIST: 'KYC_INE_LIST',

  // Identity verifications
  CURP_RENAPO: 'KYC_CURP_RENAPO',
  RFC_SAT: 'KYC_RFC_SAT',

  // Biometric verifications
  FACE_MATCH: 'KYC_FACE_MATCH',
  LIVENESS: 'KYC_LIVENESS',

  // Compliance verifications
  OFAC: 'KYC_OFAC',
  PLD: 'KYC_PLD',

  // Manual verifications
  MANUAL: 'MANUAL',
  STAFF_REVIEW: 'STAFF_REVIEW',
} as const

export type KycMethod = (typeof KYC_METHODS)[keyof typeof KYC_METHODS]

/**
 * Verifiable fields in KYC process.
 * These map to the backend VerifiableField enum.
 */
export const VERIFIABLE_FIELDS = {
  // Personal data
  FIRST_NAME: 'first_name',
  LAST_NAME_1: 'last_name_1',
  LAST_NAME_2: 'last_name_2',
  BIRTH_DATE: 'birth_date',
  GENDER: 'gender',
  BIRTH_STATE: 'birth_state',

  // Identity documents
  CURP: 'curp',
  RFC: 'rfc',
  INE_CLAVE: 'ine_clave',
  INE_OCR: 'ine_ocr',
  INE_FOLIO: 'ine_folio',

  // Address fields
  ADDRESS_STREET: 'address_street',
  ADDRESS_NEIGHBORHOOD: 'address_neighborhood',
  ADDRESS_CITY: 'address_city',
  ADDRESS_STATE: 'address_state',
  ADDRESS_POSTAL_CODE: 'address_postal_code',

  // Biometric verifications
  FACE_MATCH: 'face_match',
  LIVENESS: 'liveness',

  // Compliance checks
  OFAC_CLEAR: 'ofac_clear',
  PLD_CLEAR: 'pld_clear',

  // Document captures
  INE_DOCUMENT: 'ine_document',
  INE_DOCUMENT_FRONT: 'ine_document_front',
  INE_DOCUMENT_BACK: 'ine_document_back',
  SELFIE: 'selfie',
} as const

export type VerifiableField = (typeof VERIFIABLE_FIELDS)[keyof typeof VERIFIABLE_FIELDS]

/**
 * Human-readable labels for verification methods (Spanish).
 */
export const KYC_METHOD_LABELS: Record<KycMethod, string> = {
  [KYC_METHODS.INE_OCR]: 'OCR de INE',
  [KYC_METHODS.INE_LIST]: 'Lista Nominal INE',
  [KYC_METHODS.CURP_RENAPO]: 'RENAPO',
  [KYC_METHODS.RFC_SAT]: 'SAT',
  [KYC_METHODS.FACE_MATCH]: 'Comparación Facial',
  [KYC_METHODS.LIVENESS]: 'Prueba de Vida',
  [KYC_METHODS.OFAC]: 'OFAC/Sanciones',
  [KYC_METHODS.PLD]: 'PLD/Listas Negras',
  [KYC_METHODS.MANUAL]: 'Verificación Manual',
  [KYC_METHODS.STAFF_REVIEW]: 'Revisión de Staff',
}

/**
 * Human-readable labels for verifiable fields (Spanish).
 */
export const VERIFIABLE_FIELD_LABELS: Record<VerifiableField, string> = {
  [VERIFIABLE_FIELDS.FIRST_NAME]: 'Nombre(s)',
  [VERIFIABLE_FIELDS.LAST_NAME_1]: 'Apellido Paterno',
  [VERIFIABLE_FIELDS.LAST_NAME_2]: 'Apellido Materno',
  [VERIFIABLE_FIELDS.BIRTH_DATE]: 'Fecha de Nacimiento',
  [VERIFIABLE_FIELDS.GENDER]: 'Sexo',
  [VERIFIABLE_FIELDS.BIRTH_STATE]: 'Estado de Nacimiento',
  [VERIFIABLE_FIELDS.CURP]: 'CURP',
  [VERIFIABLE_FIELDS.RFC]: 'RFC',
  [VERIFIABLE_FIELDS.INE_CLAVE]: 'Clave de Elector',
  [VERIFIABLE_FIELDS.INE_OCR]: 'OCR INE',
  [VERIFIABLE_FIELDS.INE_FOLIO]: 'Folio INE',
  [VERIFIABLE_FIELDS.ADDRESS_STREET]: 'Calle',
  [VERIFIABLE_FIELDS.ADDRESS_NEIGHBORHOOD]: 'Colonia',
  [VERIFIABLE_FIELDS.ADDRESS_CITY]: 'Ciudad/Municipio',
  [VERIFIABLE_FIELDS.ADDRESS_STATE]: 'Estado',
  [VERIFIABLE_FIELDS.ADDRESS_POSTAL_CODE]: 'Código Postal',
  [VERIFIABLE_FIELDS.FACE_MATCH]: 'Comparación Facial',
  [VERIFIABLE_FIELDS.LIVENESS]: 'Prueba de Vida',
  [VERIFIABLE_FIELDS.OFAC_CLEAR]: 'Verificación OFAC',
  [VERIFIABLE_FIELDS.PLD_CLEAR]: 'Verificación PLD',
  [VERIFIABLE_FIELDS.INE_DOCUMENT]: 'Documento INE',
  [VERIFIABLE_FIELDS.INE_DOCUMENT_FRONT]: 'INE Frente',
  [VERIFIABLE_FIELDS.INE_DOCUMENT_BACK]: 'INE Reverso',
  [VERIFIABLE_FIELDS.SELFIE]: 'Selfie',
}

/**
 * KYC status values.
 */
export const KYC_STATUS = {
  PENDING: 'pending',
  IN_PROGRESS: 'in_progress',
  VERIFIED: 'verified',
  FAILED: 'failed',
  REQUIRES_REVIEW: 'requires_review',
} as const

export type KycStatus = (typeof KYC_STATUS)[keyof typeof KYC_STATUS]
